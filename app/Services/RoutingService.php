<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the OSRM driving-route API, extracted from
 * Dashboard\HomeController::getRoute() so the mobile-facing ride flow
 * (route polyline broadcast, later live ETA recalculation) can reuse the
 * same call instead of duplicating the HTTP request shape. Currently talks
 * to the public router.project-osrm.org demo server, same as the existing
 * pre-ride quote -- swap the base URL via config if a self-hosted OSRM
 * instance is stood up later.
 */
class RoutingService
{
    /**
     * @return array{polyline: string, distance_km: float, duration_min: float}|null
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        try {
            $baseUrl = config('services.osrm.url', 'https://router.project-osrm.org');
            $url = "{$baseUrl}/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}?overview=full&geometries=polyline";

            $response = Http::timeout(5)->get($url)->json();

            if (!isset($response['routes'][0])) {
                return null;
            }

            $route = $response['routes'][0];

            return [
                'polyline' => $route['geometry'],
                'distance_km' => round($route['distance'] / 1000, 2),
                'duration_min' => round($route['duration'] / 60),
            ];
        } catch (\Throwable $e) {
            Log::error('RoutingService::route failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Straight-line distance x1.3 and an average-speed ETA -- the brief's
     * specified fallback for when the routing provider is unavailable, so a
     * ride never stalls just because OSRM is down or slow.
     *
     * @return array{distance_km: float, duration_min: float}
     */
    public function fallbackEstimate(float $fromLat, float $fromLng, float $toLat, float $toLng, float $averageSpeedKmh = 30): array
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lonDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lonDelta / 2) ** 2;

        $straightLineKm = $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceKm = round($straightLineKm * 1.3, 2);

        return [
            'distance_km' => $distanceKm,
            'duration_min' => max(1, round(($distanceKm / $averageSpeedKmh) * 60)),
        ];
    }
}
