<?php

namespace App\Services;

use App\Models\DriverOnlineSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Batch 3 Part 4 -- online/offline state for drivers and delivery riders,
 * separate from busy/available. Every state change goes through here so
 * online sessions (for online hours in the performance reports) stay
 * consistent.
 */
class DriverPresenceService
{
    public static function goOnline(User $driver, ?float $latitude = null, ?float $longitude = null): void
    {
        if ($latitude !== null && $longitude !== null) {
            $driver->lat = $latitude;
            $driver->lang = $longitude;
            $driver->last_location_at = now();
        }

        if ($driver->online_status !== 'online') {
            $driver->online_status = 'online';
            $driver->online_status_changed_at = now();
            // Fresh start for auto-offline: going online counts as a sign of life.
            $driver->last_location_at = $driver->last_location_at && $driver->last_location_at->gt(now()->subMinute())
                ? $driver->last_location_at
                : now();

            DriverOnlineSession::where('driver_id', $driver->id)->whereNull('ended_at')
                ->update(['ended_at' => now(), 'ended_reason' => 'manual']);
            DriverOnlineSession::create(['driver_id' => $driver->id, 'started_at' => now()]);
        }

        $driver->save();
    }

    public static function goOffline(User $driver, string $reason = 'manual'): void
    {
        if ($driver->online_status !== 'offline') {
            $driver->online_status = 'offline';
            $driver->online_status_changed_at = now();
            $driver->save();
        }

        DriverOnlineSession::where('driver_id', $driver->id)->whereNull('ended_at')
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);

        Log::info('Driver went offline', ['driver_id' => $driver->id, 'reason' => $reason]);
    }

    /**
     * Record a location update (sign of life for auto-offline). Cheap:
     * a single column update without touching updated_at.
     */
    public static function touchLocation(User $driver): void
    {
        User::whereKey($driver->id)->update(['last_location_at' => now()]);
    }

    /**
     * Switches drivers who use the toggle (online_status = 'online') to
     * offline when they haven't sent a location update in $minutes.
     * Legacy drivers (NULL) are never touched. Returns how many.
     */
    public static function autoOfflineStale(int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        $cutoff = now()->subMinutes($minutes);

        $stale = User::where('online_status', 'online')
            ->where(function ($q) use ($cutoff) {
                $q->where('last_location_at', '<', $cutoff)
                    ->orWhere(fn ($q) => $q->whereNull('last_location_at')->where('online_status_changed_at', '<', $cutoff));
            })
            ->get();

        foreach ($stale as $driver) {
            self::goOffline($driver, 'auto_offline');

            try {
                app('notificationService')->notifyUsers(
                    [$driver],
                    'You are now offline',
                    'We switched you offline because we have not received your location for a while. Go online again to receive jobs.',
                    'driver_status',
                    $driver->id,
                    'driver_status'
                );
            } catch (\Throwable $e) {
                Log::error('Auto-offline notification failed', ['driver_id' => $driver->id, 'error' => $e->getMessage()]);
            }
        }

        return $stale->count();
    }

    public static function payload(User $driver): array
    {
        return [
            'online_status' => $driver->online_status ?? 'online',
            'uses_online_toggle' => $driver->online_status !== null,
            'driver_status' => $driver->driver_status,
            'presence' => $driver->presenceStatus(),
            'online_status_changed_at' => $driver->online_status_changed_at?->toIso8601String(),
            'last_location_at' => $driver->last_location_at?->toIso8601String(),
            'auto_offline_minutes' => DispatchSettingsService::autoOfflineMinutes(),
        ];
    }
}
