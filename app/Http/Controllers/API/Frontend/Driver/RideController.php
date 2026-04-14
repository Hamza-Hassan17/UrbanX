<?php

namespace App\Http\Controllers\API\Frontend\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverVehicle;
use App\Models\Ride;
use App\Models\RideDriverLog;
use App\Models\RideOffer;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RideController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase->getDatabase();
    }

    // public function getLatestRides(Request $request)
    // {
    //     try {
    //         $driver = $request->user();
    //         // if ($driver->driver_status !== 'available') {
    //         //     return response()->json([
    //         //         'rides' => [],
    //         //         'message' => 'Driver is currently busy'
    //         //     ], Response::HTTP_OK);
    //         // }

    //         $tenMinutesAgo = now()->subMinutes(10);

    //         $driverVehicleType = DriverVehicle::where('driver_id', $driver->id)
    //             ->value('vehicle_type_id');

    //         if (!$driverVehicleType) {
    //             return response()->json([
    //                 'message' => 'Driver vehicle not found.'
    //             ], Response::HTTP_BAD_REQUEST);
    //         }

    //         $offeredRideIds = RideOffer::where('driver_id', $driver->id)
    //             ->pluck('ride_id');

    //         // Fetch rides
    //         $rides = Ride::where('status', 'requested')
    //             ->where('requested_at', '>=', $tenMinutesAgo)
    //             ->where('vehicle_type_id', $driverVehicleType)
    //             ->whereNotIn('id', $offeredRideIds)
    //             ->orderBy('requested_at', 'desc')
    //             ->get();

    //         // Get current busy hour multiplier
    //         $now = now()->format('H:i');
    //         $busyHour = DB::table('boost_hours')
    //             ->where('start', '<=', $now)
    //             ->where('end', '>=', $now)
    //             ->first();

    //         $multiplier = $busyHour ? (float) $busyHour->multiplier : 1.0;
    //         $isBoost = $multiplier > 1 ? true : false;

    //         // Append boost info to each ride
    //         $rides->transform(function ($ride) use ($multiplier, $isBoost) {
    //             $ride->boost_multiplier = $multiplier;
    //             $ride->is_boost = $isBoost;

    //             // Optional: calculate fare with multiplier if total_fare exists
    //             if (isset($ride->total_fare)) {
    //                 $ride->final_fare = $ride->total_fare * $multiplier;
    //             }

    //             return $ride;
    //         });

    //         return response()->json([
    //             'rides' => $rides,
    //         ], Response::HTTP_OK);
    //     } catch (\Throwable $th) {
    //         Log::error('API Get Rides failed', ['error' => $th->getMessage()]);
    //         return response()->json([
    //             'message' => 'Something went wrong!'
    //         ], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }

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
            $driverVehicleType = DriverVehicle::where('driver_id', $driver->id)
                ->value('vehicle_type_id');

            if (!$driverVehicleType) {
                return response()->json(['rides' => []], 200);
            }

            // -------------------------
            // Rides already sent / accepted (last 10 hours)
            // -------------------------
            $busyRideIds = DB::table('ride_driver_logs')
                ->whereIn('action', ['sent', 'accepted'])
                ->where('created_at', '>=', $logWindowStart)
                ->pluck('ride_id');

            // -------------------------
            // Rides rejected by this driver (last 10 hours)
            // -------------------------
            $rejectedByMe = DB::table('ride_driver_logs')
                ->where('driver_id', $driver->id)
                ->where('action', 'rejected')
                ->where('created_at', '>=', $logWindowStart)
                ->pluck('ride_id');

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
                ->where('status', 'requested')
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

            $multiplier = $busyHour ? (float)$busyHour->multiplier : 1.0;

            $rides->transform(function ($ride) use ($multiplier) {
                $ride->boost_multiplier = $multiplier;
                $ride->is_boost = $multiplier > 1;
                $ride->final_fare = isset($ride->total_fare)
                    ? $ride->total_fare * $multiplier
                    : null;
                return $ride;
            });

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

    public function getSingleRideDetails($ride_id)
    {
        try {
            $ride = Ride::where('id', $ride_id)
                ->where('status', 'requested')
                ->first();

            if (!$ride) {
                return response()->json([
                    'message' => 'Ride not found or no longer available.'
                ], Response::HTTP_NOT_FOUND);
            }

            // Get current busy hour multiplier
            $now = now()->format('H:i');
            $busyHour = DB::table('boost_hours')
                ->where('start', '<=', $now)
                ->where('end', '>=', $now)
                ->first();

            $multiplier = $busyHour ? (float) $busyHour->multiplier : 1.0;
            $isBoost = $multiplier > 1 ? true : false;

            // Append boost info to the ride
            $ride->boost_multiplier = $multiplier;
            $ride->is_boost = $isBoost;

            // Optional: calculate final fare if total_fare exists
            if (isset($ride->total_fare)) {
                $ride->final_fare = $ride->total_fare * $multiplier;
            }

            return response()->json([
                'ride' => $ride,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Single Ride Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getRideDetails($ride_id)
    {
        try {
            $user = request()->user();
            $ride = Ride::where('id', $ride_id)
                ->first();

            if (!$ride) {
                return response()->json([
                    'message' => 'Ride not found or no longer available.'
                ], Response::HTTP_NOT_FOUND);
            }
            // if ($ride->driver_id != $user->id) {
            //     return response()->json([
            //         'message' => 'You are not assigned to this ride.'
            //     ], Response::HTTP_NOT_FOUND);
            // }

            $isRideAccepted = $ride->status == 'accepted' ? true : false;

            return response()->json([
                'ride' => $ride,
                'is_ride_accepted' => $isRideAccepted,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Ride Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function OfferToRide(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'proposed_price' => 'required|numeric|min:0',
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
            $rideOffer->proposed_price = $request->proposed_price;
            $rideOffer->eta_minutes = $request->eta_minutes;
            $rideOffer->note = $request->note;
            $rideOffer->offered_at = now();
            $rideOffer->status = 'pending';
            $rideOffer->save();

            RideDriverLog::updateOrCreate(
                [
                    'ride_id' => $ride->id,
                    'driver_id' => auth()->id(),
                ],
                [
                    'action' => 'sent',
                    'note'   => $request->note ?? 'Ride offer Pending by driver',
                ]
            );

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
                    // 'status' => 'pending',
                    'offered_at' => now()->toDateTimeString(),
                ]);


            $passenger = $ride->passenger;
            app('notificationService')->notifyUsers(
                [$passenger],
                'New Ride Offer',
                'A driver has offered a ride for your request.',
                'ride_offers',
                $rideOffer->id,
                'ride_offer_details'
            );

            return response()->json([
                'message' => 'Ride offered successfully.',
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
        $rideDriverLog->note = 'Driver rejected the ride offer';
        $rideDriverLog->save();

        return response()->json([
            'message' => 'Offer rejected successfully'
        ]);
    }

    public function updateRideStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'status' => 'required|in:en_route,arrived,started,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $ride = Ride::find($request->ride_id);

            // if ($ride->driver_id !== auth()->id()) {
            //     return response()->json([
            //         'message' => 'You are not assigned to this ride.'
            //     ], Response::HTTP_FORBIDDEN);
            // }

            $ride->status = $request->status;

            if ($request->status === 'started') {
                $ride->started_at = now();
            } elseif ($request->status === 'completed') {
                $ride->completed_at = now();
            }

            $ride->save();
            $passenger = $ride->passenger;
            if ($request->status === 'en_route') {
                $title = 'Driver En Route';
                $message = "Your driver is en route to the pickup location.";
            } elseif ($request->status === 'arrived') {
                $title = 'Driver Arrived';
                $message = "Your driver has arrived at the pickup location.";
            } elseif ($request->status === 'started') {
                $title = 'Ride Started';
                $message = "Your ride has started.";
            } else {
                $title = 'Ride Completed';
                $message = "Your ride has been completed.";
            }
            app('notificationService')->notifyUsers(
                [$passenger],
                $title,
                $message,
                'rides',
                $ride->id,
                'ride_details'
            );

            // 🔥 Update status in Firebase so passenger can see live
            $this->firebase
                ->getReference('ride_requests/vehicle_type_'.$ride->vehicle_type_id.'/ride_'.$ride->id)
                ->update([
                    'status' => $ride->status,
                    'started_at' => $ride->started_at ? \Carbon\Carbon::parse($ride->started_at)->toDateTimeString() : null,
                    'completed_at' => $ride->completed_at ? \Carbon\Carbon::parse($ride->completed_at)->toDateTimeString() : null,
                    'updated_at' => now()->toDateTimeString(),
                ]);


            return response()->json([
                'message' => 'Ride status updated successfully.',
                'ride' => $ride,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Ride Status failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getRideHistory(Request $request)
    {
        try {
            $driverId = $request->user()->id;

            $rides = Ride::where('driver_id', $driverId)
                ->whereIn('status', ['completed', 'cancelled'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'ride_history' => $rides,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Ride History failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
