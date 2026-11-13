<?php

namespace App\Services;

use App\Models\RestaurantOrder;
use App\Models\RestaurantPayout;
use App\Models\RestaurantReview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A restaurant's own numbers for the Partner app (home screen and Reports).
 * Money comes from each order's saved snapshot, so it matches the admin's
 * Restaurant Payable report exactly.
 *
 *   food sales  = what the dishes sold for (before delivery fee, tips, fees)
 *   net payable = food sales - commission - SST - tax - restaurant-funded discounts
 */
class RestaurantReportService
{
    public const DELIVERED = ['delivered', 'completed'];

    /** Totals for delivered orders in [from, to]. */
    public static function totals(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $orders = RestaurantOrder::where('restaurant_id', $restaurantId)
            ->whereIn('status', self::DELIVERED)
            ->whereBetween('updated_at', [$from, $to])
            ->get(['id', 'subtotal', 'discount', 'discount_funded_by', 'fare_breakdown', 'updated_at']);

        $t = ['orders' => 0, 'food_sales' => 0.0, 'discounts' => 0.0, 'commission' => 0.0, 'sst' => 0.0, 'tax' => 0.0, 'net_payable' => 0.0];
        foreach ($orders as $o) {
            $m = self::money($o);
            $t['orders']++;
            foreach (['food_sales', 'discounts', 'commission', 'sst', 'tax', 'net_payable'] as $k) {
                $t[$k] += $m[$k];
            }
        }
        foreach (['food_sales', 'discounts', 'commission', 'sst', 'tax', 'net_payable'] as $k) {
            $t[$k] = round($t[$k], 2);
        }
        $t['average_order_value'] = $t['orders'] ? round($t['food_sales'] / $t['orders'], 2) : 0.0;
        $t['_orders'] = $orders;

        return $t;
    }

    /** One order's money, from its snapshot (older orders without one: no deductions known). */
    public static function money(RestaurantOrder $o): array
    {
        $fb = $o->fare_breakdown ?: [];
        $sales = (float) ($fb['subtotal'] ?? $o->subtotal);
        $discount = ($fb['discount_funded_by'] ?? $o->discount_funded_by) === 'restaurant' ? (float) ($fb['discount'] ?? $o->discount) : 0.0;
        $commission = (float) ($fb['commission_amount'] ?? 0);
        $sst = (float) ($fb['restaurant_sst_amount'] ?? 0);
        $tax = (float) ($fb['restaurant_tax_amount'] ?? 0);
        $net = isset($fb['restaurant_payable']) ? (float) $fb['restaurant_payable'] : $sales - $discount;

        return ['food_sales' => $sales, 'discounts' => $discount, 'commission' => $commission, 'sst' => $sst, 'tax' => $tax, 'net_payable' => $net];
    }

    /** Percent change from previous to current, or null when there's nothing to compare against. */
    public static function change(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }
        return round(($current - $previous) / $previous * 100, 1);
    }

    public static function statusCounts(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $rows = RestaurantOrder::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('count(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'placed' => (int) $rows->sum(),
            'cancelled' => (int) ($rows['cancelled'] ?? 0),
            'rejected' => (int) ($rows['rejected'] ?? 0),
            'in_progress' => (int) $rows->except(['cancelled', 'rejected', 'delivered', 'completed'])->sum(),
        ];
    }

    public static function ratings(int $restaurantId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $q = RestaurantReview::where('restaurant_id', $restaurantId)->where('is_active', 'active');
        if ($from && $to) {
            $q->whereBetween('created_at', [$from, $to]);
        }
        $reviews = $q->latest('id')->get(['rating', 'comment', 'created_at']);
        $breakdown = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $breakdown[(string) $star] = $reviews->where('rating', $star)->count();
        }

        return [
            'average' => $reviews->count() ? round($reviews->avg('rating'), 1) : 0.0,
            'count' => $reviews->count(),
            'breakdown' => $breakdown,
            'recent' => $reviews->filter(fn ($r) => filled($r->comment))->take(5)->map(fn ($r) => [
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'date' => $r->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** Paid so far vs everything earned (all time). */
    public static function payouts(int $restaurantId): array
    {
        $earned = self::totals($restaurantId, Carbon::create(2000), now())['net_payable'];
        $paid = (float) RestaurantPayout::where('restaurant_id', $restaurantId)->sum('payable_amount');
        $last = RestaurantPayout::where('restaurant_id', $restaurantId)->latest('paid_at')->first();

        return [
            'total_earned' => round($earned, 2),
            'total_paid' => round($paid, 2),
            'pending' => round(max(0, $earned - $paid), 2),
            'last_payout' => $last ? [
                'amount' => round((float) $last->payable_amount, 2),
                'paid_at' => $last->paid_at?->toIso8601String(),
                'period_start' => (string) $last->period_start,
                'period_end' => (string) $last->period_end,
            ] : null,
        ];
    }
}
