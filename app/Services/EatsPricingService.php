<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\SystemSetting;

/**
 * Urban Eats customer charges set on Pricing & Fees (platform fee, small
 * order fee) and the delivery time estimate shown in cart and order screens.
 */
class EatsPricingService
{
    /** Kitchen time when no item in the order has its own preparation time. */
    private const DEFAULT_PREP_MINUTES = 20;
    /** City bike speed, about 20 km/h. */
    private const MINUTES_PER_KM = 3;
    /** Width of the "20-30 mins" range shown to customers. */
    private const RANGE_MINUTES = 10;

    public static function platformFee(): float
    {
        return round((float) (SystemSetting::first()?->eats_platform_fee ?? 0), 2);
    }

    /** Charged when the food subtotal is below the threshold (0 = off). */
    public static function smallOrderFee(float $foodSubtotal): float
    {
        $settings = SystemSetting::first();
        $threshold = (float) ($settings?->small_order_threshold ?? 0);
        if ($threshold <= 0 || $foodSubtotal >= $threshold) {
            return 0.0;
        }
        return round((float) ($settings?->small_order_fee ?? 0), 2);
    }

    public static function smallOrderThreshold(): float
    {
        return (float) (SystemSetting::first()?->small_order_threshold ?? 0);
    }

    /**
     * @param iterable $menuItems RestaurantItem models in the order
     * @return array{min: int, max: int, label: string}
     */
    public static function eta(Restaurant $restaurant, iterable $menuItems, $lat = null, $lng = null): array
    {
        $prep = 0;
        foreach ($menuItems as $item) {
            $prep = max($prep, (int) ($item?->preparation_time ?? 0));
        }
        $prep = $prep > 0 ? $prep : self::DEFAULT_PREP_MINUTES;

        $travel = 0;
        if ($lat !== null && $lng !== null && $restaurant->latitude && $restaurant->longitude) {
            $travel = (int) ceil(self::km($restaurant->latitude, $restaurant->longitude, $lat, $lng) * self::MINUTES_PER_KM);
        }

        $min = $prep + $travel;
        $max = $min + self::RANGE_MINUTES;

        return ['min' => $min, 'max' => $max, 'label' => "{$min}-{$max} mins"];
    }

    private static function km($lat1, $lng1, $lat2, $lng2): float
    {
        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLng = deg2rad((float) $lng2 - (float) $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($dLng / 2) ** 2;
        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
