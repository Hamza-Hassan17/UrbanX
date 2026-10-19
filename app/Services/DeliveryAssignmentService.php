<?php

namespace App\Services;

use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideDriverLog;
use App\Models\RideOffer;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Admin-side "give this delivery job to this rider" side effects, mirroring
 * the rider app's own accept path (DeliveryController::acceptRide): an
 * accepted RideOffer, a driver log row, the food order flipped to
 * rider_assigned, broadcasts, and pushes to the rider and the customer.
 * The caller sets $ride->driver_id / status and saves before calling this.
 */
class DeliveryAssignmentService
{
    public static function assign(Ride $ride, User $rider, ?RestaurantOrder $order, int $etaMinutes = 15, string $note = 'Assigned by admin'): RideOffer
    {
        $rideOffer = new RideOffer();
        $rideOffer->ride_id = $ride->id;
        $rideOffer->driver_id = $rider->id;
        $rideOffer->proposed_price = $ride->total_fare;
        $rideOffer->eta_minutes = $etaMinutes;
        $rideOffer->note = $note;
        $rideOffer->offered_at = now();
        $rideOffer->status = 'accepted';
        $rideOffer->save();

        RideDriverLog::updateOrCreate(
            ['ride_id' => $ride->id, 'driver_id' => $rider->id],
            ['action' => 'sent', 'note' => 'Delivery ' . strtolower($note)]
        );

        try {
            broadcast(new \App\Events\RideStatusUpdated($ride));
        } catch (\Throwable $e) {
            Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
        }

        try {
            broadcast(new \App\Events\RideOfferCreated($rideOffer));
            broadcast(new \App\Events\RideOfferStatusUpdated($rideOffer));
        } catch (\Throwable $e) {
            Log::error('RideOffer broadcast failed', ['offer_id' => $rideOffer->id, 'error' => $e->getMessage()]);
        }

        if ($order) {
            $order->status = 'rider_assigned';
            $order->save();

            try {
                broadcast(new \App\Events\RestaurantOrderUpdated($order, [
                    'id' => $rider->id,
                    'name' => $rider->name,
                    'phone' => $rider->phone,
                    'rating' => round($rider->driverReviews()->avg('rating'), 1),
                ]));
            } catch (\Throwable $e) {
                Log::error('RestaurantOrderUpdated broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        // The rider's own discovery poll only lists unclaimed jobs, so a push
        // is the one channel that tells them this job is now theirs.
        app('notificationService')->notifyUsers(
            [$rider],
            'New Delivery Assigned',
            'You have been assigned a delivery by the admin.',
            'rides',
            $ride->id,
            'ride_details'
        );

        $customer = $order ? $order->customer : $ride->passenger;
        if ($customer) {
            app('notificationService')->notifyUsers(
                [$customer],
                'New Delivery Ride Offer',
                'A driver has been assigned to your delivery order.',
                'ride_offers',
                $rideOffer->id,
                'ride_offer_details'
            );
        }

        return $rideOffer;
    }
}
