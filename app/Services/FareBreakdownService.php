<?php

namespace App\Services;

use App\Models\SystemSetting;

/**
 * Splits a ride's gross fare into platform commission, SST (sales tax),
 * and the driver's net income, per the accounting breakdown:
 *
 *   Commission          = Gross x commission%
 *   SST on Commission   = Commission x sst%
 *   Remaining           = Gross - Commission - SST on Commission
 *   Driver's Income     = Remaining / (1 + sst%)   -- Remaining is treated
 *                          as tax-inclusive, so this backs the SST out of it
 *   SST on Ride Fare    = Remaining - Driver's Income
 */
class FareBreakdownService
{
    public static function commissionPercent(): float
    {
        return (float) (SystemSetting::first()?->driver_commission_percent ?? 7.63);
    }

    public static function sstPercent(): float
    {
        return (float) (SystemSetting::first()?->sst_percent ?? 5.00);
    }

    public static function calculate(float $grossFare): array
    {
        $commissionRate = self::commissionPercent() / 100;
        $sstRate = self::sstPercent() / 100;

        $commission = round($grossFare * $commissionRate, 2);
        $sstOnCommission = round($commission * $sstRate, 2);
        $remaining = round($grossFare - $commission - $sstOnCommission, 2);
        $driverIncome = round($remaining / (1 + $sstRate), 2);
        $sstOnRideFare = round($remaining - $driverIncome, 2);

        return [
            'gross_fare' => $grossFare,
            'commission' => $commission,
            'sst_on_commission' => $sstOnCommission,
            'remaining' => $remaining,
            'driver_income' => $driverIncome,
            'sst_on_ride_fare' => $sstOnRideFare,
            'income_percent' => $grossFare > 0 ? round($driverIncome / $grossFare * 100, 2) : 0,
        ];
    }
}
