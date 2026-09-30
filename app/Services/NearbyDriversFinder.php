<?php

namespace App\Services;

use App\Models\DriverVehicle;

/**
 * Anonymized nearby-driver lookup shared by the searching-ride case
 * (Driver\RideController::broadcastNearbyDriversForSearchingRides, keyed off
 * a ride's pickup point) and the pre-ride "choose a trip" preview
 * (Customer\RideController's watch endpoint + the same idle-ping trigger,
 * keyed off a browsing passenger's current location) -- same privacy rules
 * either way: no ids/names, coordinates rounded to ~100m, capped at 10.
 */
class NearbyDriversFinder
{
    private const RADIUS_KM = 5;
    private const AVERAGE_SPEED_KMH = 30;

    /**
     * @return array{points: array<array{lat: float, lng: float}>, nearest_eta_min: int}|null
     *         Null when no drivers are found nearby.
     */
    public function find(float $lat, float $lng, int $vehicleTypeId): ?array
    {
        $nearbyDrivers = DriverVehicle::where('vehicle_type_id', $vehicleTypeId)
            ->join('users', 'users.id', '=', 'driver_vehicles.driver_id')
            ->whereNotNull('users.lat')
            ->whereNotNull('users.lang')
            ->selectRaw("
                users.lat, users.lang,
                (6371 * acos(
                    cos(radians(?)) *
                    cos(radians(users.lat)) *
                    cos(radians(users.lang) - radians(?)) +
                    sin(radians(?)) *
                    sin(radians(users.lat))
                )) AS distance
            ", [$lat, $lng, $lat])
            ->havingRaw('distance <= ?', [self::RADIUS_KM])
            ->orderBy('distance')
            ->limit(10)
            ->get();

        if ($nearbyDrivers->isEmpty()) {
            return null;
        }

        // Straight-line distance / average city speed -- a coarse,
        // anonymized estimate, not a per-ride live ETA.
        $nearestEtaMin = max(1, (int) round(($nearbyDrivers->first()->distance / self::AVERAGE_SPEED_KMH) * 60));

        $points = $nearbyDrivers->map(fn ($d) => [
            'lat' => round((float) $d->lat, 3),
            'lng' => round((float) $d->lang, 3),
        ])->values()->all();

        return [
            'points' => $points,
            'nearest_eta_min' => $nearestEtaMin,
        ];
    }
}
