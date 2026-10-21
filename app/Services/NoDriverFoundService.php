<?php

namespace App\Services;

use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideOffer;
use Illuminate\Support\Facades\Log;

/**
 * Batch 3 Part 6 -- "No driver found" handling.
 *
 * SWITCHED OFF UNTIL THE CLIENT CONFIRMS: appliesTo() is false whenever
 * the Dispatch setting "No-driver handling" is Off (the default), so with
 * it off every caller behaves exactly as before -- the job stays
 * 'requested' and only raises a stale alert.
 *
 * When On, for taxi rides and parcels only: once max re-offer attempts are
 * reached or nobody eligible is left, the job becomes 'no_driver_found',
 * the customer's ride channel gets a ride.status.updated broadcast and a
 * push, and the customer can retry (re-run dispatch) or cancel. Food
 * orders never auto-fail -- after the restaurant accepts they keep the
 * stale alert for an admin to assign a rider manually, and before
 * acceptance no rider dispatch runs at all, so there is nothing to fail.
 */
class NoDriverFoundService
{
    public static function appliesTo(Ride $ride): bool
    {
        if (!DispatchSettingsService::noDriverHandlingEnabled()) {
            return false;
        }

        if ($ride->status !== 'requested' || $ride->driver_id) {
            return false;
        }

        if ($ride->ride_type === 'ride') {
            return true;
        }

        // Delivery: parcels only (a food job has a restaurant_orders row).
        return $ride->ride_type === 'delivery' && !RestaurantOrder::where('ride_id', $ride->id)->exists();
    }

    public static function attemptsExhausted(Ride $ride): bool
    {
        return (int) $ride->reoffer_attempts >= DispatchSettingsService::maxReofferAttempts();
    }

    public static function markNoDriverFound(Ride $ride): void
    {
        $ride->status = 'no_driver_found';
        $ride->no_driver_found_at = now();
        $ride->save();

        Log::info('Ride marked no_driver_found', ['ride_id' => $ride->id, 'attempts' => $ride->reoffer_attempts]);

        try {
            broadcast(new \App\Events\RideStatusUpdated($ride));
        } catch (\Throwable $e) {
            Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
        }

        if ($ride->passenger) {
            try {
                app('notificationService')->notifyUsers(
                    [$ride->passenger],
                    'No drivers available right now',
                    'We could not find a driver for your ' . ($ride->ride_type === 'delivery' ? 'parcel' : 'ride') . '. You can try again or cancel.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );
            } catch (\Throwable $e) {
                Log::error('No-driver push failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Customer retry: back to 'requested' with a fresh dispatch window.
     * Earlier offers are marked superseded (kept for history/reports) so the
     * same drivers can be offered the job again.
     */
    public static function retry(Ride $ride): bool
    {
        RideOffer::where('ride_id', $ride->id)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);

        $ride->status = 'requested';
        $ride->requested_at = now();
        $ride->dispatch_restarted_at = now();
        $ride->no_driver_found_at = null;
        $ride->reoffer_attempts = 0;
        $ride->save();

        try {
            broadcast(new \App\Events\RideStatusUpdated($ride));
        } catch (\Throwable $e) {
            Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
        }

        Log::info('Dispatch restarted by customer', ['ride_id' => $ride->id]);

        return OfferDispatchService::offerToNextDriver($ride->fresh());
    }
}
