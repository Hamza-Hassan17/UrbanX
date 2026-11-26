<?php

namespace App\Services;

use App\Models\RiderCashLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        return DB::transaction(function () use ($driverId, $commissionPlusSst, $referenceType, $referenceId) {
            $driver = User::lockForUpdate()->find($driverId);
            $balance = self::currentBalance($driverId);
            $entry = RiderCashLedger::create([
                'rider_id' => $driverId,
                'entry_type' => 'owed',
                'amount' => $commissionPlusSst,
                'balance_after' => round($balance + $commissionPlusSst, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            $limit = DispatchSettingsService::taxiOutstandingLimit();
            if ($driver && $balance < $limit && (float) $entry->balance_after >= $limit) {
                DB::afterCommit(function () use ($driver) {
                    try {
                        self::sendPaymentReminder($driver);
                    } catch (\Throwable $e) {
                        Log::error('Driver balance reminder failed', ['driver_id' => $driver->id, 'error' => $e->getMessage()]);
                    }
                });
            }

            return $entry;
        });
    }

    public static function sendPaymentReminder(User $driver): bool
    {
        $balance = self::currentBalance($driver->id);
        $limit = DispatchSettingsService::taxiOutstandingLimit();
        if ($balance < $limit) {
            return false;
        }

        app('notificationService')->notifyUsers(
            [$driver],
            'New ride offers paused',
            'Your outstanding balance is PKR ' . number_format($balance, 2)
                . '. To receive new ride offers, bring your balance below PKR ' . number_format($limit, 2)
                . '. Open Wallet to review your balance.'
        );

        return true;
    }

    public static function isOverLimit(int $driverId): bool
    {
        return self::currentBalance($driverId) >= DispatchSettingsService::taxiOutstandingLimit();
    }
}
