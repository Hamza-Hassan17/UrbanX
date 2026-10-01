<?php

namespace App\Events;

use App\Models\RideStop;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Driver marked an intermediate "Add Stop" waypoint as arrived -- lets the
 * passenger/admin app update the trip progress UI (e.g. "stop 1 of 2
 * complete") without needing to re-fetch the whole ride.
 */
class RideStopArrived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public RideStop $stop;

    public function __construct(RideStop $stop)
    {
        $this->stop = $stop;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride.' . $this->stop->ride_id);
    }

    public function broadcastAs()
    {
        return 'ride.stop.arrived';
    }

    public function broadcastWith()
    {
        return [
            'ride_id' => $this->stop->ride_id,
            'stop_id' => $this->stop->id,
            'sequence' => $this->stop->sequence,
            'arrived_at' => $this->stop->arrived_at->toIso8601String(),
        ];
    }
}
