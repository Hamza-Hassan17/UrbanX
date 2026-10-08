<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Batch 1 Part 6 -- shared cancellation logic for the three call sites
 * (customer, restaurant, admin), each with their own status-gating rules
 * but identical mechanics once allowed: stamp cancelled_by/reason/time,
 * release any assigned rider, notify every party, broadcast the same
 * RestaurantOrderUpdated event the rest of the order lifecycle already
 * uses.
 */
class RestaurantOrderCancellationService
{
    public static function cancel(RestaurantOrder $order, string $cancelledBy, string $reason): void
    {
        $order->status = 'cancelled';
        $order->cancelled_by = $cancelledBy;
        $order->cancel_reason = $reason;
        $order->cancelled_at = now();
        $order->save();

        $releasedDriver = null;
        if ($order->ride_id) {
            $ride = Ride::find($order->ride_id);
            if ($ride) {
                if ($ride->driver_id) {
                    $releasedDriver = User::find($ride->driver_id);
                    $ride->driver_id = null;
                }
                $ride->status = 'cancelled';
                $ride->cancelled_at = now();
                $ride->cancel_reason = $reason;
                $ride->save();
            }
        }

        if ($releasedDriver) {
            app('notificationService')->notifyUsers(
                [$releasedDriver],
                'Order Cancelled',
                "Order #{$order->order_number} was cancelled. You've been released from this delivery.",
                'restaurant_orders',
                $order->id,
                'order_details'
            );
        }

        $partiesToNotify = [];
        if ($cancelledBy !== 'customer' && $order->customer) {
            $partiesToNotify[] = $order->customer;
        }
        if ($cancelledBy !== 'restaurant' && $order->restaurant?->user) {
            $partiesToNotify[] = $order->restaurant->user;
        }
        if (!empty($partiesToNotify)) {
            app('notificationService')->notifyUsers(
                $partiesToNotify,
                'Order Cancelled',
                "Order #{$order->order_number} was cancelled: {$reason}",
                'restaurant_orders',
                $order->id,
                'order_details'
            );
        }

        try {
            broadcast(new \App\Events\RestaurantOrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error('RestaurantOrderUpdated broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
