<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\DriverReview;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantReview;
use App\Services\FoodOrderFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Rate your order": after delivery the customer rates the rider (the
 * delivery) and the restaurant (the food) once per order. Both optional,
 * at least one required; the screen can be skipped.
 */
class OrderRatingController extends Controller
{
    private const DELIVERED = ['delivered', 'completed'];
    /** Older orders stop being suggested for rating. */
    private const PROMPT_DAYS = 7;

    /** The order to show the rating screen for, or null. Call on app open and after delivery. */
    public function pending(Request $request)
    {
        $order = RestaurantOrder::where('customer_id', $request->user()->id)
            ->whereIn('status', self::DELIVERED)
            ->whereNull('rating_skipped_at')
            ->where('updated_at', '>=', now()->subDays(self::PROMPT_DAYS))
            ->whereDoesntHave('restaurantReview')
            ->whereDoesntHave('riderReview')
            ->latest('updated_at')
            ->first();

        return response()->json([
            'message' => 'Pending rating',
            'order' => $order ? $this->summary($order) : null,
        ], Response::HTTP_OK);
    }

    public function rate(Request $request, $order_id)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_rating' => 'nullable|integer|min:1|max:5|required_without:rider_rating',
            'restaurant_comment' => 'nullable|string|max:1000',
            'rider_rating' => 'nullable|integer|min:1|max:5|required_without:restaurant_rating',
            'rider_comment' => 'nullable|string|max:1000',
        ], [
            'restaurant_rating.required_without' => 'Please rate the food or the delivery.',
            'rider_rating.required_without' => 'Please rate the food or the delivery.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order = $this->ownOrder($request, $order_id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], Response::HTTP_NOT_FOUND);
        }
        if (!in_array($order->status, self::DELIVERED, true)) {
            return response()->json(['message' => 'You can rate this order once it has been delivered.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (RestaurantReview::where('order_id', $order->id)->exists() || DriverReview::where('order_id', $order->id)->exists()) {
            return response()->json(['message' => 'You have already rated this order.'], Response::HTTP_CONFLICT);
        }

        $rider = FoodOrderFlowService::rider($order);
        if ($request->filled('rider_rating') && !$rider) {
            return response()->json(['message' => 'This order has no rider to rate.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($request, $order, $rider) {
            if ($request->filled('restaurant_rating')) {
                RestaurantReview::create([
                    'restaurant_id' => $order->restaurant_id,
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'rating' => (int) $request->restaurant_rating,
                    'comment' => $request->restaurant_comment,
                ]);
            }
            if ($request->filled('rider_rating')) {
                DriverReview::create([
                    'driver_id' => $rider->id,
                    'review_by' => $order->customer_id,
                    'order_id' => $order->id,
                    'rating' => (int) $request->rider_rating,
                    'comments' => $request->rider_comment,
                ]);
            }
        });

        return response()->json([
            'message' => 'Thanks for your rating!',
            'rating' => $this->ratingOf($order->fresh()),
        ], Response::HTTP_OK);
    }

    /** "Not now": stop suggesting this order. It can still be rated from the order screen. */
    public function skip(Request $request, $order_id)
    {
        $order = $this->ownOrder($request, $order_id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], Response::HTTP_NOT_FOUND);
        }
        $order->forceFill(['rating_skipped_at' => now()])->saveQuietly();

        return response()->json(['message' => 'Okay, we won\'t ask again.'], Response::HTTP_OK);
    }

    /** Rating block for order-details. */
    public static function ratingOf(RestaurantOrder $order): array
    {
        $restaurant = RestaurantReview::where('order_id', $order->id)->first();
        $rider = DriverReview::where('order_id', $order->id)->first();

        return [
            'can_rate' => in_array($order->status, self::DELIVERED, true) && !$restaurant && !$rider,
            'restaurant_rating' => $restaurant?->rating,
            'restaurant_comment' => $restaurant?->comment,
            'rider_rating' => $rider?->rating,
            'rider_comment' => $rider?->comments,
        ];
    }

    private function ownOrder(Request $request, $orderId): ?RestaurantOrder
    {
        return RestaurantOrder::where('id', $orderId)->where('customer_id', $request->user()->id)->first();
    }

    private function summary(RestaurantOrder $order): array
    {
        $restaurant = Restaurant::find($order->restaurant_id);
        $rider = FoodOrderFlowService::rider($order);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'delivered_at' => $order->updated_at?->toIso8601String(),
            'restaurant' => [
                'id' => $restaurant?->id,
                'name' => $restaurant?->name,
                'logo' => $restaurant?->logo ? url('storage/' . $restaurant->logo) : null,
            ],
            'rider' => $rider ? ['id' => $rider->id, 'name' => $rider->name] : null,
        ];
    }
}
