<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\RideAnomaly;
use App\Models\RideLocationPing;
use App\Models\User;

/**
 * Flags two kinds of anomalies on an active ride, per the Live Ops brief:
 *   - wrong_direction: driver's actual travel bearing is >90 degrees off the
 *     straight-line pickup->dropoff bearing for 3 consecutive pings in a row.
 *     Sustained, not single-ping, so a legitimate turn around a block or one
 *     noisy GPS fix doesn't trigger a false alarm.
 *   - stale_gps: no ping received for 4+ minutes while the ride is still in
 *     an active status. Checked by a scheduled command (RideAnomalyScan),
 *     not here, since a driver who stops sending pings entirely can't be
 *     caught by logic that only runs when a ping arrives.
 *
 * One open (unresolved) anomaly per ride+type at a time -- once flagged, it
 * doesn't re-flag on every subsequent bad ping until resolved.
 */
class RideAnomalyDetector
{
    private const WRONG_DIRECTION_THRESHOLD_DEGREES = 90;
    private const WRONG_DIRECTION_CONSECUTIVE_PINGS = 3;
    private const STALE_GPS_MINUTES = 4;

    public function recordPing(Ride $ride, User $driver, float $latitude, float $longitude): RideLocationPing
    {
        $ping = RideLocationPing::create([
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        $this->checkWrongDirection($ride, $driver);

        return $ping;
    }

    private function checkWrongDirection(Ride $ride, User $driver): void
    {
        // Null-check, not falsy-check -- dropoff coordinates are stored as
        // strings, and "0" (a real, if unlikely, latitude/longitude) is falsy
        // in PHP, which silently skipped detection entirely on any such ride.
        if ($ride->dropoff_latitude === null || $ride->dropoff_longitude === null) {
            return;
        }

        // Order by id, not created_at -- pings recorded within the same
        // second (e.g. two calls in the same request cycle, or a clock with
        // 1s resolution) tie on created_at with no guaranteed insertion-order
        // tiebreak, which silently scrambled "recent" ordering in testing.
        $recentPings = RideLocationPing::where('ride_id', $ride->id)
            ->orderByDesc('id')
            ->take(self::WRONG_DIRECTION_CONSECUTIVE_PINGS + 1)
            ->get()
            ->reverse()
            ->values();

        if ($recentPings->count() < self::WRONG_DIRECTION_CONSECUTIVE_PINGS + 1) {
            return;
        }

        $expectedBearing = $this->bearing(
            (float) $ride->pickup_latitude,
            (float) $ride->pickup_longitude,
            (float) $ride->dropoff_latitude,
            (float) $ride->dropoff_longitude,
        );

        $allOffCourse = true;
        for ($i = 1; $i < $recentPings->count(); $i++) {
            $prev = $recentPings[$i - 1];
            $curr = $recentPings[$i];

            $travelBearing = $this->bearing(
                (float) $prev->latitude,
                (float) $prev->longitude,
                (float) $curr->latitude,
                (float) $curr->longitude,
            );

            $diff = $this->angleDifference($expectedBearing, $travelBearing);

            if ($diff <= self::WRONG_DIRECTION_THRESHOLD_DEGREES) {
                $allOffCourse = false;
                break;
            }
        }

        if (!$allOffCourse) {
            return;
        }

        $alreadyOpen = RideAnomaly::where('ride_id', $ride->id)
            ->where('type', 'wrong_direction')
            ->open()
            ->exists();

        if ($alreadyOpen) {
            return;
        }

        RideAnomaly::create([
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'type' => 'wrong_direction',
            'details' => sprintf(
                'Driver traveled >%d° off the expected pickup-to-dropoff bearing for %d consecutive pings.',
                self::WRONG_DIRECTION_THRESHOLD_DEGREES,
                self::WRONG_DIRECTION_CONSECUTIVE_PINGS
            ),
            'detected_at' => now(),
        ]);
    }

    /**
     * Called by the scheduled command, not on ping arrival -- this is the
     * "driver stopped sending pings altogether" case.
     */
    public function checkStaleGps(Ride $ride): void
    {
        $lastPing = RideLocationPing::where('ride_id', $ride->id)->latest('created_at')->first();

        if (!$lastPing) {
            return;
        }

        $minutesSinceLastPing = $lastPing->created_at->diffInMinutes(now());

        $alreadyOpen = RideAnomaly::where('ride_id', $ride->id)
            ->where('type', 'stale_gps')
            ->open()
            ->exists();

        if ($minutesSinceLastPing >= self::STALE_GPS_MINUTES) {
            if (!$alreadyOpen) {
                RideAnomaly::create([
                    'ride_id' => $ride->id,
                    'driver_id' => $lastPing->driver_id,
                    'type' => 'stale_gps',
                    'details' => sprintf('No location ping received for %d+ minutes.', self::STALE_GPS_MINUTES),
                    'detected_at' => now(),
                ]);
            }
        } elseif ($alreadyOpen) {
            // Pings resumed -- auto-resolve rather than requiring manual dismissal.
            RideAnomaly::where('ride_id', $ride->id)
                ->where('type', 'stale_gps')
                ->open()
                ->update(['resolved_at' => now()]);
        }
    }

    private function bearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);

        $y = sin($deltaLon) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($deltaLon);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    private function angleDifference(float $a, float $b): float
    {
        $diff = fmod(abs($a - $b), 360);
        return $diff > 180 ? 360 - $diff : $diff;
    }
}
