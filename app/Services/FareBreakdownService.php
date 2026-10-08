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

    /**
     * Distance is always charged rounded UP to the next whole km (3.4 km
     * bills as 4 km) -- see Pricing & Fees.
     */
    public static function roundUpToKm(float $distanceKm): int
    {
        return (int) ceil($distanceKm);
    }

    /**
     * Batch 1 Part 2 snapshot for a restaurant_orders row -- called once,
     * at order creation, and stored on fare_breakdown so later rate
     * changes never alter this order's history. Commission is always on
     * the full food price (subtotal), before any discount, per the brief.
     * The delivery leg (platform share / rider net) is split out
     * separately from the food leg (restaurant payable), since they're
     * paid to two different parties.
     */
    public static function buildOrderSnapshot(array $input): array
    {
        $subtotal = (float) $input['subtotal'];
        $discount = (float) ($input['discount'] ?? 0);
        $fundedBy = $input['discount_funded_by'] ?? null; // 'platform' | 'restaurant' | null
        $deliveryFee = (float) ($input['delivery_fee'] ?? 0);

        $commissionRate = self::restaurantCommissionPercent();
        $commissionAmount = round($subtotal * $commissionRate / 100, 2);
        $restaurantFundedDiscount = $fundedBy === 'restaurant' ? $discount : 0;
        $restaurantPayable = round($subtotal - $commissionAmount - $restaurantFundedDiscount, 2);

        $riderBreakdown = self::calculate($deliveryFee, self::platformSharePercent());

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'discount_funded_by' => $fundedBy,
            'distance_actual_km' => $input['distance_actual_km'] ?? null,
            'distance_charged_km' => $input['distance_charged_km'] ?? null,
            'delivery_fee' => $deliveryFee,
            'total_payable_by_customer' => round($subtotal - $discount + $deliveryFee, 2),
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'restaurant_payable' => $restaurantPayable,
            'platform_share_rate' => self::platformSharePercent(),
            'platform_share_amount' => $riderBreakdown['commission'],
            'rider_gross' => $deliveryFee,
            'sst_rate' => self::sstRideFarePercent(),
            'sst_amount' => round($riderBreakdown['sst_on_commission'] + $riderBreakdown['sst_on_ride_fare'], 2),
            'rider_net' => $riderBreakdown['driver_income'],
            'calculated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Batch 1 Part 2 snapshot for a rides row (taxi ride or parcel/food
     * delivery ride) -- called once, at the point status flips to
     * 'completed' (not at creation), since total_fare can still change
     * before then (wait penalty). $isDelivery picks platform_share_percent
     * over the taxi-only commission_percent.
     */
    public static function buildRideSnapshot(array $input): array
    {
        $subtotal = (float) $input['subtotal'];
        $isDelivery = (bool) ($input['is_delivery'] ?? false);
        $commissionRate = $isDelivery ? self::platformSharePercent() : self::commissionPercent();
        $breakdown = self::calculate($subtotal, $commissionRate);

        return [
            'subtotal' => $subtotal,
            'distance_actual_km' => $input['distance_actual_km'] ?? null,
            'distance_charged_km' => $input['distance_charged_km'] ?? null,
            'total_payable_by_customer' => $subtotal,
            'commission_rate' => $commissionRate,
            'commission_amount' => $breakdown['commission'],
            'rider_gross' => $subtotal,
            'sst_rate' => self::sstRideFarePercent(),
            'sst_amount' => round($breakdown['sst_on_commission'] + $breakdown['sst_on_ride_fare'], 2),
            'rider_net' => $breakdown['driver_income'],
            'calculated_at' => now()->toDateTimeString(),
        ];
    }
}
