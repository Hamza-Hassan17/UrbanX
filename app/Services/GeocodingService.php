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

    /**
     * Just the city name, not a full address -- for clustering drivers by
     * city at registration (see RegisterController). Google's response
     * doesn't label anything "city" directly; 'locality' is the closest
     * match, with administrative_area fallbacks for sparser areas where
     * Google has no locality-level data.
     */
    public function reverseGeocodeCity(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $cacheKey = 'reverse_geocode_city_' . round($lat, 5) . '_' . round($lng, 5);

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

            if (($data['status'] ?? null) !== 'OK' || empty($data['results'][0]['address_components'])) {
                return null;
            }

            $components = $data['results'][0]['address_components'];
            $city = $this->extractComponent($components, ['locality'])
                ?? $this->extractComponent($components, ['administrative_area_level_2'])
                ?? $this->extractComponent($components, ['administrative_area_level_1']);

            if ($city) {
                Cache::forever($cacheKey, $city);
            }

            return $city;
        } catch (\Throwable $e) {
            Log::error('GeocodingService::reverseGeocodeCity failed', ['lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractComponent(array $components, array $types): ?string
    {
        foreach ($components as $component) {
            if (!empty(array_intersect($types, $component['types'] ?? []))) {
                return $component['long_name'] ?? null;
            }
        }

        return null;
    }
}
