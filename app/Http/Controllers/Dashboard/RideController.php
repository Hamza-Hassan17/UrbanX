<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BoostHour;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideDriverLog;
use App\Models\RideExtraCharge;
use App\Models\RideOffer;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RideController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase->getDatabase();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view ride');
        try {
            $rides = Ride::with('passenger:id,name','driver:id,name')->latest()->get();
            return view('dashboard.rides.index',compact('rides'));
        } catch (\Throwable $th) {
            Log::error('Rides Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $this->authorize('view ride');
        try {
            $ride = Ride::with('passenger','driver','vehicleType','promoCode')->findOrFail($id);
            $rideOffers = RideOffer::where('ride_id', $id)->with('driver:id,name,phone')->get();
            $rideExtraCharges = RideExtraCharge::where('ride_id', $id)->get();

            // Check for boost hour
            $requestedTime = \Carbon\Carbon::parse($ride->requested_at)->format('H:i:s');
            $boostHour = BoostHour::where('start', '<=', $requestedTime)
                ->where('end', '>=', $requestedTime)
                ->first();

            return view('dashboard.rides.show',compact('ride','rideOffers','rideExtraCharges','boostHour'));
        } catch (\Throwable $th) {
            Log::error('Ride Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update ride');

        // Assigning a driver is a distinct action from editing status/pickup/
        // dropoff -- checked here (before the transaction below starts) so an
        // AuthorizationException surfaces as a real 403 instead of getting
        // caught by the generic \Throwable handler further down and masked
        // as "Something went wrong".
        if ($request->filled('driver_id')) {
            $this->authorize('assign ride');
        }

        $wantsJson = $request->wantsJson();

        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'status' => 'required|in:requested,accepted,en_route,arrived,started,completed,cancelled',
            'pickup_latitude' => 'nullable|string',
            'pickup_longitude' => 'nullable|string',
            'dropoff_latitude' => 'nullable|string',
            'dropoff_longitude' => 'nullable|string',
            'driver_id' => 'nullable|exists:users,id',
            'eta_minutes' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'message' => $validator->errors()->first() ?: 'Validation Error!',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();

            $ride = Ride::findOrFail($id);
            $ride->status = $request->status;

            // Super admin can also correct pickup/dropoff on a dispatch/booked ride.
            // Only touch these when both lat+lng arrive together, so a partial
            // payload can never leave one coordinate stale against the other.
            if ($request->filled('pickup_latitude') && $request->filled('pickup_longitude')) {
                $ride->pickup_latitude = $request->pickup_latitude;
                $ride->pickup_longitude = $request->pickup_longitude;
            }
            if ($request->filled('dropoff_latitude') && $request->filled('dropoff_longitude')) {
                $ride->dropoff_latitude = $request->dropoff_latitude;
                $ride->dropoff_longitude = $request->dropoff_longitude;
            }

            $assignedDriver = null;
            $assignedRestaurantOrder = null;
            if ($request->filled('driver_id')) {
                if ($ride->driver_id) {
                    DB::rollBack();
                    $message = 'This ride already has a driver assigned.';
                    if ($wantsJson) {
                        return response()->json(['message' => $message], 422);
                    }
                    return redirect()->back()->with('error', $message);
                }

                // For a delivery job, "claimed" is gated by restaurant_orders.status
                // (accepted -> rider_assigned), not by rides.driver_id -- confirmed
                // against DeliveryController::acceptRide()/getLatestRides(), which
                // never touch/filter on driver_id at all for delivery. So an
                // unclaimed delivery job must still have its linked order sitting
                // at 'accepted'.
                if ($ride->ride_type !== 'ride') {
                    $assignedRestaurantOrder = RestaurantOrder::where('ride_id', $ride->id)->first();
                    if (!$assignedRestaurantOrder || $assignedRestaurantOrder->status !== 'accepted') {
                        DB::rollBack();
                        $message = 'This delivery job is no longer available to assign.';
                        if ($wantsJson) {
                            return response()->json(['message' => $message], 422);
                        }
                        return redirect()->back()->with('error', $message);
                    }
                }

                $assignedDriver = User::find($request->driver_id);
                $ride->driver_id = $assignedDriver->id;
                // Operator-traceability audit field (RBAC brief Task 4) -- this
                // ride was organically requested by a customer, not created by an
                // operator, so created_by would otherwise stay null forever even
                // though a dispatcher/super-admin just made the actual assignment
                // decision from the dispatch queue.
                $ride->created_by = auth()->id();
            }

            if ($request->status == 'accepted') {
                $ride->accepted_at = now();
            } elseif ($request->status == 'started') {
                $ride->started_at = now();
            } elseif ($request->status == 'completed') {
                $ride->completed_at = now();
            } elseif ($request->status == 'cancelled') {
                $ride->cancelled_at = now();
            }

            $ride->status_updated_by = auth()->id();
            $ride->status_updated_by_role = 'admin';

            $ride->save();

            if ($assignedDriver && $ride->ride_type === 'ride') {
                // Same discovery pipeline the driver app already listens to, mirroring
                // CustomRideController::requestCustomRide()'s admin-assign branch.
                $this->firebase
                    ->getReference('ride_requests/vehicle_type_' . $ride->vehicle_type_id . '/ride_' . $ride->id)
                    ->set([
                        'ride_id' => $ride->id,
                        'passenger_id' => $ride->passenger_id,
                        'driver_id' => $ride->driver_id,
                        'vehicle_type_id' => $ride->vehicle_type_id,
                        'pickup_latitude' => $ride->pickup_latitude,
                        'pickup_longitude' => $ride->pickup_longitude,
                        'dropoff_latitude' => $ride->dropoff_latitude,
                        'dropoff_longitude' => $ride->dropoff_longitude,
                        'distance_km' => $ride->distance_km,
                        'duration_minutes' => $ride->duration_minutes,
                        'subtotal' => $ride->subtotal,
                        'discount_amount' => $ride->discount_amount,
                        'total_fare' => $ride->total_fare,
                        'status' => $ride->status,
                        'ride_type' => $ride->ride_type,
                        'requested_at' => optional($ride->requested_at)->toDateTimeString(),
                    ]);

                app('notificationService')->notifyUsers(
                    [$assignedDriver],
                    'New Ride Assigned',
                    'You have been assigned a new ride by the admin.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );
            } elseif ($assignedDriver && $ride->ride_type !== 'ride' && $assignedRestaurantOrder) {
                // Mirrors DeliveryController::acceptRide()'s full side-effect set as
                // closely as possible, since that's the only proven-working path for
                // a rider claiming a delivery job -- just triggered by admin instead
                // of the rider self-claiming.
                $rideOffer = new RideOffer();
                $rideOffer->ride_id = $ride->id;
                $rideOffer->driver_id = $assignedDriver->id;
                $rideOffer->proposed_price = $ride->total_fare;
                // No ETA input on the admin assignment form -- default to 15 minutes
                // when not explicitly provided.
                $rideOffer->eta_minutes = $request->input('eta_minutes', 15);
                $rideOffer->note = 'Assigned by admin';
                $rideOffer->offered_at = now();
                $rideOffer->status = 'accepted';
                $rideOffer->save();

                RideDriverLog::updateOrCreate(
                    ['ride_id' => $ride->id, 'driver_id' => $assignedDriver->id],
                    ['action' => 'sent', 'note' => 'Delivery assigned by admin']
                );

                $this->firebase
                    ->getReference('ride_requests/vehicle_type_' . $ride->vehicle_type_id . '/ride_' . $ride->id)
                    ->remove();

                $this->firebase
                    ->getReference('ride_offers/ride_' . $ride->id . '/offer_' . $rideOffer->id)
                    ->set([
                        'offer_id' => $rideOffer->id,
                        'ride_id' => $ride->id,
                        'driver_id' => $rideOffer->driver_id,
                        'driver_name' => $assignedDriver->name,
                        'driver_email' => $assignedDriver->email,
                        'driver_phone' => $assignedDriver->phone,
                        'driver_rating' => round($assignedDriver->driverReviews()->avg('rating'), 1),
                        'vehicle_type' => $assignedDriver->vehicle->type ?? null,
                        'proposed_price' => $rideOffer->proposed_price,
                        'eta_minutes' => $rideOffer->eta_minutes,
                        'note' => $rideOffer->note,
                        'status' => $ride->status,
                        'offered_at' => now()->toDateTimeString(),
                    ]);

                $assignedRestaurantOrder->status = 'rider_assigned';
                $assignedRestaurantOrder->save();

                $this->firebase
                    ->getReference('restaurant_orders/' . $assignedRestaurantOrder->id)
                    ->update([
                        'status' => 'rider_assigned',
                        'rider_id' => $assignedDriver->id,
                        'rider_name' => $assignedDriver->name,
                        'rider_phone' => $assignedDriver->phone,
                        'rider_rating' => round($assignedDriver->driverReviews()->avg('rating'), 1),
                        'updated_at' => now()->toDateTimeString(),
                    ]);

                // acceptRide() doesn't need this -- the rider already knows they just
                // claimed it. Here, the rider has no way to find out otherwise: the
                // proven-working discovery path (DeliveryController::getLatestRides)
                // only surfaces still-unclaimed jobs by design, so once
                // restaurant_orders.status flips away from 'accepted' above, this
                // rider's own next poll would come back empty rather than showing
                // this job as theirs. A push notification is the one channel I can
                // confirm actually reaches their device regardless of that -- but
                // whether their app has any *screen* that then shows this as an
                // active/assigned delivery is unverified. Flagging this rather than
                // assuming it's fully wired end-to-end.
                app('notificationService')->notifyUsers(
                    [$assignedDriver],
                    'New Delivery Assigned',
                    'You have been assigned a delivery by the admin.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );

                $customer = $assignedRestaurantOrder->customer;
                if ($customer) {
                    app('notificationService')->notifyUsers(
                        [$customer],
                        'New Delivery Ride Offer',
                        'A driver has been assigned to your delivery order.',
                        'ride_offers',
                        $rideOffer->id,
                        'ride_offer_details'
                    );
                }
            }

            DB::commit();

            if ($wantsJson) {
                return response()->json(['message' => 'Ride status updated successfully'], 200);
            }
            return redirect()->route('dashboard.rides.index')->with('success', 'Ride status Updated successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Ride status update Failed', ['error' => $th->getMessage()]);

            if ($wantsJson) {
                return response()->json(['message' => 'Something went wrong! Please try again later'], 500);
            }
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete ride');

        try {
            $ride = Ride::findOrFail($id);
            $ride->delete();

            return redirect()->route('dashboard.rides.index')->with('success', 'Ride deleted successfully');
        } catch (\Throwable $th) {
            Log::error('Ride Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }
}
