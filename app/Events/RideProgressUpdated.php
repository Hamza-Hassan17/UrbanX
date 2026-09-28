<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Periodic ETA/distance/elapsed-time update while a ride is active, thrown
 * from pingLocation() at most every ~20s (see the throttle there). live_fare
 * is always null -- this app uses fixed upfront pricing, not per-meter
 * billing (explicit product decision), so this event is a progress display
 * only and never affects what the rider is charged.
 */
class RideProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ride $ride;
    public string $phase;
    public int $etaMin;
    public float $remainingKm;
    public float $travelledKm;
    public int $elapsedMin;

    public function __construct(Ride $ride, string $phase, int $etaMin, float $remainingKm, float $travelledKm, int $elapsedMin)
    {
        $this->ride = $ride;
        $this->phase = $phase;
        $this->etaMin = $etaMin;
        $this->remainingKm = $remainingKm;
        $this->travelledKm = $travelledKm;
        $this->elapsedMin = $elapsedMin;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride.' . $this->ride->id);
    }

    public function broadcastAs()
    {
        return 'ride.progress';
    }

    public function broadcastWith()
    {
        return [
            'ride_id' => $this->ride->id,
            'phase' => $this->phase,
            'eta_min' => $this->etaMin,
            'remaining_km' => $this->remainingKm,
            'travelled_km' => $this->travelledKm,
            'elapsed_min' => $this->elapsedMin,
            'live_fare' => null,
            'updated_at' => now()->timestamp,
        ];
    }
}
