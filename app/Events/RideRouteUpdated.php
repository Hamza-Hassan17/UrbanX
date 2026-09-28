<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The planned route line for the map, sent only on accept/start (not on
 * every GPS tick like driver.location). "phase" tells the app which leg
 * this polyline covers, since accept's route (driver -> pickup) and start's
 * route (pickup -> dropoff) both arrive on the same ride channel.
 */
class RideRouteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ride $ride;
    public string $phase;
    public string $polyline;
    public string $reason;

    public function __construct(Ride $ride, string $phase, string $polyline, string $reason)
    {
        $this->ride = $ride;
        $this->phase = $phase;
        $this->polyline = $polyline;
        $this->reason = $reason;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride.' . $this->ride->id);
    }

    public function broadcastAs()
    {
        return 'ride.route';
    }

    public function broadcastWith()
    {
        return [
            'ride_id' => $this->ride->id,
            'phase' => $this->phase,
            'polyline' => $this->polyline,
            'reason' => $this->reason,
        ];
    }
}
