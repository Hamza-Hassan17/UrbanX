<?php

use App\Models\RestaurantOrder;
use App\Models\Ride;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Carries the customer's order status, the restaurant's own order, and (once
 * assigned) the delivery rider's name/phone/rating -- PII, so only the four
 * parties actually involved may subscribe: the customer who placed it, the
 * restaurant owner fulfilling it, the rider assigned to deliver it, and staff
 * roles that need dashboard visibility into all orders.
 */
Broadcast::channel('restaurant-order.{orderId}', function ($user, $orderId) {
    $order = RestaurantOrder::with('restaurant')->find($orderId);

    if (!$order) {
        return false;
    }

    if ((int) $order->customer_id === (int) $user->id) {
        return true;
    }

    if ($order->restaurant && (int) $order->restaurant->user_id === (int) $user->id) {
        return true;
    }

    if ($order->ride_id) {
        $ride = Ride::find($order->ride_id);
        if ($ride && (int) $ride->driver_id === (int) $user->id) {
            return true;
        }
    }

    return $user->hasRole(['admin', 'super-admin', 'dispatcher']);
});
