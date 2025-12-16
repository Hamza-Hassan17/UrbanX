<?php

namespace App\Http\Controllers\API\Frontend\Customer;

use App\Http\Controllers\Controller;
use App\Models\DriverReview;
use App\Models\PromoCode;
use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RideController extends Controller
{
    public function calculateDistanceFare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'distance_km' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Fetch vehicle type pricing details
            $vehicleType = VehicleType::find($request->vehicle_type_id);
            if (!$vehicleType) {
                $baseFare = 5.00;
            }else{
                $baseFare = $vehicleType->base_fare;
            }

            // Calculate fare components
            $totalFare = $request->distance_km * $baseFare;

            return response()->json([
                'total_fare' => $totalFare,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Calculate Ride Fare failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function promoCodeApply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'promo_code' => 'required|string|exists:promo_codes,code',
            'total_fare' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $promoCode = PromoCode::where('code', $request->promo_code)
                ->where('is_active', 'active')
                ->where('valid_from', '<=', now())
                ->where('valid_until', '>=', now())
                ->first();

            if (!$promoCode) {
                return response()->json([
                    'message' => 'Invalid or expired promo code.'
                ], Response::HTTP_BAD_REQUEST);
            }
            // CHECK PER RIDE LIMIT
            $rideUsedCount = Ride::where('promo_code_id', $promoCode->id)->count();
            if ($rideUsedCount >= $promoCode->usage_limit) {
                return response()->json([
                    'message' => 'Promo code usage limit reached for this ride.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // CHECK PER USER LIMIT
            $userUsedCount = Ride::where('passenger_id', auth()->id())
                ->where('promo_code_id', $promoCode->id)
                ->count();

            if ($userUsedCount >= $promoCode->usage_limit_per_user) {
                return response()->json([
                    'message' => 'Promo code usage limit reached for this user.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $promoCodeData = [
                'id' => $promoCode->id,
                'name' => $promoCode->name,
                'code' => $promoCode->code,
                'discount_percentage' => $promoCode->discount_percentage,
            ];

            if ($promoCode->discount_percentage > 0) {
                $discountAmount = ($promoCode->discount_percentage / 100) * $request->total_fare;
                $promoCodeData['discount_amount'] = $discountAmount;
                $promoCodeData['total_fare'] = $request->total_fare - $discountAmount;
            } else {
                $promoCodeData['discount_amount'] = 0;
                $promoCodeData['total_fare'] = $request->total_fare;
            }

            return response()->json([
                'promo_code' => $promoCodeData,
                'message' => 'Promo code is valid.'
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Apply Promo Code failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getVehicleTypes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'distance_km' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        try {
            $vehicleTypes = VehicleType::where('is_active', 'active')->get();

            $data = $vehicleTypes->map(function ($v) use ($request) {
                return [
                    'id'   => $v->id,
                    'name' => $v->name,
                    'icon' => ( $v->icon ) ? url($v->icon) : null,
                    'fare' => $request->distance_km * $v->base_fare,
                ];
            });
            return response()->json([
                'vehicle_types' => $data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Vehicle Types failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function requestRide(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'promo_code_id' => 'nullable|exists:promo_codes,id',
            'pickup_latitude' => 'required|string',
            'pickup_longitude' => 'required|string',
            'dropoff_latitude' => 'nullable|string',
            'dropoff_longitude' => 'nullable|string',
            'distance_km' => 'nullable|string',
            'duration_minutes' => 'nullable|string',
            'subtotal' => 'nullable|string',
            'discount_amount' => 'nullable|string',
            'total_fare' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $ride = new Ride();
            $ride->passenger_id = $user->id;
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
            $ride->save();

            return response()->json([
                'ride_id' => $ride->id,
                'message' => 'Ride requested successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Store Ride failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rideOffers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'nullable|exists:rides,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $rideOffers = RideOffer::where('ride_id', $request->ride_id)
                ->with([
                    'driver' => function ($q) {
                        $q->withAvg('driverReviews', 'rating');
                    }
                ])
                ->where('status', 'pending')
                ->orderBy('offered_at', 'desc')
                ->get();

            return response()->json([
                'ride_offers' => $rideOffers,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Ride Offers failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function acceptRideOffer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_offer_id' => 'required|exists:ride_offers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $rideOffer = RideOffer::find($request->ride_offer_id);

            if ($rideOffer->status !== 'pending') {
                return response()->json([
                    'message' => 'This ride offer is no longer available.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update ride offer status to accepted
            $rideOffer->status = 'accepted';
            $rideOffer->accepted_at = now();
            $rideOffer->save();

            // Update the associated ride
            $ride = Ride::find($rideOffer->ride_id);
            $ride->driver_id = $rideOffer->driver_id;
            $ride->total_fare = $rideOffer->proposed_price;
            $ride->status = 'accepted';
            $ride->accepted_at = now();
            $ride->save();

            $driver = $rideOffer->driver;
            app('notificationService')->notifyUsers(
                [$driver],
                'Ride Offer Accepted',
                'Your ride offer has been accepted by the passenger.',
                'ride_offers',
                $rideOffer->id,
                'ride_offer_details'
            );

            return response()->json([
                'message' => 'Ride offer accepted successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Accept Ride Offer failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rejectRideOffer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_offer_id' => 'required|exists:ride_offers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $rideOffer = RideOffer::find($request->ride_offer_id);

            if ($rideOffer->status !== 'pending') {
                return response()->json([
                    'message' => 'This ride offer cannot be cancelled.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update ride offer status to cancelled
            $rideOffer->status = 'rejected';
            $rideOffer->save();

            $driver = $rideOffer->driver;
            app('notificationService')->notifyUsers(
                [$driver],
                'Ride Offer Rejected',
                'Your ride offer has been rejected by the passenger.',
                'ride_offers',
                $rideOffer->id,
                'ride_offer_details'
            );

            return response()->json([
                'message' => 'Ride offer rejected successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Reject Ride Offer failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function expireRideOffer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_offer_id' => 'required|exists:ride_offers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $rideOffer = RideOffer::find($request->ride_offer_id);

            if ($rideOffer->status !== 'pending') {
                return response()->json([
                    'message' => 'This ride offer cannot be expired.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update ride offer status to expired
            $rideOffer->status = 'expired';
            $rideOffer->save();

            $driver = $rideOffer->driver;
            app('notificationService')->notifyUsers(
                [$driver],
                'Ride Offer Expired',
                'Your ride offer has expired.',
                'ride_offers',
                $rideOffer->id,
                'ride_offer_details'
            );

            return response()->json([
                'message' => 'Ride offer expired successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Expire Ride Offer failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function cancelRide(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'cancel_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $ride = Ride::find($request->ride_id);

            if ($ride->passenger_id !== $user->id) {
                return response()->json([
                    'message' => 'You are not authorized to cancel this ride.'
                ], Response::HTTP_FORBIDDEN);
            }

            if (in_array($ride->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'message' => 'This ride cannot be cancelled.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update ride status to cancelled
            $ride->status = 'cancelled';
            $ride->cancelled_at = now();
            $ride->cancel_reason = $request->cancel_reason;
            $ride->save();

            // Notify the driver if assigned
            if ($ride->driver_id) {
                $driver = $ride->driver;
                app('notificationService')->notifyUsers(
                    [$driver],
                    'Ride Cancelled',
                    'The passenger has cancelled the ride.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );
            }

            return response()->json([
                'message' => 'Ride cancelled successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Cancel Ride failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function postReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:users,id',
            'rating' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $review = new DriverReview();
            $review->driver_id = $request->driver_id;
            $review->review_by = $user->id;
            $review->rating = $request->rating;
            $review->comments = $request->comments;
            $review->save();

            return response()->json([
                'message' => 'Review posted successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Post Review failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getRideHistory(Request $request)
    {
        try {
            $user = $request->user();

            $rideHistory = Ride::where('passenger_id', $user->id)
                ->with('driver', 'vehicleType', 'promoCode')
                ->orderBy('requested_at', 'desc')
                ->get();

            return response()->json([
                'ride_history' => $rideHistory,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Ride History failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
