<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Food order steps shared by the customer, Partner (restaurant), Delivery
 * (rider) and admin flows, and the push notification each step sends.
 *
 * The customer only hears restaurant steps (accepted, preparing) and never
 * the rider's trip to the restaurant: the rider is revealed to them at
 * pickup ("on the way with [rider]"). Restaurant and rider see each other.
 *
 * Order status = the restaurant's workflow. Ride status = the rider's:
 * accepted -> en_route (to restaurant) -> arrived -> started (has the
 * food) -> completed.
 */
class FoodOrderFlowService
{
    /** Order statuses at which the customer may see the rider. */
    public const RIDER_VISIBLE_STATUSES = ['picked_up', 'on_the_way', 'delivered', 'completed'];

    public const BEFORE_PICKUP_STATUSES = ['accepted', 'rider_assigned', 'preparing', 'ready_for_pickup'];

    public static function rider(RestaurantOrder $order): ?User
    {
        if (!$order->ride_id) {
            return null;
        }
        return Ride::find($order->ride_id)?->driver
            ?? RideOffer::where('ride_id', $order->ride_id)->where('status', 'accepted')->latest('id')->first()?->driver;
    }

    /** A rider took the job: attach them to the delivery (it stayed riderless before). */
    public static function riderAccepted(Ride $ride, User $rider): void
    {
        if ($ride->status === 'requested') {
            $ride->update(['driver_id' => $rider->id, 'status' => 'accepted', 'accepted_at' => now()]);
        }
    }

    public static function riderHeadingToRestaurant(RestaurantOrder $order, User $rider): void
    {
        Ride::whereKey($order->ride_id)->whereIn('status', ['accepted', 'en_route'])->update(['status' => 'en_route']);
        self::notifyRestaurant($order, 'Rider on the way', "Rider {$rider->name} is on the way to collect order #{$order->order_number}.");
    }

    public static function riderArrived(RestaurantOrder $order, User $rider): void
    {
        Ride::whereKey($order->ride_id)->whereIn('status', ['accepted', 'en_route', 'arrived'])->update(['status' => 'arrived', 'arrived_at' => now()]);
        self::notifyRestaurant($order, 'Rider arrived', "Rider {$rider->name} has arrived for order #{$order->order_number}.");
    }

    /**
     * Food handed over (restaurant) or picked up (rider). Returns false if it
     * was already picked up, so nobody is notified twice.
     */
    public static function pickedUp(RestaurantOrder $order, string $by): bool
    {
        if (!in_array($order->status, self::BEFORE_PICKUP_STATUSES, true)) {
            return false;
        }
        $order->status = 'picked_up';
        $order->save();
        Ride::whereKey($order->ride_id)->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'started', 'started_at' => now()]);

        $rider = self::rider($order);
        $name = $rider?->name ?? 'your rider';
        self::notifyCustomer($order, 'Your order is on the way', "Your order from " . self::restaurantName($order) . " is on the way with {$name}.");
        if ($by === 'restaurant') {
            self::notifyRider($order, 'Food handed over', "Order #{$order->order_number} handed over. Please deliver it to the customer.");
        } else {
            self::notifyRestaurant($order, 'Order picked up', "{$name} picked up order #{$order->order_number}.");
        }

        self::broadcast($order);
        return true;
    }

    /** Order delivered: complete the delivery (rider earnings count completed deliveries). */
    public static function delivered(RestaurantOrder $order): void
    {
        $ride = $order->ride_id ? Ride::find($order->ride_id) : null;
        if ($ride && $ride->status !== 'completed') {
            $ride->driver_id = $ride->driver_id ?? self::rider($order)?->id;
            $ride->status = 'completed';
            $ride->completed_at = now();
            if (!$ride->fare_breakdown) {
                $ride->fare_breakdown = FareBreakdownService::buildRideSnapshot([
                    'subtotal' => (float) $ride->total_fare,
                    'is_delivery' => true,
                    'distance_actual_km' => $ride->distance_km,
                    'distance_charged_km' => $ride->distance_charged_km,
                ]);
            }
            $ride->save();
        }

        self::notifyCustomer($order, 'Order delivered', 'Your order has been delivered. Enjoy your meal!');
        self::notifyRestaurant($order, 'Order delivered', "Order #{$order->order_number} was delivered.");
    }

    public static function notifyCustomer(RestaurantOrder $order, string $title, string $message): void
    {
        self::push(User::find($order->customer_id), $title, $message, 'restaurant_orders', $order->id, 'order_details');
    }

    public static function notifyRestaurant(RestaurantOrder $order, string $title, string $message): void
    {
        $ownerId = Restaurant::whereKey($order->restaurant_id)->value('user_id');
        self::push($ownerId ? User::find($ownerId) : null, $title, $message, 'restaurant_orders', $order->id, 'restaurant_order_details');
    }

    public static function notifyRider(RestaurantOrder $order, string $title, string $message): void
    {
        self::push(self::rider($order), $title, $message, 'rides', $order->ride_id, 'delivery_details');
    }

    private static function restaurantName(RestaurantOrder $order): string
    {
        return (string) (Restaurant::whereKey($order->restaurant_id)->value('name') ?? 'the restaurant');
    }

    private static function push(?User $user, string $title, string $message, string $table, $id, string $page): void
    {
        if (!$user) {
            return;
        }
        try {
            app('notificationService')->notifyUsers([$user], $title, $message, $table, $id, $page);
        } catch (\Throwable $e) {
            Log::error('Food order notification failed', ['user_id' => $user->id, 'title' => $title, 'error' => $e->getMessage()]);
        }
    }

    private static function broadcast(RestaurantOrder $order): void
    {
        try {
            broadcast(new \App\Events\RestaurantOrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error('RestaurantOrderUpdated broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
