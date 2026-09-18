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

            // TEMP: verification gate disabled 2026-09-19, see notifyNearbyDrivers()
            // for context -- re-enable before shipping.
            // if ($driver->driverVerification?->status !== 'approved') {
            //     return response()->json(['rides' => []], 200);
            // }

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
            // Ride pre-assigned directly to this driver (e.g. admin "Assign Trip")
            // bypasses proximity/vehicle-type/log filters below — it's already
            // locked to this driver regardless of distance or vehicle match.
            // -------------------------
            $assignedRide = Ride::where('status', 'requested')
                ->where('driver_id', $driver->id)
                ->latest('requested_at')
                ->first();

            if ($assignedRide) {
                $assignedRide->boost_multiplier = 1.0;
                $assignedRide->is_boost = false;
                $assignedRide->final_fare = $assignedRide->total_fare;

                return response()->json([
                    'rides' => collect([$assignedRide]),
                ], 200);
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

    public function getCurrentRide(Request $request)
    {
        try {
            $driver = $request->user();

            $ride = Ride::where('driver_id', $driver->id)
                ->whereIn('status', ['accepted', 'en_route', 'arrived', 'started'])
                ->latest()
                ->first();

            if (!$ride) {
                return response()->json([
                    'ride' => null,
                ], Response::HTTP_OK);
            }

            return response()->json([
                'ride' => $ride,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Current Ride failed', ['error' => $th->getMessage()]);
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

            // Ride was pre-locked to this driver by the admin (Custom Ride "Assign Trip").
            // There is no passenger on the other end to accept a counter-offer, so accepting
            // here finalizes the ride immediately instead of creating a pending offer.
            if ($ride->driver_id && (int) $ride->driver_id === (int) auth()->id()) {
                DB::beginTransaction();

                $rideOffer = new RideOffer();
                $rideOffer->ride_id = $ride->id;
                $rideOffer->driver_id = auth()->id();
                $rideOffer->proposed_price = $request->proposed_price;
                $rideOffer->eta_minutes = $request->eta_minutes;
                $rideOffer->note = $request->note ?? 'Auto Accepted (Pre-Assigned by Admin)';
                $rideOffer->offered_at = now();
                $rideOffer->accepted_at = now();
                $rideOffer->status = 'accepted';
                $rideOffer->save();

                $ride->total_fare = $rideOffer->proposed_price;
                $ride->status = 'accepted';
                $ride->accepted_at = now();
                $ride->save();

                try {
                    broadcast(new \App\Events\RideStatusUpdated($ride));
                } catch (\Throwable $e) {
                    Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
                }

                try {
                    broadcast(new \App\Events\RideOfferCreated($rideOffer));
                    broadcast(new \App\Events\RideOfferStatusUpdated($rideOffer));
                } catch (\Throwable $e) {
                    Log::error('RideOffer broadcast failed', ['offer_id' => $rideOffer->id, 'error' => $e->getMessage()]);
                }

                DB::commit();

                return response()->json([
                    'message' => 'Ride accepted successfully.',
                    'ride' => $ride,
                ], Response::HTTP_OK);
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

            try {
                broadcast(new \App\Events\RideOfferCreated($rideOffer));
            } catch (\Throwable $e) {
                Log::error('RideOfferCreated broadcast failed', ['offer_id' => $rideOffer->id, 'error' => $e->getMessage()]);
            }


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

    /**
     * Fixed-price direct-accept: the app is Uber-style, not a bidding
     * marketplace, so there is no proposed_price/counter-offer step here --
     * the driver simply claims the ride at the fare already shown to the
     * passenger at request time. Locks the row so that if two drivers who
     * both got the NewRideRequested push tap Accept at the same moment,
     * only the first commits and the second gets a clean "no longer
     * available" instead of silently overwriting the assignment.
     */
    public function acceptRide(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        // TEMP: verification gate disabled 2026-09-19, see notifyNearbyDrivers()
        // for context -- re-enable before shipping.
        // if (auth()->user()->driverVerification?->status !== 'approved') {
        //     return response()->json([
        //         'message' => 'Your account is not verified yet.'
        //     ], Response::HTTP_FORBIDDEN);
        // }

        DB::beginTransaction();

        try {
            $ride = Ride::where('id', $request->ride_id)->lockForUpdate()->first();

            if (!$ride || $ride->status !== 'requested') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Ride is no longer available.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $rideOffer = new RideOffer();
            $rideOffer->ride_id = $ride->id;
            $rideOffer->driver_id = auth()->id();
            $rideOffer->proposed_price = $ride->total_fare;
            $rideOffer->eta_minutes = 0;
            $rideOffer->note = 'Direct accept (fixed price)';
            $rideOffer->offered_at = now();
            $rideOffer->accepted_at = now();
            $rideOffer->status = 'accepted';
            $rideOffer->save();

            $ride->driver_id = auth()->id();
            $ride->status = 'accepted';
            $ride->accepted_at = now();
            $ride->status_updated_by = auth()->id();
            $ride->status_updated_by_role = 'driver';
            $ride->save();

            RideDriverLog::updateOrCreate(
                [
                    'ride_id' => $ride->id,
                    'driver_id' => auth()->id(),
                ],
                [
                    'action' => 'accepted',
                    'note'   => 'Ride accepted by driver',
                ]
            );

            DB::commit();

            try {
                broadcast(new \App\Events\RideStatusUpdated($ride));
                broadcast(new \App\Events\RideOfferCreated($rideOffer));
                broadcast(new \App\Events\RideOfferStatusUpdated($rideOffer));
            } catch (\Throwable $e) {
                Log::error('Ride accept broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
            }

            $this->notifyRideNoLongerAvailable($ride, auth()->id());

            $passenger = $ride->passenger;
            app('notificationService')->notifyUsers(
                [$passenger],
                'Driver Assigned',
                'A driver has accepted your ride request.',
                'rides',
                $ride->id,
                'ride_details'
            );

            return response()->json([
                'message' => 'Ride accepted successfully.',
                'ride' => $ride,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('API Accept Ride failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Tells the other drivers who received NewRideRequested for this ride
     * (same vehicle-type + 5km eligibility query used to send it) that it's
     * been claimed, so their popups dismiss. Best-effort: failure here must
     * never undo the accept that already committed.
     */
    private function notifyRideNoLongerAvailable(Ride $ride, int $acceptedByDriverId): void
    {
        try {
            $radiusKm = 5;

            // TEMP: verification gate disabled 2026-09-19, see
            // Customer\RideController::notifyNearbyDrivers() for context --
            // re-enable before shipping.
            $driverIds = DriverVehicle::where('vehicle_type_id', $ride->vehicle_type_id)
                ->join('users', 'users.id', '=', 'driver_vehicles.driver_id')
                // ->join('driver_verifications', function ($join) {
                //     $join->on('driver_verifications.driver_id', '=', 'driver_vehicles.driver_id')
                //         ->where('driver_verifications.status', '=', 'approved');
                // })
                ->whereNotNull('users.lat')
                ->whereNotNull('users.lang')
                ->where('driver_vehicles.driver_id', '!=', $acceptedByDriverId)
                ->selectRaw("
                    driver_vehicles.driver_id,
                    (6371 * acos(
                        cos(radians(?)) *
                        cos(radians(users.lat)) *
                        cos(radians(users.lang) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(users.lat))
                    )) AS distance
                ", [
                    $ride->pickup_latitude,
                    $ride->pickup_longitude,
                    $ride->pickup_latitude,
                ])
                ->havingRaw('distance <= ?', [$radiusKm])
                ->pluck('driver_id');

            foreach ($driverIds as $driverId) {
                broadcast(new \App\Events\RideNoLongerAvailable($ride->id, (int) $driverId));
            }
        } catch (\Throwable $e) {
            Log::error('RideNoLongerAvailable broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
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

            $ride->status_updated_by = auth()->id();
            $ride->status_updated_by_role = 'driver';

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

            // Update status so the passenger can see live
            try {
                broadcast(new \App\Events\RideStatusUpdated($ride));
            } catch (\Throwable $e) {
                Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
            }


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

    // public function getRideHistory(Request $request)
    // {
    //     try {
    //         $driverId = $request->user()->id;

    //         $rides = Ride::where('driver_id', $driverId)
    //             ->whereIn('status', ['completed', 'cancelled'])
    //             ->orderBy('created_at', 'desc')
    //             ->get();

    //         return response()->json([
    //             'ride_history' => $rides,
    //         ], Response::HTTP_OK);
    //     } catch (\Throwable $th) {
    //         Log::error('API Get Ride History failed', ['error' => $th->getMessage()]);
    //         return response()->json([
    //             'message' => 'Something went wrong!'
    //         ], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }

    public function getRideHistory(Request $request)
    {
        try {
            $user = $request->user();
            $driverId = $user->id;

            // 🔥 role check
            $rideType = $user->hasRole('driver') ? 'ride' : 'delivery';

            $rides = Ride::where('driver_id', $driverId)
                ->where('ride_type', $rideType)
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

    /**
     * Continuous GPS ping while a taxi ride is active -- previously the only
     * write to users.lat/lang happened at login/registration, so a taxi
     * driver's position on the admin's live-tracking map was a one-time
     * snapshot, not a real trail. This is the taxi-side counterpart to
     * DeliveryController::updateRiderLocation(), and feeds
     * RideAnomalyDetector for wrong-direction / stale-GPS flags. The app
     * should call this every ~15-30s while status is en_route/started.
     */
    public function pingLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $driver = $request->user();
            $ride = Ride::find($request->ride_id);

            if ((int) $ride->driver_id !== (int) $driver->id) {
                return response()->json([
                    'message' => 'You are not assigned to this ride.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Keep users.lat/lang current too, since other code (nearby-driver
            // matching, dispatch map's "last known" fallback) already reads it.
            $driver->lat = $request->latitude;
            $driver->lang = $request->longitude;
            $driver->save();

            app(\App\Services\RideAnomalyDetector::class)->recordPing(
                $ride,
                $driver,
                (float) $request->latitude,
                (float) $request->longitude
            );

            return response()->json([
                'message' => 'Location updated successfully.',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Ping Location failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
