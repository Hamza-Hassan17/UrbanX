<?php

namespace App\Services;

use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideDriverLog;
use App\Models\RideOffer;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Food delivery jobs go to one rider at a time, nearest to the restaurant
 * first. Each rider has a time limit to accept; a rejection or no answer
 * passes the job to the next nearest rider. When every nearby rider has
 * been tried, admins are alerted and a new round starts from the nearest
 * again, up to the configured number of rounds.
 *
 * "Nearby" = online riders within the pickup radius whose app asked for
 * jobs in the last ACTIVE_SECONDS (a rider whose app is closed is skipped
 * instead of holding the job).
 */
class FoodDispatchService
{
    private const ACTIVE_SECONDS = 90;
    /** Stop offering a job this long after the restaurant accepted it. */
    private const MAX_AGE_MINUTES = 60;
    private const ORDER_STATUSES = ['accepted', 'preparing', 'ready_for_pickup'];

    public static function timeoutSeconds(): int
    {
        return max(10, (int) (SystemSetting::first()?->rider_offer_timeout_seconds ?? 60));
    }

    public static function maxRounds(): int
    {
        return max(1, (int) (SystemSetting::first()?->rider_offer_rounds ?? 3));
    }

    private static function radiusKm(): float
    {
        return (float) FareBreakdownService::foodDeliveryFeeSettings()['max_distance_km'];
    }

    /** The job this rider should see right now, or null. */
    public static function offerFor(User $rider, int $vehicleTypeId): ?Ride
    {
        $rider->forceFill(['last_job_poll_at' => now()])->saveQuietly();
        if ($rider->lat === null || $rider->lang === null) {
            return null;
        }

        $candidates = self::openJobs()
            ->where('vehicle_type_id', $vehicleTypeId)
            ->get()
            ->filter(fn ($ride) => self::km($rider->lat, $rider->lang, $ride->pickup_latitude, $ride->pickup_longitude) <= self::radiusKm())
            ->sortBy(fn ($ride) => self::km($rider->lat, $rider->lang, $ride->pickup_latitude, $ride->pickup_longitude));

        foreach ($candidates as $ride) {
            // Two passes: if this rider's poll finishes a round, look again
            // in the new round straight away.
            for ($pass = 0; $pass < 2; $pass++) {
                $state = self::state($ride);

                if ($state['active']) {
                    if ((int) $state['active']->driver_id === (int) $rider->id) {
                        return $ride;
                    }
                    continue 2;
                }

                $pool = $state['pool'];
                if ($pool->isEmpty()) {
                    if ($state['tried']->isNotEmpty() && self::endRound($ride)) {
                        continue;
                    }
                    continue 2;
                }

                if ((int) $pool->first()->id === (int) $rider->id) {
                    RideDriverLog::create([
                        'ride_id' => $ride->id,
                        'driver_id' => $rider->id,
                        'action' => 'sent',
                        'round' => $ride->offer_round,
                        'note' => "Offered (round {$ride->offer_round})",
                    ]);
                    return $ride;
                }
                continue 2;
            }
        }

        return null;
    }

    /**
     * Every-minute safety net: finish rounds where nobody nearby is left to
     * try (e.g. the last rider went offline), so admins still get alerted.
     */
    public static function sweep(): int
    {
        $ended = 0;
        foreach (self::openJobs()->get() as $ride) {
            $state = self::state($ride);
            if (!$state['active'] && $state['pool']->isEmpty() && $state['tried']->isNotEmpty() && self::endRound($ride)) {
                $ended++;
            }
        }
        return $ended;
    }

    private static function openJobs()
    {
        return Ride::where('ride_type', 'delivery')
            ->where('status', 'requested')
            ->where('offer_round', '<=', self::maxRounds())
            ->where('requested_at', '>=', now()->subMinutes(self::MAX_AGE_MINUTES))
            ->whereIn('id', RestaurantOrder::whereIn('status', self::ORDER_STATUSES)->whereNotNull('ride_id')->select('ride_id'))
            // A rider has already taken it (the customer side may still be confirming).
            ->whereNotIn('id', RideOffer::where('status', 'accepted')->select('ride_id'));
    }

    /** @return array{active: ?RideDriverLog, tried: Collection, pool: Collection} */
    private static function state(Ride $ride): array
    {
        $logs = RideDriverLog::where('ride_id', $ride->id)->where('round', $ride->offer_round)->get();
        $cutoff = now()->subSeconds(self::timeoutSeconds());

        $active = $logs->first(fn ($l) => $l->action === 'sent' && $l->updated_at->gt($cutoff));
        $tried = $logs->pluck('driver_id')->unique();

        $pool = User::role('rider')
            ->where('online_status', '!=', 'offline')
            ->where('last_job_poll_at', '>=', now()->subSeconds(self::ACTIVE_SECONDS))
            ->whereNotNull('lat')->whereNotNull('lang')
            ->whereNotIn('id', $tried)
            ->whereHas('driverVehicle', fn ($q) => $q->where('vehicle_type_id', $ride->vehicle_type_id))
            ->get(['id', 'lat', 'lang'])
            ->map(function ($u) use ($ride) {
                $u->setAttribute('km', self::km($u->lat, $u->lang, $ride->pickup_latitude, $ride->pickup_longitude));
                return $u;
            })
            ->filter(fn ($u) => $u->km <= self::radiusKm())
            ->sortBy('km')
            ->values();

        return ['active' => $active, 'tried' => $tried, 'pool' => $pool];
    }

    /** Alert admins and start the next round. Returns false once all rounds are used up. */
    private static function endRound(Ride $ride): bool
    {
        $order = RestaurantOrder::where('ride_id', $ride->id)->first();
        $label = $order ? "Order #{$order->order_number}" : "Delivery #{$ride->id}";
        $round = (int) $ride->offer_round;
        $max = self::maxRounds();
        $last = $round >= $max;

        $message = $last
            ? "{$label}: no rider accepted after {$max} round(s). Please assign a rider manually."
            : "{$label}: every nearby rider skipped or rejected it (round {$round} of {$max}). Offering again from the nearest rider.";

        StaleJobAlertService::raise(
            $last ? 'no_rider_after_all_rounds' : "rider_round_{$round}_skipped",
            Ride::class,
            $ride->id,
            'delivery',
            $message
        );

        $ride->offer_round = $round + 1;
        $ride->save();

        return !$last;
    }

    private static function km($lat1, $lng1, $lat2, $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLng = deg2rad((float) $lng2 - (float) $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
