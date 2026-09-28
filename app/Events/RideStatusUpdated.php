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
        // Free-wait window before waiting charges would apply, shown as a
        // countdown once the driver taps/auto-detects "arrived". 5 minutes
        // is a placeholder default -- move to a config/DB rate table if this
        // needs to vary by city or vehicle type later.
        $freeWaitMinutes = 5;

        $driver = null;
        if ($this->ride->driver_id && $this->ride->status !== 'requested') {
            $driverUser = $this->ride->driver;
            $vehicle = $driverUser?->driverVehicle;

            if ($driverUser) {
                $driver = [
                    'id' => $driverUser->id,
                    'name' => $driverUser->name,
                    'phone_masked' => $this->maskPhone($driverUser->profile?->phone_number),
                    'vehicle' => $vehicle ? [
                        'make' => $vehicle->vehicle_make,
                        'model' => $vehicle->vehicle_model,
                        'color' => $vehicle->vehicle_color,
                        'plate' => $vehicle->vehicle_plate_number,
                    ] : null,
                ];
            }
        }

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
            'cancelled_by' => $this->ride->status === 'cancelled' ? $this->ride->status_updated_by_role : null,
            'cancel_reason' => $this->ride->cancel_reason,
            'free_wait_until' => $this->ride->status === 'arrived' && $this->ride->arrived_at
                ? $this->ride->arrived_at->copy()->addMinutes($freeWaitMinutes)->timestamp
                : null,
            'driver' => $driver,
            'requested_at' => $this->ride->requested_at?->toIso8601String(),
            'accepted_at' => $this->ride->accepted_at?->toIso8601String(),
            'arrived_at' => $this->ride->arrived_at?->toIso8601String(),
            'started_at' => $this->ride->started_at?->toIso8601String(),
            'completed_at' => $this->ride->completed_at?->toIso8601String(),
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $length = strlen($phone);
        if ($length <= 4) {
            return $phone;
        }

        return substr($phone, 0, $length - 4) . str_repeat('x', 3) . substr($phone, -1);
    }
}
