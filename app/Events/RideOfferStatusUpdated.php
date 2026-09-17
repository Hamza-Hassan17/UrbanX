<?php

namespace App\Events;

use App\Models\RideOffer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fires once per offer whenever its status changes: accepted, rejected, or
 * superseded (another driver's offer on the same ride was accepted instead).
 *
 * Broadcast per-offer (ride-offer.{offerId}), not on the ride's channel --
 * this is the specific offering driver's own outcome, not something every
 * subscriber to the ride needs. This also fixes a real pre-existing bug: the
 * old Firebase code wrote rejections to `ride_offers/ride_offer_{id}/status`,
 * a path shape nothing else in the app ever wrote or read (every other write
 * used `ride_offers/ride_{ride_id}/offer_{offer_id}`) -- so a driver's
 * rejected-offer notification likely never reached a real listener. And
 * "superseded" (losing to another driver) had no notification path at all;
 * the old code just silently `.remove()`'d the sibling offer nodes from
 * Firebase with nothing to tell the losing driver why.
 */
class RideOfferStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public RideOffer $offer;

    public function __construct(RideOffer $offer)
    {
        $this->offer = $offer;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride-offer.' . $this->offer->id);
    }

    public function broadcastAs()
    {
        return 'ride.offer.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'offer_id' => $this->offer->id,
            'ride_id' => $this->offer->ride_id,
            'driver_id' => $this->offer->driver_id,
            'status' => $this->offer->status,
            'proposed_price' => $this->offer->proposed_price !== null ? (float) $this->offer->proposed_price : null,
            'accepted_at' => $this->offer->accepted_at?->toIso8601String(),
        ];
    }
}
