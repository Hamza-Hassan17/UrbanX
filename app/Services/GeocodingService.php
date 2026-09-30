<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reverse geocoding (coordinates -> human-readable address) via Google's
 * Geocoding API, for the Live Tracking map's ride popups -- previously
 * showed raw "24.86, 67.00" pickup/dropoff strings.
 *
 * Cached indefinitely per rounded coordinate pair (a pickup/dropoff point
 * doesn't move once a ride is created, and many rides share common pickup
 * spots), so this is at most one paid API call per distinct location ever
 * seen, not one per poll/request.
 */
class GeocodingService
{
    public function reverseGeocode(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $cacheKey = 'reverse_geocode_' . round($lat, 5) . '_' . round($lng, 5);

        // Only cache a successful result -- caching a null forever would
        // permanently give up on a location after one transient API
        // failure (timeout, rate limit) and never retry it.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $key = config('services.google_maps.key');
            if (!$key) {
                return null;
            }

            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $key,
            ]);

            $data = $response->json();

            if (($data['status'] ?? null) === 'OK' && !empty($data['results'][0]['formatted_address'])) {
                $address = $data['results'][0]['formatted_address'];
                Cache::forever($cacheKey, $address);
                return $address;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('GeocodingService::reverseGeocode failed', ['lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
