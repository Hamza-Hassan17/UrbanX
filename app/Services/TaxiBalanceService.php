<?php

namespace App\Services;

use App\Models\RiderCashLedger;

/**
 * Batch 2 Part 5 -- taxi driver outstanding balance. Reuses the Batch 1
 * Part 8 rider_cash_ledgers table (entry_type='owed', see the migration)
 * rather than a separate ledger system -- RiderCashService::
 * currentBalance()/recordSettlement() are already entry-type-agnostic
 * (a plain running total by rider_id), so only the "driver now owes
 * commission+SST on a cash fare" write and the taxi-specific limit are
 * new here. OfferDispatchService::findNextEligibleDriver references this
 * class by name (guarded with class_exists() until this file existed).
 */
class TaxiBalanceService
{
    public static function currentBalance(int $driverId): float
    {
        return RiderCashService::currentBalance($driverId);
    }

    /**
     * Called once, when a cash-paid taxi ride completes. $commissionPlusSst
     * comes straight from that ride's fare_breakdown snapshot (commission_
     * amount + sst_amount) -- never recalculated from current rates, same
     * "the snapshot is the only source of truth for a finished job"
     * principle every other part of this app follows.
     */
    public static function recordOwed(int $driverId, float $commissionPlusSst, string $referenceType, int $referenceId): RiderCashLedger
    {
        $newBalance = self::currentBalance($driverId) + $commissionPlusSst;

        return RiderCashLedger::create([
            'rider_id' => $driverId,
            'entry_type' => 'owed',
            'amount' => $commissionPlusSst,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    public static function isOverLimit(int $driverId): bool
    {
        return self::currentBalance($driverId) >= DispatchSettingsService::taxiOutstandingLimit();
    }
}
