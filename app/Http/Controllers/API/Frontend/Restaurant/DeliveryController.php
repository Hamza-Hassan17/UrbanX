<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\DriverVehicle;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideDriverLog;
use App\Models\RideOffer;
use Illuminate\Http\Request;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class DeliveryController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase->getDatabase();
    }

    public function getHomeData(Request $request)
    {
        try {
            $user = $request->user();

            $today = now()->toDateString();
            $startOfWeek = now()->startOfWeek(); // Monday
            $endOfWeek = now()->endOfWeek();

            // Today Deliveries
            $today_deliveries = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count();

            // Today Earnings
            $today_earning = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->sum('total_fare');

            // This Week Deliveries
            $this_week_deliveries = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->count();

            // This Week Earnings
            $this_week_earning = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->sum('total_fare');

            return response()->json([
                'message' => 'Delivery Rider Home Stats',

                'stats' => [
                    'today_earning' => (float) $today_earning,
                    'today_deliveries' => $today_deliveries,
                    'this_week_earning' => (float) $this_week_earning,
                    'this_week_deliveries' => $this_week_deliveries,
                ]

            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('API Restaurant Delivery Home failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getLatestRides(Request $request)
    {
        try {
            $driver = $request->user();

            // -------------------------
            // Time windows
            // -------------------------
            $tenMinutesAgo   = now()->subMinutes(10);   // ride life
            $logWindowStart  = now()->subHours(10);     // logs window

            // -------------------------
            // Driver vehicle type
            // -------------------------
            $driverVehicleType = DriverVehicle::join('vehicle_types', 'driver_vehicles.vehicle_type_id', '=', 'vehicle_types.id')
                ->where('driver_vehicles.driver_id', $driver->id)
                ->where('vehicle_types.is_delivery', '1')
                ->value('driver_vehicles.vehicle_type_id');

            Log::info('Driver Vehicle Type', ['driver_id' => $driver->id, 'vehicle_type_id' => $driverVehicleType]);

            if (!$driverVehicleType) {
                Log::warning("Driver does not have a valid delivery vehicle type", ['driver_id' => $driver->id]);
                return response()->json(['rides' => []], 200);
            }

            // -------------------------
            // Rides already sent / accepted (last 10 hours)
            // -------------------------
            $busyRideIds = DB::table('ride_driver_logs')
                ->whereIn('action', ['sent', 'accepted'])
                ->where('created_at', '>=', $logWindowStart)
                ->pluck('ride_id');

            Log::info('Busy Ride IDs', ['driver_id' => $driver->id, 'busy_ride_ids' => $busyRideIds]);

            // -------------------------
            // Rides rejected by this driver (last 10 hours)
            // -------------------------
            $rejectedByMe = DB::table('ride_driver_logs')
                ->where('driver_id', $driver->id)
                ->where('action', 'rejected')
                ->where('created_at', '>=', $logWindowStart)
                ->pluck('ride_id');

            Log::info('Rides Rejected by Driver', ['driver_id' => $driver->id, 'rejected_ride_ids' => $rejectedByMe]);

            // -------------------------
            // Fetch nearest single ride
            // -------------------------
            $rides = Ride::selectRaw("
                    rides.*,
                    (6371 * acos(
                        cos(radians(?)) *
                        cos(radians(pickup_latitude)) *
                        cos(radians(pickup_longitude) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(pickup_latitude))
                    )) AS distance
                ", [
                    $driver->lat,
                    $driver->lang,
                    $driver->lat
                ])
                ->where('ride_type', 'delivery')
                ->where('status', 'requested')
                ->whereIn('id', function ($q) {
                    $q->select('ride_id')
                        ->from('restaurant_orders')
                        ->where('status', 'accepted')
                        ->whereNotNull('ride_id');
                })
                ->where('requested_at', '>=', $tenMinutesAgo)
                ->where('vehicle_type_id', $driverVehicleType)
                ->whereNotIn('id', $busyRideIds)
                ->whereNotIn('id', $rejectedByMe)
                ->orderBy('distance')
                ->limit(1)
                ->get();

            // -------------------------
            // Log ride as "sent"
            // -------------------------
            if ($rides->count()) {
                Log::info('Ride Sent to Driver', ['driver_id' => $driver->id, 'ride_id' => $rides[0]->id]);
                RideDriverLog::create([
                    'ride_id'   => $rides[0]->id,
                    'driver_id' => $driver->id,
                    'action'    => 'sent',
                    'note'      => 'Ride sent to nearest driver'
                ]);
            }

            // -------------------------
            // Boost logic
            // -------------------------
            $now = now()->format('H:i');
            $busyHour = DB::table('boost_hours')
                ->where('start', '<=', $now)
                ->where('end', '>=', $now)
                ->first();

            Log::info('Boost Hour Check', ['current_time' => $now, 'busy_hour' => $busyHour]);

            $multiplier = $busyHour ? (float)$busyHour->multiplier : 1.0;

            $rides->transform(function ($ride) use ($multiplier) {
                $ride->boost_multiplier = $multiplier;
                $ride->is_boost = $multiplier > 1;
                $ride->final_fare = isset($ride->total_fare)
                    ? $ride->total_fare * $multiplier
                    : null;
                return $ride;
            });

            Log::info('Rides after Boost Calculation', ['driver_id' => $driver->id, 'rides' => $rides]);

            return response()->json([
                'rides' => $rides
            ], 200);

        } catch (\Throwable $e) {
            Log::error('getLatestRides failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function acceptRide(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'eta_minutes' => 'required|integer|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $ride = Ride::find($request->ride_id);
            if ($ride->status !== 'requested') {
                return response()->json([
                    'message' => 'Ride is no longer available for offer.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create a new ride offer
            $rideOffer = new RideOffer();
            $rideOffer->ride_id = $ride->id;
            $rideOffer->driver_id = auth()->id();
            $rideOffer->proposed_price = $ride->total_fare;
            $rideOffer->eta_minutes = $request->eta_minutes;
            $rideOffer->note = $request->note;
            $rideOffer->offered_at = now();
            $rideOffer->status = 'accepted';
            $rideOffer->save();

            RideDriverLog::updateOrCreate(
                [
                    'ride_id' => $ride->id,
                    'driver_id' => auth()->id(),
                ],
                [
                    'action' => 'sent',
                    'note'   => $request->note ?? 'Delivery offer Accepted by driver',
                ]
            );

            $this->firebase
                ->getReference(
                    'ride_requests/vehicle_type_'.$ride->vehicle_type_id.'/ride_'.$ride->id
                )
                ->remove();

            $this->firebase
                ->getReference(
                    'ride_offers/ride_' . $ride->id . '/offer_' . $rideOffer->id
                )
                ->set([
                    'offer_id' => $rideOffer->id,
                    'ride_id' => $ride->id,
                    'driver_id' => $rideOffer->driver_id,
                    'driver_name' => $rideOffer->driver->name,
                    'driver_email' => $rideOffer->driver->email,
                    'driver_phone' => $rideOffer->driver->phone,
                    'driver_rating' => round($rideOffer->driver->driverReviews()->avg('rating'), 1),
                    'vehicle_type' => $rideOffer->driver->vehicle->type ?? null,
                    'proposed_price' => $rideOffer->proposed_price,
                    'eta_minutes' => $rideOffer->eta_minutes,
                    'note' => $rideOffer->note,
                    'status' => $ride->status,
                    'offered_at' => now()->toDateTimeString(),
                ]);

            $offersRef = $this->firebase
                ->getReference("ride_offers/ride_{$ride->id}")
                ->getValue();

            if ($offersRef) {
                foreach ($offersRef as $key => $offer) {
                    if ($key !== 'offer_'.$rideOffer->id) {
                        $this->firebase
                            ->getReference("ride_offers/ride_{$ride->id}/{$key}")
                            ->remove();
                    }
                }
            }


            // Update RestaurantOrder status to rider_assigned
            $restaurantOrder = RestaurantOrder::where('ride_id', $ride->id)->first();
            if ($restaurantOrder) {
                $restaurantOrder->status = 'rider_assigned';
                $restaurantOrder->save();

                $rider = $rideOffer->driver;
                $this->firebase
                    ->getReference('restaurant_orders/' . $restaurantOrder->id)
                    ->update([
                        'status'       => 'rider_assigned',
                        'rider_id'     => $rider->id,
                        'rider_name'   => $rider->name,
                        'rider_phone'  => $rider->phone,
                        'rider_rating' => round($rider->driverReviews()->avg('rating'), 1),
                        'updated_at'   => now()->toDateTimeString(),
                    ]);
            }

            $passenger = $ride->passenger;
            app('notificationService')->notifyUsers(
                [$passenger],
                'New Delivery Ride Offer',
                'A driver has offered a delivery ride for your request.',
                'ride_offers',
                $rideOffer->id,
                'ride_offer_details'
            );

            return response()->json([
                'message' => 'Ride offered successfully.',
                'order_id' => $restaurantOrder->id ?? null,
                'ride' => $ride,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Offer to Ride failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rejectRide(Request $request)
    {
        $rideDriverLog = RideDriverLog::where('ride_id', $request->ride_id)
            ->where('driver_id', auth()->id())
            ->first();

        if (!$rideDriverLog) {
            return response()->json([
                'message' => 'Ride offer not found or not accessible.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Mark offer rejected
        $rideDriverLog->action = 'rejected';
        $rideDriverLog->note = 'Driver rejected the delivery ride offer';
        $rideDriverLog->save();

        return response()->json([
            'message' => 'Offer rejected successfully'
        ]);
    }

    public function updateDeliveryStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:restaurant_orders,id',
            'status'   => 'required|in:picked_up,on_the_way,delivered',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $order = RestaurantOrder::find($request->order_id);

            $hasOffer = RideOffer::where('ride_id', $order->ride_id)
                ->where('driver_id', auth()->id())
                ->where('status', 'accepted')
                ->exists();

            if (!$hasOffer) {
                return response()->json([
                    'message' => 'You are not assigned to this delivery.'
                ], Response::HTTP_FORBIDDEN);
            }

            $order->status = $request->status;
            $order->save();

            $this->firebase
                ->getReference('restaurant_orders/' . $order->id)
                ->update([
                    'status'     => $request->status,
                    'updated_at' => now()->toDateTimeString(),
                ]);

            return response()->json([
                'message' => 'Delivery status updated.',
                'order'   => $order,
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('updateDeliveryStatus failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateRiderLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id'  => 'required|exists:restaurant_orders,id',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $order = RestaurantOrder::find($request->order_id);

            $hasOffer = RideOffer::where('ride_id', $order->ride_id)
                ->where('driver_id', auth()->id())
                ->where('status', 'accepted')
                ->exists();

            if (!$hasOffer) {
                return response()->json([
                    'message' => 'You are not assigned to this delivery.'
                ], Response::HTTP_FORBIDDEN);
            }

            $this->firebase
                ->getReference('restaurant_orders/' . $request->order_id . '/rider_location')
                ->set([
                    'latitude'   => (float) $request->latitude,
                    'longitude'  => (float) $request->longitude,
                    'updated_at' => now()->toDateTimeString(),
                ]);

            return response()->json([
                'message' => 'Location updated.'
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('updateRiderLocation failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
