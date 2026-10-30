<?php

namespace App\Services;

use App\Models\DriverOnlineSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Batch 3 Part 5 -- driver (taxi) and rider (delivery) performance from
 * existing data. One grouped query per metric source, merged per driver.
 *
 * Definitions:
 * - offers received/accepted/rejected/expired: ride_offers rows for the
 *   driver created in the range, by status (pending ones count as received
 *   only). Acceptance rate = accepted / (accepted + rejected + expired).
 * - cancellations after accepting: distinct jobs the driver cancelled after
 *   taking them -- a ride_driver_logs 'cancelled' row, or a ride cancelled
 *   with status_updated_by_role = driver while assigned to them.
 * - completed jobs, avg pickup time (accepted -> started) and avg trip /
 *   delivery time (started -> completed): completed rides in the range.
 * - average rating: driver_reviews left in the range.
 * - online hours: driver_online_sessions overlapping the range (only for
 *   drivers using the Part 4 online toggle; others show null).
 */
class PerformanceReportService
{
    public const COLUMNS = [
        'name' => 'Name',
        'offers_received' => 'Offers Received',
        'offers_accepted' => 'Accepted',
        'offers_rejected' => 'Rejected',
        'offers_expired' => 'Expired',
        'acceptance_rate' => 'Acceptance Rate (%)',
        'cancellations' => 'Cancelled After Accepting',
        'completed_jobs' => 'Completed Jobs',
        'avg_rating' => 'Avg Rating',
        'avg_pickup_minutes' => 'Avg Pickup Time (min)',
        'avg_trip_minutes' => 'Avg Trip/Delivery Time (min)',
        'online_hours' => 'Online Hours',
    ];

    /**
     * @param string $scope 'ride' (taxi drivers) or 'delivery' (riders)
     */
    public static function build(string $scope, Carbon $from, Carbon $to): Collection
    {
        $isDelivery = $scope === 'delivery';
        // Taxi drivers do rides and (since the move) parcels.
        $rideTypes = $isDelivery ? ['delivery'] : \App\Models\Ride::TAXI_TYPES;

        $drivers = User::role($isDelivery ? 'rider' : 'driver')
            ->with('profile:id,user_id,city')
            ->get(['id', 'name', 'phone', 'online_status', 'driver_status']);

        if ($drivers->isEmpty()) {
            return collect();
        }

        $ids = $drivers->pluck('id')->all();

        $offers = DB::table('ride_offers')
            ->join('rides', 'rides.id', '=', 'ride_offers.ride_id')
            ->whereIn('rides.ride_type', $rideTypes)
            ->whereIn('ride_offers.driver_id', $ids)
            ->whereBetween('ride_offers.created_at', [$from, $to])
            ->groupBy('ride_offers.driver_id')
            ->selectRaw("ride_offers.driver_id,
                COUNT(*) AS received,
                SUM(ride_offers.status = 'accepted') AS accepted,
                SUM(ride_offers.status = 'rejected') AS rejected,
                SUM(ride_offers.status = 'expired') AS expired")
            ->get()
            ->keyBy('driver_id');

        $loggedCancels = DB::table('ride_driver_logs')
            ->join('rides', 'rides.id', '=', 'ride_driver_logs.ride_id')
            ->whereIn('rides.ride_type', $rideTypes)
            ->where('ride_driver_logs.action', 'cancelled')
            ->whereIn('ride_driver_logs.driver_id', $ids)
            ->whereBetween('ride_driver_logs.created_at', [$from, $to])
            ->select('ride_driver_logs.driver_id', 'ride_driver_logs.ride_id');
        $cancels = DB::table('rides')
            ->whereIn('ride_type', $rideTypes)
            ->where('status', 'cancelled')
            ->where('status_updated_by_role', 'driver')
            ->whereIn('driver_id', $ids)
            ->whereBetween('cancelled_at', [$from, $to])
            ->select('driver_id', 'id as ride_id')
            ->union($loggedCancels)
            ->get()
            ->groupBy('driver_id')
            ->map(fn ($rows) => $rows->pluck('ride_id')->unique()->count());

        $completed = DB::table('rides')
            ->whereIn('ride_type', $rideTypes)
            ->where('status', 'completed')
            ->whereIn('driver_id', $ids)
            ->whereBetween('completed_at', [$from, $to])
            ->groupBy('driver_id')
            ->selectRaw("driver_id,
                COUNT(*) AS completed_jobs,
                AVG(CASE WHEN accepted_at IS NOT NULL AND started_at IS NOT NULL AND started_at >= accepted_at
                    THEN TIMESTAMPDIFF(SECOND, accepted_at, started_at) END) / 60 AS avg_pickup_minutes,
                AVG(CASE WHEN started_at IS NOT NULL AND completed_at >= started_at
                    THEN TIMESTAMPDIFF(SECOND, started_at, completed_at) END) / 60 AS avg_trip_minutes")
            ->get()
            ->keyBy('driver_id');

        $ratings = DB::table('driver_reviews')
            ->whereIn('driver_id', $ids)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('driver_id')
            ->selectRaw('driver_id, AVG(rating) AS avg_rating, COUNT(*) AS reviews')
            ->get()
            ->keyBy('driver_id');

        $onlineSeconds = DriverOnlineSession::whereIn('driver_id', $ids)
            ->where('started_at', '<', $to)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $from))
            ->get(['driver_id', 'started_at', 'ended_at'])
            ->groupBy('driver_id')
            ->map(function ($sessions) use ($from, $to) {
                return $sessions->sum(function ($s) use ($from, $to) {
                    $start = $s->started_at->max($from);
                    $end = ($s->ended_at ?? now())->min($to);
                    return max(0, $end->getTimestamp() - $start->getTimestamp());
                });
            });

        return $drivers->map(function ($driver) use ($offers, $cancels, $completed, $ratings, $onlineSeconds) {
            $o = $offers->get($driver->id);
            $c = $completed->get($driver->id);
            $r = $ratings->get($driver->id);

            $accepted = (int) ($o->accepted ?? 0);
            $rejected = (int) ($o->rejected ?? 0);
            $expired = (int) ($o->expired ?? 0);
            $decided = $accepted + $rejected + $expired;

            return [
                'driver_id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'city' => $driver->profile->city ?? null,
                'presence' => $driver->presenceStatus(),
                'offers_received' => (int) ($o->received ?? 0),
                'offers_accepted' => $accepted,
                'offers_rejected' => $rejected,
                'offers_expired' => $expired,
                'acceptance_rate' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
                'cancellations' => (int) ($cancels->get($driver->id) ?? 0),
                'completed_jobs' => (int) ($c->completed_jobs ?? 0),
                'avg_rating' => $r ? round((float) $r->avg_rating, 2) : null,
                'reviews' => (int) ($r->reviews ?? 0),
                'avg_pickup_minutes' => isset($c->avg_pickup_minutes) ? round((float) $c->avg_pickup_minutes, 1) : null,
                'avg_trip_minutes' => isset($c->avg_trip_minutes) ? round((float) $c->avg_trip_minutes, 1) : null,
                'online_hours' => $onlineSeconds->has($driver->id)
                    ? round($onlineSeconds->get($driver->id) / 3600, 1)
                    : ($driver->online_status === null ? null : 0.0),
            ];
        })->values();
    }

    /**
     * Sort rows by a column; nulls always last.
     */
    public static function sort(Collection $rows, string $column, string $direction): Collection
    {
        if (!array_key_exists($column, self::COLUMNS)) {
            $column = 'completed_jobs';
        }
        $desc = $direction !== 'asc';

        return $rows->sort(function ($a, $b) use ($column, $desc) {
            $x = $a[$column];
            $y = $b[$column];
            if ($x === null && $y === null) {
                return 0;
            }
            if ($x === null) {
                return 1;
            }
            if ($y === null) {
                return -1;
            }
            $cmp = is_string($x) ? strcasecmp($x, $y) : ($x <=> $y);

            return $desc ? -$cmp : $cmp;
        })->values();
    }
}
