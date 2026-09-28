<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live driver position during an active taxi ride, pushed on the same
 * ride.{id} channel RideStatusUpdated already uses. Kept deliberately
 * minimal (hot path, fires every few seconds) -- no rider/driver names or
 * anything not needed to move a marker on the map.
 */
class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ride $ride;
    public float $latitude;
    public float $longitude;
    public ?float $heading;
    public ?float $speedKmh;

    public function __construct(Ride $ride, float $latitude, float $longitude, ?float $heading = null, ?float $speedKmh = null)
    {
        $this->ride = $ride;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->heading = $heading;
        $this->speedKmh = $speedKmh;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride.' . $this->ride->id);
    }

    public function broadcastAs()
    {
        return 'driver.location';
    }

    public function broadcastWith()
    {
        return [
            'ride_id' => $this->ride->id,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'heading' => $this->heading,
            'speed_kmh' => $this->speedKmh,
            'ts' => now()->timestamp,
        ];
    }
}
