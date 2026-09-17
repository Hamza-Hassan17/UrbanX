<?php

use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideOffer;
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

/**
 * Carries the ride's live status (and, once assigned, driver_id) for the
 * passenger to watch while requesting/riding. Restricted to the passenger,
 * the assigned driver, and staff roles.
 */
Broadcast::channel('ride.{rideId}', function ($user, $rideId) {
    $ride = Ride::find($rideId);

    if (!$ride) {
        return false;
    }

    if ((int) $ride->passenger_id === (int) $user->id) {
        return true;
    }

    if ($ride->driver_id && (int) $ride->driver_id === (int) $user->id) {
        return true;
    }

    return $user->hasRole(['admin', 'super-admin', 'dispatcher']);
});

/**
 * Carries one specific offer's outcome (accepted/rejected/superseded) --
 * this is the offering driver's own result, not something every subscriber
 * to the ride needs. Restricted to the driver who made the offer, the ride's
 * passenger (so they can see who's responding), and staff roles.
 */
Broadcast::channel('ride-offer.{offerId}', function ($user, $offerId) {
    $offer = RideOffer::with('ride')->find($offerId);

    if (!$offer) {
        return false;
    }

    if ((int) $offer->driver_id === (int) $user->id) {
        return true;
    }

    if ($offer->ride && (int) $offer->ride->passenger_id === (int) $user->id) {
        return true;
    }

    return $user->hasRole(['admin', 'super-admin', 'dispatcher']);
});
