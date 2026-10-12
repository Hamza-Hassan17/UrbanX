<?php

namespace App\Services;

use App\Models\SystemSetting;

/**
 * Batch 2 Part 1 -- Dispatch settings (offer timeout, re-offer, stale
 * thresholds, taxi outstanding limit). Same single-settings-row pattern
 * as FareBreakdownService's getters.
 */
class DispatchSettingsService
{
    public static function offerAcceptanceTimeoutSeconds(): int
    {
        return (int) (SystemSetting::first()?->offer_acceptance_timeout_seconds ?? 30);
    }

    public static function maxReofferAttempts(): int
    {
        return (int) (SystemSetting::first()?->max_reoffer_attempts ?? 5);
    }

    public static function staleUnassignedMinutes(): int
    {
        return (int) (SystemSetting::first()?->stale_unassigned_minutes ?? 5);
    }

    public static function staleRestaurantAcceptMinutes(): int
    {
        return (int) (SystemSetting::first()?->stale_restaurant_accept_minutes ?? 5);
    }

    public static function staleReadyForPickupMinutes(): int
    {
        return (int) (SystemSetting::first()?->stale_ready_for_pickup_minutes ?? 10);
    }

    public static function taxiOutstandingLimit(): float
    {
        return (float) (SystemSetting::first()?->taxi_outstanding_limit ?? 5000.00);
    }

    /**
     * Single switch for every driver-verification gate in the app --
     * flip this one line to `true` to re-enable all of them at once:
     *   - Customer\RideController::notifyNearbyDrivers() (taxi push)
     *   - Driver\RideController::notifyNearbyDrivers() (parcel/delivery push)
     *   - Driver\RideController::getLatestRides() (poll)
     *   - Driver\RideController::acceptRide() (direct accept)
     *   - DriverDetailsController::updateDriverStatus() (go-online)
     *   - OfferDispatchService::findNextEligibleDriver() (Batch 2 Part 2
     *     automatic re-offer -- added alongside this switch, not a
     *     separate TEMP comment, so it can never drift out of sync with
     *     the other five)
     *
     * Disabled since 2026-09-19 (see the TEMP comments at each site
     * above) to isolate a mobile-testing issue. DO NOT ship to
     * production with this still false -- unverified drivers/riders
     * must not be reachable by customers or offered jobs, per the KYC
     * requirement. See docs/mobile-handoff-batch2.md's deploy steps for
     * the exact pre-launch checklist.
     */
    public static function driverVerificationEnabled(): bool
    {
        return false;
    }

    /**
     * Review follow-up -- default true (on), so accept-ride keeps
     * accepting a direct tap with no prior poll (e.g. straight from a
     * push notification), same as it always has. Switch off once
     * updated app builds -- which actually poll and see the pending
     * offer -- are rolled out; see the migration's own comment.
     */
    public static function allowLegacyDirectAccept(): bool
    {
        return (bool) (SystemSetting::first()?->allow_legacy_direct_accept ?? true);
    }
}
