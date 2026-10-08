<?php

namespace App\Services;

use App\Models\RiderCashLedger;
use App\Models\User;

/**
 * Batch 1 Part 8 -- cash-in-hand tracking. All writes go through here so
 * balance_after is always computed from the rider's actual last balance,
 * never passed in by a caller.
 */
class RiderCashService
{
    public static function currentBalance(int $riderId): float
    {
        $last = RiderCashLedger::where('rider_id', $riderId)->latest('id')->first();
        return $last ? (float) $last->balance_after : 0.0;
    }

    public static function recordCollected(int $riderId, float $amount, ?string $referenceType = null, ?int $referenceId = null): RiderCashLedger
    {
        $newBalance = self::currentBalance($riderId) + $amount;

        return RiderCashLedger::create([
            'rider_id' => $riderId,
            'entry_type' => 'collected',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    public static function recordSettlement(int $riderId, float $amount, string $method, ?string $note, int $recordedBy): RiderCashLedger
    {
        $newBalance = self::currentBalance($riderId) - $amount;

        return RiderCashLedger::create([
            'rider_id' => $riderId,
            'entry_type' => 'settled',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'method' => $method,
            'note' => $note,
            'recorded_by' => $recordedBy,
        ]);
    }

    public static function isOverLimit(int $riderId): bool
    {
        return self::currentBalance($riderId) >= FareBreakdownService::riderCashLimit();
    }
}
