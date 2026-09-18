<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a newly requested ride to one specific driver's own channel.
 * Fired once per matching driver (see Customer\RideController::requestRide()),
 * using the same proximity/vehicle-type eligibility query that
 * Driver\RideController::getLatestRides() already applies on poll -- this is
 * the push-based complement to that endpoint, not a replacement for it (the
 * app should keep polling as a fallback in case a push is missed).
 */
class NewRideRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ride $ride;
    public int $driverId;

    public function __construct(Ride $ride, int $driverId)
    {
        $this->ride = $ride;
        $this->driverId = $driverId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('driver.' . $this->driverId);
    }

    public function broadcastAs()
    {
        return 'ride.requested';
    }

    public function broadcastWith()
    {
        return [
            'ride_id' => $this->ride->id,
            'vehicle_type_id' => $this->ride->vehicle_type_id,
            'pickup' => [
                'latitude' => $this->ride->pickup_latitude,
                'longitude' => $this->ride->pickup_longitude,
            ],
            'dropoff' => [
                'latitude' => $this->ride->dropoff_latitude,
                'longitude' => $this->ride->dropoff_longitude,
            ],
            'distance_km' => $this->ride->distance_km,
            'duration_minutes' => $this->ride->duration_minutes,
            'total_fare' => $this->ride->total_fare !== null ? (float) $this->ride->total_fare : null,
            'requested_at' => $this->ride->requested_at?->toIso8601String(),
        ];
    }
}
