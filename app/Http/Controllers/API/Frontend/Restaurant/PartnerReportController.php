<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\EatsSupportInfo;
use App\Models\Faq;
use App\Models\Restaurant;
use App\Services\RestaurantReportService as Reports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Urban Partner app: Reports screen and Help & Support screen.
 */
class PartnerReportController extends Controller
{
    /** GET /restaurant/reports?period=today|week|month|year|custom&from=&to= */
    public function reports(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|in:today,week,month,year,custom',
            'from' => 'required_if:period,custom|nullable|date',
            'to' => 'required_if:period,custom|nullable|date|after_or_equal:from',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $restaurant = Restaurant::where('user_id', $request->user()->id)->first();
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            [$from, $to, $period] = $this->range($request);
            // The same-length period just before, for "vs previous period".
            $prevTo = $from->copy()->subSecond();
            $prevFrom = $prevTo->copy()->subSeconds($to->diffInSeconds($from));

            $now = Reports::totals($restaurant->id, $from, $to);
            $prev = Reports::totals($restaurant->id, $prevFrom, $prevTo);
            $orders = $now['_orders'];
            unset($now['_orders'], $prev['_orders']);

            return response()->json([
                'message' => 'Restaurant report',
                'period' => ['name' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
                'summary' => $now + [
                    'orders_change_percent' => Reports::change($now['orders'], $prev['orders']),
                    'net_payable_change_percent' => Reports::change($now['net_payable'], $prev['net_payable']),
                ],
                'order_counts' => Reports::statusCounts($restaurant->id, $from, $to) + ['delivered' => $now['orders']],
                'daily' => $this->daily($orders, $from, $to),
                'top_items' => $this->topItems($orders->pluck('id')),
                'busiest_hours' => $this->busiestHours($orders),
                'ratings' => Reports::ratings($restaurant->id, $from, $to),
                'payouts' => Reports::payouts($restaurant->id),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Reports failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** GET /restaurant/help: contact details and FAQs for restaurants. */
    public function help()
    {
        $info = EatsSupportInfo::first();

        return response()->json([
            'message' => 'Help & Support',
            'contact' => [
                'phone' => $info?->phone,
                'email' => $info?->email,
                'address' => $info?->address,
            ],
            'faqs' => Faq::where('is_active', true)->whereIn('audience', ['all', 'restaurant'])
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'question', 'answer']),
        ], Response::HTTP_OK);
    }

    private function range(Request $request): array
    {
        $period = $request->input('period', 'week');

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay(), $period],
            'month' => [now()->startOfMonth(), now()->endOfDay(), $period],
            'year' => [now()->startOfYear(), now()->endOfDay(), $period],
            'custom' => [Carbon::parse($request->from)->startOfDay(), Carbon::parse($request->to)->endOfDay(), $period],
            default => [now()->startOfWeek(), now()->endOfDay(), 'week'],
        };
    }

    /** One row per day in the range, for the chart (days with no orders included). */
    private function daily($orders, Carbon $from, Carbon $to): array
    {
        $byDay = $orders->groupBy(fn ($o) => $o->updated_at->toDateString());
        $days = [];
        // Long ranges (a year) would make hundreds of bars: cap at 62 days, else monthly.
        $monthly = $from->diffInDays($to) > 62;
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $group = $monthly
                ? $orders->filter(fn ($o) => $o->updated_at->format('Y-m') === $key)
                : ($byDay[$key] ?? collect());
            $days[] = [
                'date' => $key,
                'orders' => $group->count(),
                'food_sales' => round($group->sum(fn ($o) => Reports::money($o)['food_sales']), 2),
                'net_payable' => round($group->sum(fn ($o) => Reports::money($o)['net_payable']), 2),
            ];
            $monthly ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }
        return $days;
    }

    private function topItems($orderIds): array
    {
        if ($orderIds->isEmpty()) {
            return [];
        }
        return DB::table('restaurant_order_items')
            ->whereIn('order_id', $orderIds)
            ->select('item_name', DB::raw('SUM(quantity) as quantity'), DB::raw('SUM(total_price) as sales'))
            ->groupBy('item_name')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['name' => $r->item_name, 'quantity' => (int) $r->quantity, 'sales' => round((float) $r->sales, 2)])
            ->all();
    }

    private function busiestHours($orders): array
    {
        $counts = $orders->countBy(fn ($o) => (int) $o->updated_at->format('G'));
        $hours = [];
        foreach (range(0, 23) as $h) {
            $hours[] = ['hour' => $h, 'orders' => (int) ($counts[$h] ?? 0)];
        }
        return $hours;
    }
}
