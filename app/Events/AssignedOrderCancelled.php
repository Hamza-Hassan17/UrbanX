<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Batch 1 review follow-up -- tells a rider who was assigned a food order
 * that it was just cancelled out from under them (by the customer,
 * restaurant, or an admin) and they've been released. Broadcast on the
 * rider's own driver.{id} channel (same convention RideNoLongerAvailable
 * uses), not the order's restaurant-order.{id} channel -- the rider app
 * needs this even if it never subscribed to that specific order's channel,
 * and needs it addressed by driver id, not order id, to reliably clear
 * whatever "current job" state it's showing.
 */
class AssignedOrderCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $driverId;
    public int $orderId;
    public string $cancelledBy;
    public string $reason;

    public function __construct(int $driverId, int $orderId, string $cancelledBy, string $reason)
    {
        $this->driverId = $driverId;
        $this->orderId = $orderId;
        $this->cancelledBy = $cancelledBy;
        $this->reason = $reason;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('driver.' . $this->driverId);
    }

    public function broadcastAs()
    {
        return 'order.cancelled';
    }

    public function broadcastWith()
    {
        return [
            'order_id' => $this->orderId,
            'status' => 'cancelled',
            'cancelled_by' => $this->cancelledBy,
            'reason' => $this->reason,
        ];
    }
}
