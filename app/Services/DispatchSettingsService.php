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
}
