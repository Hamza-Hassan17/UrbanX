<?php

namespace App\Events;

use App\Models\RestaurantOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces the old `restaurant_orders/{id}` Firebase RTDB node (`.set()`/`.update()`
 * calls across CustomerController::store(), RestaurantController::acceptOrder()/
 * rejectOrder()/generic status update, and DeliveryController's status + rider-assign
 * updates). Payload shape is our own (not a Firebase mirror, per mobile dev) --
 * confirmed with mobile dev before Flutter listener code is finalized.
 */
class RestaurantOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public RestaurantOrder $order;

    /**
     * Rider fields aren't columns on RestaurantOrder -- they were only ever
     * denormalized into the Firebase node from the accepted RideOffer's driver
     * at write time. Passed in explicitly rather than re-derived here, since
     * the caller already has the driver in hand at every call site.
     */
    public ?array $rider;

    public function __construct(RestaurantOrder $order, ?array $rider = null)
    {
        $this->order = $order;
        $this->rider = $rider;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('restaurant-order.' . $this->order->id);
    }

    public function broadcastAs()
    {
        return 'order.updated';
    }

    public function broadcastWith()
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'restaurant_id' => $this->order->restaurant_id,
            'customer_id' => $this->order->customer_id,
            'total_price' => (float) $this->order->total_price,
            'rider' => $this->rider ? [
                'id' => $this->rider['id'],
                'name' => $this->rider['name'],
                'phone' => $this->rider['phone'],
                'rating' => $this->rider['rating'],
            ] : null,
            // Named for what it actually is -- the record's last-modified time,
            // not a precise per-status-transition timestamp. No dedicated
            // delivered_at/status-history column exists on restaurant_orders to
            // report anything more specific than that.
            'order_status_updated_at' => $this->order->updated_at?->toIso8601String()
                ?? now()->toIso8601String(),
        ];
    }
}
