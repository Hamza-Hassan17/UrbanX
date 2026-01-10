<?php

namespace App\Http\Controllers\API\Frontend\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverVehicle;
use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RideController extends Controller
{
    // public function getLatestRides(Request $request)
    // {
    //     try {
    //         $tenMinutesAgo = now()->subMinutes(10);

    //         $driverVehicleType = DriverVehicle::where('driver_id', auth()->id())
    //             ->value('vehicle_type_id');

    //         if (!$driverVehicleType) {
    //             return response()->json([
    //                 'message' => 'Driver vehicle not found.'
    //             ], Response::HTTP_BAD_REQUEST);
    //         }

    //         $offeredRideIds = RideOffer::where('driver_id', auth()->id())
    //             ->pluck('ride_id');

    //         $rides = Ride::where('status', 'requested')
    //             ->where('requested_at', '>=', $tenMinutesAgo)
    //             ->where('vehicle_type_id', $driverVehicleType)
    //             ->whereNotIn('id', $offeredRideIds)
    //             ->orderBy('requested_at', 'desc')
    //             ->get();

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
            $tenMinutesAgo = now()->subMinutes(10);

            $driverVehicleType = DriverVehicle::where('driver_id', auth()->id())
                ->value('vehicle_type_id');

            if (!$driverVehicleType) {
                return response()->json([
                    'message' => 'Driver vehicle not found.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $offeredRideIds = RideOffer::where('driver_id', auth()->id())
                ->pluck('ride_id');

            // Fetch rides
            $rides = Ride::where('status', 'requested')
                ->where('requested_at', '>=', $tenMinutesAgo)
                ->where('vehicle_type_id', $driverVehicleType)
                ->whereNotIn('id', $offeredRideIds)
                ->orderBy('requested_at', 'desc')
                ->get();

            // Get current busy hour multiplier
            $now = now()->format('H:i');
            $busyHour = DB::table('boost_hours')
                ->where('start', '<=', $now)
                ->where('end', '>=', $now)
                ->first();

            $multiplier = $busyHour ? (float) $busyHour->multiplier : 1.0;
            $isBoost = $multiplier > 1 ? true : false;

            // Append boost info to each ride
            $rides->transform(function ($ride) use ($multiplier, $isBoost) {
                $ride->boost_multiplier = $multiplier;
                $ride->is_boost = $isBoost;

                // Optional: calculate fare with multiplier if total_fare exists
                if (isset($ride->total_fare)) {
                    $ride->final_fare = $ride->total_fare * $multiplier;
                }

                return $ride;
            });

            return response()->json([
                'rides' => $rides,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Rides failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            if($request->status === 'en_route'){
                $title = 'Driver En Route';
                $message = "Your driver is en route to the pickup location.";
            } elseif($request->status === 'arrived'){
                $title = 'Driver Arrived';
                $message = "Your driver has arrived at the pickup location.";
            } elseif($request->status === 'started'){
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
