<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-customer history across every service (taxi rides, parcels, food
 * orders, chauffeur rentals) and the customer groups built from it:
 *
 *   top      -- orders at least the average (and 3+), ordered recently
 *   at_risk  -- orders at least the average (and 3+), but nothing for
 *               "at risk days" (System setting, default 14): win them back
 *   new      -- first order in the last 30 days
 *   inactive -- nothing for 60+ days (and not a frequent customer)
 *   regular  -- everyone else who has ordered
 *   never    -- signed up, never ordered
 *
 * An "order" = a completed ride / parcel, a delivered food order or a
 * completed rental. Activity (first/last order) counts anything not
 * cancelled, so a customer whose last order is still in progress isn't
 * "slipping away".
 */
class CustomerInsightsService
{
    public const SERVICES = ['ride' => 'Taxi rides', 'parcel' => 'Parcels', 'food' => 'Food orders', 'rental' => 'Rentals'];
    public const GROUPS = [
        'top' => 'Top customers',
        'at_risk' => 'At risk',
        'new' => 'New',
        'regular' => 'Regular',
        'inactive' => 'Inactive',
        'never' => 'Never ordered',
    ];
    private const NEW_DAYS = 30;
    private const INACTIVE_DAYS = 60;
    private const MIN_FREQUENT = 3;

    public static function atRiskDays(): int
    {
        return max(1, (int) (SystemSetting::first()?->customer_at_risk_days ?? 14));
    }

    /**
     * Aggregates per customer and service, in bulk.
     * @param int[]|null $userIds null = every customer
     * @return array<int, array> keyed by user id
     */
    public static function summaries(?array $userIds = null): array
    {
        $out = [];
        $add = function ($rows, string $service) use (&$out) {
            foreach ($rows as $r) {
                $u = (int) $r->uid;
                $out[$u] ??= self::blank();
                $s = &$out[$u]['services'][$service];
                $s['requested'] += (int) $r->requested;
                $s['completed'] += (int) $r->completed;
                $s['cancelled'] += (int) $r->cancelled;
                $s['spent'] += (float) $r->spent;
                foreach (['first' => 'first_at', 'last' => 'last_at'] as $col => $key) {
                    if ($r->$col) {
                        $t = Carbon::parse($r->$col);
                        $cur = $out[$u][$key];
                        if (!$cur || ($key === 'first_at' ? $t->lt($cur) : $t->gt($cur))) {
                            $out[$u][$key] = $t;
                        }
                    }
                }
                unset($s);
            }
        };

        $filter = fn ($q, $col) => $userIds === null ? $q : $q->whereIn($col, $userIds ?: [0]);

        // Taxi rides and parcels (food delivery rides are counted as food orders instead).
        foreach (['ride', 'parcel'] as $type) {
            $q = DB::table('rides')->where('ride_type', $type)->whereNotNull('passenger_id')
                ->selectRaw("passenger_id as uid, count(*) as requested,
                    sum(status = 'completed') as completed,
                    sum(status = 'cancelled') as cancelled,
                    sum(case when status = 'completed' then coalesce(total_fare, 0) else 0 end) as spent,
                    min(case when status <> 'cancelled' then coalesce(requested_at, created_at) end) as first,
                    max(case when status <> 'cancelled' then coalesce(requested_at, created_at) end) as last")
                ->groupBy('passenger_id');
            $add($filter($q, 'passenger_id')->get(), $type);
        }

        $q = DB::table('restaurant_orders')->whereNotNull('customer_id')
            ->selectRaw("customer_id as uid, count(*) as requested,
                sum(status in ('delivered', 'completed')) as completed,
                sum(status in ('cancelled', 'rejected')) as cancelled,
                sum(case when status in ('delivered', 'completed') then coalesce(total_price, 0) else 0 end) as spent,
                min(case when status not in ('cancelled', 'rejected') then created_at end) as first,
                max(case when status not in ('cancelled', 'rejected') then created_at end) as last")
            ->groupBy('customer_id');
        $add($filter($q, 'customer_id')->get(), 'food');

        $q = DB::table('bookings')->whereNotNull('user_id')
            ->selectRaw("user_id as uid, count(*) as requested,
                sum(status = 'completed') as completed,
                sum(status = 'cancelled') as cancelled,
                sum(case when status = 'completed' then coalesce(total_amount, 0) else 0 end) as spent,
                min(case when status <> 'cancelled' then created_at end) as first,
                max(case when status <> 'cancelled' then created_at end) as last")
            ->groupBy('user_id');
        $add($filter($q, 'user_id')->get(), 'rental');

        foreach ($out as &$c) {
            $c['orders'] = array_sum(array_column($c['services'], 'completed'));
            $c['requested'] = array_sum(array_column($c['services'], 'requested'));
            $c['cancelled'] = array_sum(array_column($c['services'], 'cancelled'));
            $c['spent'] = round(array_sum(array_column($c['services'], 'spent')), 2);
            $c['avg_order'] = $c['orders'] > 0 ? round($c['spent'] / $c['orders'], 2) : 0;
        }

        return $out;
    }

    /** Average completed orders per customer who has ordered at least once. */
    public static function averageOrders(?array $all = null): float
    {
        $all ??= self::summaries();
        $ordered = array_filter($all, fn ($c) => $c['orders'] > 0);

        return $ordered ? round(array_sum(array_column($ordered, 'orders')) / count($ordered), 2) : 0;
    }

    public static function groupOf(?array $c, float $average, ?Carbon $signedUp = null): string
    {
        if (!$c || (!$c['last_at'] && $c['orders'] === 0)) {
            return 'never';
        }
        $daysSince = $c['last_at'] ? (int) $c['last_at']->diffInDays(now()) : 9999;
        $frequent = $c['orders'] >= max(self::MIN_FREQUENT, $average);

        if ($frequent) {
            return $daysSince >= self::atRiskDays() ? 'at_risk' : 'top';
        }
        if ($c['first_at'] && $c['first_at']->gte(now()->subDays(self::NEW_DAYS))) {
            return 'new';
        }
        if ($daysSince >= self::INACTIVE_DAYS) {
            return 'inactive';
        }

        return 'regular';
    }

    /** Everything the customer page shows. */
    public static function forCustomer(int $userId): array
    {
        $c = self::summaries([$userId])[$userId] ?? self::blank();
        $all = self::summaries();
        $average = self::averageOrders($all);

        return [
            'summary' => $c,
            'average_orders' => $average,
            'group' => self::groupOf($c, $average),
            'days_since_last' => $c['last_at'] ? (int) $c['last_at']->diffInDays(now()) : null,
            'monthly' => self::monthly($userId),
        ];
    }

    /** Orders (not cancelled) per month and service, last 12 months. */
    public static function monthly(int $userId): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[now()->startOfMonth()->subMonths($i)->format('Y-m')] = array_fill_keys(array_keys(self::SERVICES), 0);
        }
        $since = now()->startOfMonth()->subMonths(11);
        $fill = function ($rows, string $service) use (&$months) {
            foreach ($rows as $r) {
                if (isset($months[$r->ym])) {
                    $months[$r->ym][$service] = (int) $r->n;
                }
            }
        };

        foreach (['ride', 'parcel'] as $type) {
            $fill(DB::table('rides')->where('passenger_id', $userId)->where('ride_type', $type)->where('status', '<>', 'cancelled')
                ->whereRaw('coalesce(requested_at, created_at) >= ?', [$since])
                ->selectRaw("date_format(coalesce(requested_at, created_at), '%Y-%m') as ym, count(*) as n")->groupBy('ym')->get(), $type);
        }
        $fill(DB::table('restaurant_orders')->where('customer_id', $userId)->whereNotIn('status', ['cancelled', 'rejected'])
            ->where('created_at', '>=', $since)
            ->selectRaw("date_format(created_at, '%Y-%m') as ym, count(*) as n")->groupBy('ym')->get(), 'food');
        $fill(DB::table('bookings')->where('user_id', $userId)->where('status', '<>', 'cancelled')
            ->where('created_at', '>=', $since)
            ->selectRaw("date_format(created_at, '%Y-%m') as ym, count(*) as n")->groupBy('ym')->get(), 'rental');

        return $months;
    }

    private static function blank(): array
    {
        return [
            'services' => array_fill_keys(array_keys(self::SERVICES), ['requested' => 0, 'completed' => 0, 'cancelled' => 0, 'spent' => 0.0]),
            'orders' => 0, 'requested' => 0, 'cancelled' => 0, 'spent' => 0.0, 'avg_order' => 0,
            'first_at' => null, 'last_at' => null,
        ];
    }
}
