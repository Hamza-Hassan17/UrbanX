<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

class CustomRideController extends Controller
{
    public function index()
    {
        $this->authorize('view custom rides');
        try {
            // Every driver, not just ones with a registered vehicle -- otherwise
            // typing a real driver's ID that happens to have no vehicle yet gives
            // the misleading "No driver found with ID: x". They still can't be
            // assigned a ride (no vehicle_type_id), but the UI can now say why.
            $drivers = User::with('driverVehicle.vehicleType', 'profile:id,user_id,city')
            ->role('driver')
            ->get()
            ->map(function ($driver) {
                return [
                    'id'     => $driver->id,
                    'name'   => $driver->name,
                    'phone'   => $driver->phone,
                    'has_vehicle' => (bool) $driver->driverVehicle,
                    'lat'    => $driver->lat ? (float) $driver->lat : null,
                    'lng'    => $driver->lang ? (float) $driver->lang : null,
                    'status' => $driver->driver_status, // busy | available
                    'icon'   => $driver->driver_status === 'available'
                                    ? 'taxiIcon'
                                    : 'taxiIconBusy',
                    'vehicle'  => $driver->driverVehicle ? $driver->driverVehicle->vehicle_name.' '.$driver->driverVehicle->vehicle_make.' '.$driver->driverVehicle->vehicle_year : 'N/A',
                    'vehicle_type_id' => $driver->driverVehicle ? $driver->driverVehicle->vehicle_type_id : null,
                    // Whether this driver's vehicle is delivery-capable -- same flag
                    // DeliveryController::getLatestRides uses to decide who can see
                    // delivery jobs at all, reused here so the dispatch queue only
                    // ever offers a delivery-capable driver for a delivery job.
                    'is_delivery' => $driver->driverVehicle && $driver->driverVehicle->vehicleType
                                    ? (bool) $driver->driverVehicle->vehicleType->is_delivery
                                    : false,
                    // Admin-assigned at onboarding (Live Ops brief Task 1) -- not
                    // derived from GPS. Null until someone sets it via the driver
                    // detail page; the UI buckets these under "Unassigned".
                    'city'   => $driver->profile->city ?? null,
                ];
            });

            $driver = User::role('driver')
                ->where('driver_status', 'available') // only available drivers
                ->whereHas('driverVehicle')          // must have a vehicle
                ->inRandomOrder()                    // randomize
                ->with('driverVehicle')              // eager load vehicle info
                ->first();

            $dispatch = $this->getDispatchSnapshot();

            // Distinct list of cities actually assigned to a driver right now, for
            // the city-filter dropdown -- "All Cities" and "Unassigned" are handled
            // separately in the view since they're not real city values.
            $cities = $drivers->pluck('city')->filter()->unique()->sort()->values();

            return view('dashboard.custom-rides.index', array_merge(
                compact('drivers', 'driver', 'cities'),
                $dispatch
            ));
        } catch (\Throwable $th) {
            Log::error('Custom Rides Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Live dispatch counters + ride queue, polled from the dispatch screen.
     */
    public function dispatchStats()
    {
        $this->authorize('view custom rides');
        try {
            return response()->json($this->getDispatchSnapshot());
        } catch (\Throwable $th) {
            Log::error('Dispatch Stats Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Live Ops: multi-city tracking + live trace, polled from the map view.
     *
     * IMPORTANT finding, not something to silently work around: there is no
     * continuous location-write path for taxi drivers at all -- grepped every
     * API controller under Driver/*, the only place users.lat/lang ever gets
     * set is at registration/login (one-time snapshot), never refreshed while
     * a driver is online. So taxi driver positions here are "last known", not
     * live GPS -- labelled as such in the UI rather than pretending otherwise.
     * Delivery riders DO have real continuous writes
     * (DeliveryController::updateRiderLocation -> restaurant_orders/{id}/rider_location),
     * so those genuinely are live.
     *
     * Also: there's no Firebase Web SDK config anywhere in this codebase (only
     * the server-side service account, which must never reach the browser), so
     * this relays Firebase reads through the backend on the same polling
     * pattern dispatchStats already uses, rather than a client-side Firebase
     * subscription -- avoids needing a new public web API key or touching
     * Firebase security rules blind.
     */
    public function liveTrackingData()
    {
        $this->authorize('view live tracking');
        try {
            $activeRideStatuses = ['accepted', 'en_route', 'arrived', 'started'];

            $activeRides = Ride::with(['driver:id,name,phone,lat,lang', 'passenger:id,name,phone'])
                ->where('ride_type', 'ride')
                ->whereIn('status', $activeRideStatuses)
                ->whereNotNull('driver_id')
                ->get()
                ->map(function ($ride) {
                    return [
                        'ride_id' => $ride->id,
                        'driver_id' => $ride->driver_id,
                        'driver_name' => $ride->driver->name ?? null,
                        'passenger_name' => $ride->passenger->name ?? null,
                        // Last-known position (see class-level note above) --
                        // driver's own lat/lng, since taxi rides don't have a
                        // continuous location trail to show instead.
                        'lat' => $ride->driver->lat ? (float) $ride->driver->lat : null,
                        'lng' => $ride->driver->lang ? (float) $ride->driver->lang : null,
                        'pickup' => $ride->pickup_latitude . ', ' . $ride->pickup_longitude,
                        'dropoff' => $ride->dropoff_latitude . ', ' . $ride->dropoff_longitude,
                        'status' => $ride->status,
                        'ride_type' => 'ride',
                    ];
                })
                ->filter(fn ($r) => $r['lat'] && $r['lng'])
                ->values();

            $activeOrderStatuses = ['rider_assigned', 'picked_up', 'on_the_way'];

            $activeDeliveries = RestaurantOrder::with(['restaurant:id,name', 'customer:id,name'])
                ->whereIn('status', $activeOrderStatuses)
                ->whereNotNull('ride_id')
                ->get()
                ->map(function ($order) {
                    $ride = Ride::find($order->ride_id);
                    // Real live position -- rider_latitude/rider_longitude are
                    // updated on every GPS ping by DeliveryController::
                    // updateRiderLocation() (see that method's RiderLocationUpdated
                    // broadcast). A WebSocket push has no queryable "current
                    // value" the way Firebase RTDB's getValue() did, so this
                    // dashboard poll reads the persisted last-known position
                    // straight from the order row instead.
                    return [
                        'order_id' => $order->id,
                        'ride_id' => $order->ride_id,
                        'restaurant_name' => $order->restaurant->name ?? null,
                        'customer_name' => $order->customer->name ?? null,
                        'lat' => $order->rider_latitude !== null ? (float) $order->rider_latitude : null,
                        'lng' => $order->rider_longitude !== null ? (float) $order->rider_longitude : null,
                        'pickup' => $ride ? $ride->pickup_latitude . ', ' . $ride->pickup_longitude : null,
                        'dropoff' => $ride ? $ride->dropoff_latitude . ', ' . $ride->dropoff_longitude : null,
                        'status' => $order->status,
                        'ride_type' => 'delivery',
                    ];
                })
                ->filter(fn ($d) => $d['lat'] && $d['lng'])
                ->values();

            return response()->json([
                'activeRides' => $activeRides,
                'activeDeliveries' => $activeDeliveries,
            ]);
        } catch (\Throwable $th) {
            Log::error('Live Tracking Data Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Driver availability counts, today's ride counts by status, and the current
     * ride queue — shared by the initial page render and the polling endpoint so
     * both stay in sync.
     */
    private function getDispatchSnapshot(): array
    {
        $driverAvailableCount = User::role('driver')->where('driver_status', 'available')->count();
        $driverBusyCount = User::role('driver')->where('driver_status', 'busy')->count();

        $activeStatuses = ['requested', 'accepted', 'en_route', 'arrived', 'started'];

        $rideCounts = [
            'dispatch'  => Ride::where('status', 'requested')->whereNull('driver_id')->count(),
            'booked'    => Ride::whereIn('status', ['requested', 'accepted', 'en_route', 'arrived', 'started'])
                                ->whereNotNull('driver_id')->count(),
            'completed' => Ride::whereDate('completed_at', today())->where('status', 'completed')->count(),
            'cancelled' => Ride::whereDate('cancelled_at', today())->where('status', 'cancelled')->count(),
        ];

        $rides = Ride::with(['driver:id,name', 'passenger:id,name,phone'])
            ->whereIn('status', array_merge($activeStatuses, ['completed', 'cancelled']))
            ->latest('requested_at')
            ->take(50)
            ->get()
            ->map(function ($ride) {
                $queue = $ride->status === 'completed'
                    ? 'completed'
                    : ($ride->status === 'cancelled'
                        ? 'cancelled'
                        : ($ride->driver_id ? 'booked' : 'dispatch'));

                return [
                    'id'        => $ride->id,
                    'time'      => optional($ride->requested_at)->format('H:i'),
                    'pickup'    => $ride->pickup_latitude . ', ' . $ride->pickup_longitude,
                    'dropoff'   => $ride->dropoff_latitude . ', ' . $ride->dropoff_longitude,
                    'driver'    => $ride->driver->name ?? null,
                    'passenger' => $ride->passenger->name ?? null,
                    'phone'     => $ride->passenger->phone ?? null,
                    'status'    => $ride->status,
                    'queue'     => $queue,
                    'fare'      => (float) $ride->total_fare,
                    'ride_type' => $ride->ride_type,
                ];
            });

        return [
            'driverAvailableCount' => $driverAvailableCount,
            'driverBusyCount'      => $driverBusyCount,
            'rideCounts'           => $rideCounts,
            'rides'                => $rides,
        ];
    }



    public function requestCustomRide(Request $request)
    {
        // Previously any authenticated dashboard user could create/assign a ride
        // through this endpoint regardless of role -- there was no check at all.
        $this->authorize('assign ride');

        Log::info('Request Custom Ride', ['request' => $request->all()]);
        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'driver_id' => 'required|exists:users,id',
            'driver_id_input' => 'nullable|exists:users,id',
            'promo_code_id' => 'nullable|exists:promo_codes,id',
            'pickup_latitude' => 'required|string',
            'pickup_longitude' => 'required|string',
            'dropoff_latitude' => 'required|string',
            'dropoff_longitude' => 'required|string',
            'distance_km' => 'required|string',
            'duration_minutes' => 'nullable|string',
            'subtotal' => 'required|string',
            'discount_amount' => 'required|string',
            'total_fare' => 'required|string',
            'passenger_name' => 'required|string|max:255',
            'passenger_phone' => 'required|string|max:255',
            'passenger_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Request Custom Ride Validation Failed', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        try {
            DB::beginTransaction();
            $driver = User::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'message' => 'Selected driver is not valid.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check existing user by phone (email is optional/no longer collected from
            // the admin-assign form, so only match on it when the caller actually sent one)
            $existingUser = User::where('phone', $request->passenger_phone)
                ->when($request->filled('passenger_email'), function ($query) use ($request) {
                    $query->orWhere('email', $request->passenger_email);
                })
                ->first();

            if ($existingUser) {

                if ($request->filled('passenger_email')) {
                    // Phone belongs to someone else
                    if (
                        $existingUser->phone === $request->passenger_phone &&
                        $existingUser->email !== $request->passenger_email &&
                        User::where('email', $request->passenger_email)->exists()
                    ) {
                        return response()->json([
                            'message' => 'This email is already registered with another phone number.'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    // Email belongs to someone else
                    if (
                        $existingUser->email === $request->passenger_email &&
                        $existingUser->phone !== $request->passenger_phone &&
                        User::where('phone', $request->passenger_phone)->exists()
                    ) {
                        return response()->json([
                            'message' => 'This phone number is already registered with another email.'
                        ], Response::HTTP_BAD_REQUEST);
                    }
                }

                // Same user (phone or email matched)
                $passenger = $existingUser;

            } else {

                $passenger = new User();
                $passenger->name = $request->passenger_name;
                $passenger->phone = $request->passenger_phone;
                // Email column is required+unique on users; the admin-assign form no
                // longer collects one, so fall back to a unique placeholder tied to the phone.
                $passenger->email = $request->filled('passenger_email')
                    ? $request->passenger_email
                    : $this->generatePlaceholderEmail($request->passenger_phone);
                $passenger->password = Hash::make($request->passenger_phone);

                // Generate unique username
                $username = $this->generateUsername($request->passenger_name);
                while (User::where('username', $username)->exists()) {
                    $username = $this->generateUsername($request->passenger_name);
                }

                $passenger->username = $username;
                $passenger->save();
            }

            // Only set the 'user' role on brand-new passengers, or existing accounts that
            // have no role yet. Never touch roles on an existing account matched by phone/email
            // that already has a role (e.g. staff/admin/driver) — syncRoles() here would have
            // silently stripped it down to just 'user'.
            if (!$passenger->roles()->exists()) {
                $passenger->syncRoles('user');
            }

            if($request->driver_id_input){
                $ride = new Ride();
                $ride->passenger_id = $passenger->id;
                // Pre-lock this ride to the driver the admin picked, but keep it in the
                // same 'requested' state normal rides use so it flows through the exact
                // discovery pipeline (Firebase ride_requests node + /driver/get-rides)
                // that the driver app already listens to for incoming ride requests.
                $ride->driver_id = $request->driver_id_input;
                $ride->vehicle_type_id = $request->vehicle_type_id;
                $ride->promo_code_id = $request->promo_code_id;
                $ride->pickup_latitude = $request->pickup_latitude;
                $ride->pickup_longitude = $request->pickup_longitude;
                $ride->dropoff_latitude = $request->dropoff_latitude;
                $ride->dropoff_longitude = $request->dropoff_longitude;
                $ride->distance_km = $request->distance_km;
                $ride->duration_minutes = $request->duration_minutes;
                $ride->subtotal = $request->subtotal;
                $ride->discount_amount = $request->discount_amount;
                $ride->total_fare = $request->total_fare;
                $ride->requested_at = now();
                $ride->status = 'requested';
                $ride->created_by = auth()->id();
                $ride->save();

                try {
                    broadcast(new \App\Events\RideStatusUpdated($ride));
                } catch (\Throwable $e) {
                    Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
                }

                app('notificationService')->notifyUsers(
                    [$driver],
                    'New Ride Assigned',
                    'You have been assigned a new ride by the admin.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );
            }else {
                $ride = new Ride();
                $ride->passenger_id = $passenger->id;
                $ride->driver_id = null; // No driver assigned yet
                $ride->vehicle_type_id = $request->vehicle_type_id;
                $ride->promo_code_id = $request->promo_code_id;
                $ride->pickup_latitude = $request->pickup_latitude;
                $ride->pickup_longitude = $request->pickup_longitude;
                $ride->dropoff_latitude = $request->dropoff_latitude;
                $ride->dropoff_longitude = $request->dropoff_longitude;
                $ride->distance_km = $request->distance_km;
                $ride->duration_minutes = $request->duration_minutes;
                $ride->subtotal = $request->subtotal;
                $ride->discount_amount = $request->discount_amount;
                $ride->total_fare = $request->total_fare;
                $ride->requested_at = now();
                $ride->status = 'requested';
                $ride->created_by = auth()->id();
                $ride->save();
            }


            DB::commit();
            return response()->json([
                'ride_id' => $ride->id,
                'message' => 'Custom Ride requested successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('API Store Custom Ride failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    private function generateUsername($name)
    {
        $name = strtolower(str_replace(' ', '', $name));
        return $name . rand(1000, 9999);
    }

    /**
     * Build a unique placeholder email for passengers created from the admin-assign
     * form, which no longer collects a real email address.
     */
    private function generatePlaceholderEmail($phone)
    {
        $base = preg_replace('/[^0-9]/', '', $phone) ?: 'guest';
        $email = $base . '@guest.urbanx.local';

        while (User::where('email', $email)->exists()) {
            $email = $base . rand(1000, 9999) . '@guest.urbanx.local';
        }

        return $email;
    }
}
