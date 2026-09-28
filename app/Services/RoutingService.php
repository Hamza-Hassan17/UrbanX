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
}
