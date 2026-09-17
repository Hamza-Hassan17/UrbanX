<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Replaces the old `ride_requests/vehicle_type_{id}/ride_{id}` Firebase RTDB
 * node. That node modeled a driver-facing "pool" (create/remove/update), but
 * nothing in this codebase ever reads it back on the driver side -- drivers
 * discover new rides via Driver\RideController::getLatestRides() polling
 * MySQL directly (distance/vehicle-type/exclusion filters), never via
 * Firebase. The only real consumer is the customer app, watching its own
 * ride's status live while waiting (requested -> accepted -> started ->
 * completed/cancelled). So this is a plain "here's the ride's current state"
 * push to the customer, not a pool -- no need to model add/remove semantics.
 */
class RideStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ride $ride;

    public function __construct(Ride $ride)
    {
        $this->ride = $ride;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('ride.' . $this->ride->id);
    }

    public function broadcastAs()
    {
        return 'ride.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'ride_id' => $this->ride->id,
            'passenger_id' => $this->ride->passenger_id,
            'driver_id' => $this->ride->driver_id,
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
            'status' => $this->ride->status,
            'ride_type' => $this->ride->ride_type,
            'cancel_reason' => $this->ride->cancel_reason,
            'requested_at' => $this->ride->requested_at?->toIso8601String(),
            'started_at' => $this->ride->started_at?->toIso8601String(),
            'completed_at' => $this->ride->completed_at?->toIso8601String(),
        ];
    }
}
