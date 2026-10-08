<?php

namespace App\Services;

use App\Models\SystemSetting;

/**
 * Splits a ride's gross fare into platform commission, SST (sales tax),
 * and the driver's net income, per the accounting breakdown:
 *
 *   Commission              = Gross x commission%
 *   SST on Commission       = Commission x sst commission%
 *   Remaining               = Gross - Commission - SST on Commission
 *   Driver's Income         = Remaining / (1 + sst ride fare%)  -- Remaining is
 *                             treated as tax-inclusive, so this backs the SST
 *                             on ride fare out of it
 *   SST on Ride Fare        = Remaining - Driver's Income
 */
class FareBreakdownService
{
    public static function commissionPercent(): float
    {
        return (float) (SystemSetting::first()?->driver_commission_percent ?? 7.63);
    }

    public static function sstCommissionPercent(): float
    {
        return (float) (SystemSetting::first()?->sst_percent ?? 5.00);
    }

    public static function sstRideFarePercent(): float
    {
        return (float) (SystemSetting::first()?->sst_ride_fare_percent ?? 5.00);
    }

    public static function restaurantCommissionPercent(): float
    {
        return (float) (SystemSetting::first()?->restaurant_commission_percent ?? 15.00);
    }

    /**
     * Platform's cut of a delivery fee (the delivery-rider equivalent of
     * commissionPercent(), which is taxi-ride-fare specific).
     */
    public static function platformSharePercent(): float
    {
        return (float) (SystemSetting::first()?->platform_share_percent ?? 20.00);
    }

    public static function riderCashLimit(): float
    {
        return (float) (SystemSetting::first()?->rider_cash_limit ?? 5000.00);
    }

    public static function foodDeliveryFeeSettings(): array
    {
        $settings = SystemSetting::first();
        return [
            'first_km_fee' => (float) ($settings?->food_first_km_fee ?? 150.00),
            'per_km_fee' => (float) ($settings?->food_per_km_fee ?? 45.00),
            'max_distance_km' => (int) ($settings?->food_max_distance_km ?? 4),
        ];
    }

    public static function parcelDeliveryFeeSettings(): array
    {
        $settings = SystemSetting::first();
        return [
            'first_km_fee' => (float) ($settings?->parcel_first_km_fee ?? 150.00),
            'per_km_fee' => (float) ($settings?->parcel_per_km_fee ?? 45.00),
            'max_distance_km' => (int) ($settings?->parcel_max_distance_km ?? 4),
        ];
    }

    /**
     * $commissionPercent lets callers swap in platformSharePercent() for
     * delivery riders instead of the taxi-specific commissionPercent() --
     * the rest of the breakdown (SST on commission, SST on remaining
     * income) is identical math for both, per the brief's shared SST rate.
     */
    public static function calculate(float $grossFare, ?float $commissionPercent = null): array
    {
        $commissionRate = ($commissionPercent ?? self::commissionPercent()) / 100;
        $sstCommissionRate = self::sstCommissionPercent() / 100;
        $sstRideFareRate = self::sstRideFarePercent() / 100;

        $commission = round($grossFare * $commissionRate, 2);
        $sstOnCommission = round($commission * $sstCommissionRate, 2);
        $remaining = round($grossFare - $commission - $sstOnCommission, 2);
        $driverIncome = round($remaining / (1 + $sstRideFareRate), 2);
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
