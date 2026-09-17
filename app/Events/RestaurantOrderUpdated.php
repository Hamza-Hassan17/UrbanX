<?php

namespace App\Events;

use App\Models\RestaurantOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces the old `restaurant_orders/{id}` Firebase RTDB node (`.set()`/`.update()`
 * calls across CustomerController::store(), RestaurantController::acceptOrder()/
 * rejectOrder()/generic status update, and DeliveryController's status + rider-assign
 * updates). Payload mirrors those Firebase writes field-for-field as a migration
 * draft -- confirm with mobile dev before Flutter listener code is finalized.
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
            'rider_id' => $this->rider['id'] ?? null,
            'rider_name' => $this->rider['name'] ?? null,
            'rider_phone' => $this->rider['phone'] ?? null,
            'rider_rating' => $this->rider['rating'] ?? null,
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
