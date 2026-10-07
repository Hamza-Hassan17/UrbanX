<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Time-window + status filtering shared by the Rides and Delivery queue
 * endpoints (Phase 2 of the workspace split). Both queues sit on the same
 * `rides` table -- just a different ride_type -- so the From/Window/Until
 * and dispatch/booked/completed/cancelled logic only needs to live once.
 * Callers apply their own ride_type (or any other) constraint on the
 * query BEFORE calling apply(); this only adds the time/status pieces.
 */
class RideQueueFilterService
{
    public const ACTIVE_STATUSES = ['requested', 'accepted', 'en_route', 'arrived', 'started'];

    public const EFFECTIVE_PICKUP_SQL = 'COALESCE(scheduled_pickup_at, requested_at)';

    public static function apply(Builder $query, Request $request): Builder
    {
        $tz = 'Asia/Karachi';
        $from = $request->filled('from')
            ? \Carbon\Carbon::parse($request->from, $tz)->utc()
            : now();

        $until = $request->filled('until')
            ? \Carbon\Carbon::parse($request->until, $tz)->utc()
            : null;

        $window = $request->input('window', '4');
        if (!$until && $window !== 'all') {
            $until = $from->copy()->addHours((int) $window);
        }

        $effective = self::EFFECTIVE_PICKUP_SQL;
        $active = self::ACTIVE_STATUSES;

        $query->when($until, fn ($q) => $q->where(function ($q) use ($from, $until, $effective, $active) {
            $q->whereRaw("$effective BETWEEN ? AND ?", [$from, $until])
                ->orWhere(function ($q) use ($from, $effective, $active) {
                    $q->whereIn('status', $active)
                        ->whereRaw("$effective < ?", [$from])
                        ->whereRaw("$effective >= ?", [$from->copy()->subHours(24)]);
                });
        }), fn ($q) => $q->whereRaw("$effective >= ?", [$from]));

        match ($request->status) {
            'dispatch' => $query->where('status', 'requested')->whereNull('driver_id'),
            'booked' => $query->whereIn('status', $active)->whereNotNull('driver_id'),
            'completed' => $query->where('status', 'completed'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query->whereIn('status', array_merge($active, ['completed', 'cancelled'])),
        };

        return $query->orderByRaw("$effective ASC");
    }
}
