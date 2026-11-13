<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The Delivery app's own view of a rider's food deliveries: list, detail,
 * and the earnings figures for each (same numbers as the Earnings screen).
 */
class RiderDeliveryHistoryService
{
    public const IN_PROGRESS = ['accepted', 'en_route', 'arrived', 'started'];

    /** Rides this rider has (or had) as a food delivery. */
    public static function query(User $rider)
    {
        $offered = RideOffer::where('driver_id', $rider->id)->where('status', 'accepted')->select('ride_id');

        return Ride::where('ride_type', 'delivery')
            ->where(fn ($q) => $q->where('driver_id', $rider->id)->orWhereIn('id', $offered))
            ->whereIn('id', RestaurantOrder::whereNotNull('ride_id')->select('ride_id'));
    }

    /** Rider's earning on one delivery: delivery fee minus platform share and SST. */
    public static function earning(Ride $ride, ?RestaurantOrder $order): array
    {
        $fb = $ride->fare_breakdown;
        if (!$fb) {
            $fb = FareBreakdownService::buildRideSnapshot([
                'subtotal' => (float) $ride->total_fare,
                'is_delivery' => true,
            ]);
        }

        $tip = (float) ($order?->tip_amount ?? 0);
        $done = $ride->status === 'completed';

        return [
            'delivery_fee' => round((float) ($fb['rider_gross'] ?? $ride->total_fare), 2),
            'platform_share' => round((float) ($fb['commission_amount'] ?? 0), 2),
            'tax' => round((float) ($fb['sst_amount'] ?? 0), 2),
            'net' => round((float) ($fb['rider_net'] ?? 0), 2),
            'tip' => round($tip, 2),
            // Only completed deliveries count as earned.
            'earned' => $done ? round((float) ($fb['rider_net'] ?? 0) + $tip, 2) : 0.0,
        ];
    }

    /** Short row for lists (My Deliveries, recent activity). */
    public static function row(Ride $ride, ?RestaurantOrder $order, ?Restaurant $restaurant): array
    {
        $money = self::earning($ride, $order);

        return [
            'ride_id' => $ride->id,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'status' => self::label($ride, $order),
            'order_status' => $order?->status,
            'delivery_status' => $ride->status,
            'created_at' => $ride->created_at?->toIso8601String(),
            'completed_at' => $ride->completed_at?->toIso8601String(),
            'from' => [
                'name' => $restaurant?->name,
                'address' => $restaurant?->address,
                'latitude' => $ride->pickup_latitude !== null ? (float) $ride->pickup_latitude : null,
                'longitude' => $ride->pickup_longitude !== null ? (float) $ride->pickup_longitude : null,
            ],
            'to' => [
                'address' => $order?->delivery_address,
                'latitude' => $ride->dropoff_latitude !== null ? (float) $ride->dropoff_latitude : null,
                'longitude' => $ride->dropoff_longitude !== null ? (float) $ride->dropoff_longitude : null,
            ],
            'payment_method' => $order?->payment_method,
            'cash_to_collect' => $order && $order->payment_method === 'cod' ? round((float) $order->total_price, 2) : 0.0,
            'earning' => $money,
        ];
    }

    /** Full detail for the order screen. */
    public static function detail(Ride $ride): array
    {
        $order = RestaurantOrder::with('items')->where('ride_id', $ride->id)->first();
        $restaurant = $order ? Restaurant::with('user')->find($order->restaurant_id) : null;
        $customer = $order ? User::find($order->customer_id) : null;
        $closed = in_array($ride->status, ['completed', 'cancelled'], true);

        return self::row($ride, $order, $restaurant) + [
            'restaurant' => [
                'name' => $restaurant?->name,
                'address' => $restaurant?->address,
                'phone' => $restaurant?->user?->phone,
            ],
            'customer' => [
                'name' => $customer?->name,
                // Contact details only while the delivery is active.
                'phone' => $closed ? null : $customer?->phone,
            ],
            'rider_note' => $order?->rider_note,
            'items' => $order ? $order->items->map(fn ($i) => [
                'name' => $i->item_name,
                'quantity' => (int) $i->quantity,
                'price' => round((float) $i->total_price, 2),
                'options' => collect($i->selected_options ?? [])->pluck('option_name')->values(),
            ])->values() : [],
            'bill' => $order ? [
                'food_subtotal' => round((float) $order->subtotal, 2),
                'discount' => round((float) $order->discount, 2),
                'delivery_fee' => round((float) $order->delivery_fee, 2),
                'platform_fee' => round((float) $order->platform_fee, 2),
                'small_order_fee' => round((float) ($order->small_order_fee ?? 0), 2),
                'tip' => round((float) ($order->tip_amount ?? 0), 2),
                'total' => round((float) $order->total_price, 2),
            ] : null,
            'timeline' => [
                'accepted_at' => $ride->accepted_at?->toIso8601String(),
                'arrived_at_restaurant_at' => $ride->arrived_at?->toIso8601String(),
                'picked_up_at' => $ride->started_at?->toIso8601String(),
                'delivered_at' => $ride->completed_at?->toIso8601String(),
                'cancelled_at' => $ride->cancelled_at?->toIso8601String(),
            ],
            'cancel_reason' => $order?->cancel_reason,
        ];
    }

    /** @param Collection<Ride> $rides */
    public static function rows(Collection $rides): Collection
    {
        $orders = RestaurantOrder::whereIn('ride_id', $rides->pluck('id'))->get()->keyBy('ride_id');
        $restaurants = Restaurant::whereIn('id', $orders->pluck('restaurant_id'))->get()->keyBy('id');

        return $rides->map(function ($ride) use ($orders, $restaurants) {
            $order = $orders->get($ride->id);
            return self::row($ride, $order, $order ? $restaurants->get($order->restaurant_id) : null);
        })->values();
    }

    private static function label(Ride $ride, ?RestaurantOrder $order): string
    {
        if ($ride->status === 'completed' || in_array($order?->status, ['delivered', 'completed'], true)) {
            return 'delivered';
        }
        if ($ride->status === 'cancelled' || in_array($order?->status, ['cancelled', 'rejected'], true)) {
            return 'cancelled';
        }
        return 'in_progress';
    }
}
