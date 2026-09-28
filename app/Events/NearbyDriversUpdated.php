<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Approximate nearby-car positions while a rider is searching, broadcast on
 * ride.{id} (the ride row already exists at status='requested' -- no
 * separate rider.{riderId} channel needed). Deliberately anonymous: no
 * driver ids/names, coordinates rounded to ~100m, capped at 10 points.
 */
class NearbyDriversUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ride $ride;
    public array $drivers;
    public int $nearestEtaMin;

    public function __construct(Ride $ride, array $drivers, int $nearestEtaMin)
    {
        $this->ride = $ride;
        $this->drivers = $drivers;
        $this->nearestEtaMin = $nearestEtaMin;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride.' . $this->ride->id);
    }

    public function broadcastAs()
    {
        return 'nearby.drivers';
    }

    public function broadcastWith()
    {
        return [
            'drivers' => $this->drivers,
            'nearest_eta_min' => $this->nearestEtaMin,
        ];
    }
}
