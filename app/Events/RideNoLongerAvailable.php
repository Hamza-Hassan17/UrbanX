<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a driver who received NewRideRequested that the ride was claimed by
 * someone else, so their accept popup can dismiss instead of showing a stale
 * offer the driver would tap only to get a "no longer available" error.
 */
class RideNoLongerAvailable implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $rideId;
    public int $driverId;

    public function __construct(int $rideId, int $driverId)
    {
        $this->rideId = $rideId;
        $this->driverId = $driverId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('driver.' . $this->driverId);
    }

    public function broadcastAs()
    {
        return 'ride.unavailable';
    }

    public function broadcastWith()
    {
        return [
            'ride_id' => $this->rideId,
        ];
    }
}
