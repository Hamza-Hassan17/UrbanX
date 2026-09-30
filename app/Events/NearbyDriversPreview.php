<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Same shape and privacy rules as NearbyDriversUpdated (anonymized, rounded
 * to ~100m, capped at 10 points) but for a passenger browsing the "choose a
 * trip" screen before any ride exists -- pushed to their own rider.{id}
 * channel instead of a ride.{id} channel, since there's no ride yet.
 */
class NearbyDriversPreview implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $passengerId;
    public array $drivers;
    public int $nearestEtaMin;

    public function __construct(int $passengerId, array $drivers, int $nearestEtaMin)
    {
        $this->passengerId = $passengerId;
        $this->drivers = $drivers;
        $this->nearestEtaMin = $nearestEtaMin;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('rider.' . $this->passengerId);
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
