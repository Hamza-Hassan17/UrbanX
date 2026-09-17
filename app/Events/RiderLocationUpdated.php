<?php

namespace App\Events;

use App\Models\RestaurantOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces the old `restaurant_orders/{id}/rider_location` Firebase RTDB node
 * (DeliveryController::updateRiderLocation()'s `.set()` call). Kept as its own
 * event rather than folded into RestaurantOrderUpdated -- location pings fire
 * far more often than status changes, and listeners that only care about
 * status shouldn't be re-triggered on every GPS tick.
 *
 * Broadcast-only: the caller is responsible for persisting rider_latitude/
 * rider_longitude/rider_location_updated_at on the RestaurantOrder first (see
 * DeliveryController::updateRiderLocation()), since the admin dashboard's live
 * map polls that persisted value rather than relying on catching every push.
 */
class RiderLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public RestaurantOrder $order;

    public function __construct(RestaurantOrder $order)
    {
        $this->order = $order;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('restaurant-order.' . $this->order->id);
    }

    public function broadcastAs()
    {
        return 'rider.location.updated';
    }

    public function broadcastWith()
    {
        return [
            'order_id' => $this->order->id,
            'latitude' => $this->order->rider_latitude !== null ? (float) $this->order->rider_latitude : null,
            'longitude' => $this->order->rider_longitude !== null ? (float) $this->order->rider_longitude : null,
            'updated_at' => $this->order->rider_location_updated_at?->toIso8601String(),
        ];
    }
}
