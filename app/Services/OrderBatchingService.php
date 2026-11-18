<?php

namespace App\Services;

use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

/**
 * A rider already on a food delivery may take one more order when all of
 * these hold (Pricing & Fees -> Order batching):
 *   1. same restaurant as the order(s) he has,
 *   2. the customers are within the batch radius of each other,
 *   3. he hasn't left the restaurant with his food yet,
 *   4. neither customer is delayed beyond the limit: the first by waiting
 *      for the new order's food, the new one by the extra stop on the way.
 *
 * Times are estimates: straight-line km x road factor x minutes per km
 * (the same speed as the customer's delivery estimate), and kitchen time
 * from the dishes' preparation times minus time since acceptance.
 */
class OrderBatchingService
{
    private const ROAD_FACTOR = 1.3;
    private const MINUTES_PER_KM = 3;
    private const DEFAULT_PREP_MINUTES = 20;
    private const ACTIVE = ['accepted', 'en_route', 'arrived', 'started'];
    private const CLOSED = ['cancelled', 'rejected', 'delivered', 'completed'];

    public static function settings(): array
    {
        $s = SystemSetting::first();

        return [
            'enabled' => (bool) ($s?->batching_enabled ?? false),
            'max_orders' => max(1, (int) ($s?->batch_max_orders ?? 2)),
            'radius_km' => (float) ($s?->batch_customer_radius_km ?? 2),
            'max_delay' => (int) ($s?->batch_max_delay_minutes ?? 10),
        ];
    }

    /** The rider's unfinished jobs (any kind), oldest first. */
    public static function activeRides(int $riderId): Collection
    {
        $rides = Ride::where('driver_id', $riderId)->whereIn('status', self::ACTIVE)->orderBy('id')->get();
        if ($rides->isEmpty()) {
            return $rides;
        }
        $closed = RestaurantOrder::whereIn('ride_id', $rides->pluck('id'))->whereIn('status', self::CLOSED)->pluck('ride_id')->all();

        return $rides->reject(fn ($r) => in_array($r->id, $closed))->values();
    }

    /** Null if the rider may also take this delivery, else the reason he can't. */
    public static function check(int $riderId, Ride $newRide): ?string
    {
        $order = RestaurantOrder::with('items.restaurantItem')->where('ride_id', $newRide->id)->first();
        if (!$order) {
            return 'Only food orders can be combined.';
        }

        return self::checkJob($riderId, [
            'restaurant_id' => (int) $order->restaurant_id,
            'drop_lat' => (float) $newRide->dropoff_latitude,
            'drop_lng' => (float) $newRide->dropoff_longitude,
            'pickup_lat' => (float) $newRide->pickup_latitude,
            'pickup_lng' => (float) $newRide->pickup_longitude,
            'prep_left' => self::prepLeft($order, $newRide),
        ]);
    }

    /**
     * Same check for a job described directly (used before an admin booking
     * exists): restaurant_id, pickup/drop coordinates and prep_left minutes.
     */
    public static function checkJob(int $riderId, array $job): ?string
    {
        $active = self::activeRides($riderId);
        if ($active->isEmpty()) {
            return null; // free rider: nothing to combine with
        }

        $cfg = self::settings();
        if (!$cfg['enabled']) {
            return 'already on a delivery';
        }
        if ($active->count() >= $cfg['max_orders']) {
            return "already has {$cfg['max_orders']} order(s), the most allowed at once";
        }

        foreach ($active as $ride) {
            $order = RestaurantOrder::with('items.restaurantItem')->where('ride_id', $ride->id)->first();
            if (!$order) {
                return 'already on a parcel or ride';
            }
            if ((int) $order->restaurant_id !== $job['restaurant_id']) {
                return 'already has an order from another restaurant';
            }
            if ($ride->status === 'started' || in_array($order->status, ['picked_up', 'on_the_way'], true)) {
                return 'has already left the restaurant with his current order';
            }

            $apart = self::km($ride->dropoff_latitude, $ride->dropoff_longitude, $job['drop_lat'], $job['drop_lng']);
            if ($apart > $cfg['radius_km']) {
                return 'customers are ' . round($apart, 1) . " km apart (limit {$cfg['radius_km']} km)";
            }

            // First customer: waits only for the new order's food (he's dropped first).
            $wait = max(0, $job['prep_left'] - self::prepLeft($order, $ride));
            if ($wait > $cfg['max_delay']) {
                return "the new order needs about {$wait} more minutes in the kitchen (limit {$cfg['max_delay']})";
            }

            // New customer: the extra stop on the way (restaurant -> first -> new vs direct).
            $direct = self::minutes(self::km($job['pickup_lat'], $job['pickup_lng'], $job['drop_lat'], $job['drop_lng']));
            $viaFirst = self::minutes(self::km($job['pickup_lat'], $job['pickup_lng'], $ride->dropoff_latitude, $ride->dropoff_longitude))
                + self::minutes($apart);
            $detour = (int) round($viaFirst - $direct);
            if ($detour > $cfg['max_delay']) {
                return "the extra stop would add about {$detour} minutes (limit {$cfg['max_delay']})";
            }
        }

        return null;
    }

    /** Kitchen minutes still needed for an order (0 once marked ready). */
    public static function prepLeft(RestaurantOrder $order, Ride $ride): int
    {
        if ($order->status === 'ready_for_pickup') {
            return 0;
        }
        $prep = (int) $order->items->max(fn ($i) => (int) ($i->restaurantItem?->preparation_time ?? 0));

        return self::prepLeftFrom($prep > 0 ? $prep : self::DEFAULT_PREP_MINUTES, $ride->requested_at);
    }

    /** $acceptedAt: when the kitchen started (the restaurant accepted). */
    public static function prepLeftFrom(int $prepMinutes, $acceptedAt): int
    {
        $elapsed = $acceptedAt ? (int) floor($acceptedAt->diffInSeconds(now()) / 60) : 0;

        return max(0, $prepMinutes - $elapsed);
    }

    public static function defaultPrepMinutes(): int
    {
        return self::DEFAULT_PREP_MINUTES;
    }

    private static function minutes(float $km): float
    {
        return $km * self::ROAD_FACTOR * self::MINUTES_PER_KM;
    }

    private static function km($lat1, $lng1, $lat2, $lng2): float
    {
        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLng = deg2rad((float) $lng2 - (float) $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
