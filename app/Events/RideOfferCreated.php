<?php

namespace App\Events;

use App\Models\RideOffer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A driver submitted a new offer on a ride. Broadcast on the ride's own
 * channel (not a per-offer one) so the customer watching that ride sees
 * incoming offers live -- replaces the old Firebase
 * `ride_offers/ride_{id}/offer_{id}` `.set()` writes.
 */
class RideOfferCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public RideOffer $offer;

    public function __construct(RideOffer $offer)
    {
        $this->offer = $offer;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride.' . $this->offer->ride_id);
    }

    public function broadcastAs()
    {
        return 'ride.offer.created';
    }

    public function broadcastWith()
    {
        $driver = $this->offer->driver;

        return [
            'offer_id' => $this->offer->id,
            'ride_id' => $this->offer->ride_id,
            'driver' => $driver ? [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'rating' => round($driver->driverReviews()->avg('rating') ?? 0, 1),
                // The old Firebase code read $driver->vehicle->type, but
                // neither that relation nor that column exist on User/
                // DriverVehicle -- it would have thrown on every offer
                // broadcast rather than gracefully returning null. Using the
                // real relation chain instead: driverVehicle -> vehicleType.
                'vehicle_type' => $driver->driverVehicle?->vehicleType?->name,
            ] : null,
            'proposed_price' => $this->offer->proposed_price !== null ? (float) $this->offer->proposed_price : null,
            'eta_minutes' => $this->offer->eta_minutes,
            'note' => $this->offer->note,
            'status' => $this->offer->status,
            'offered_at' => $this->offer->offered_at?->toIso8601String(),
        ];
    }
}
