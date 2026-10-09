<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\FinanceReportExport;
use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantPayout;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FareBreakdownService;
use App\Services\FinanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class FinanceController extends Controller
{
    public function taxCommission()
    {
        $this->authorize('export payroll');

        return view('dashboard.finance.tax-commission', [
            'commissionPercent' => FareBreakdownService::commissionPercent(),
            'sstCommissionPercent' => FareBreakdownService::sstCommissionPercent(),
            'sstRideFarePercent' => FareBreakdownService::sstRideFarePercent(),
        ]);
    }

    public function updateTaxCommission(Request $request)
    {
        if (!Gate::any(['update setting', 'create setting'])) {
            abort(403, 'Unauthorized');
        }

        $validator = Validator::make($request->all(), [
            'driver_commission_percent' => 'required|numeric|min:0|max:100',
            'sst_percent' => 'required|numeric|min:0|max:100',
            'sst_ride_fare_percent' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        try {
            $setting = SystemSetting::first() ?? new SystemSetting();
            $setting->driver_commission_percent = $request->driver_commission_percent;
            $setting->sst_percent = $request->sst_percent;
            $setting->sst_ride_fare_percent = $request->sst_ride_fare_percent;
            $setting->save();

            return redirect()->back()->with('success', 'Tax & Commission settings updated successfully');
        } catch (\Throwable $th) {
            Log::error('Tax & Commission settings update failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function reports(Request $request)
    {
        $this->authorize('export payroll');

        $drivers = User::role('driver')->orderBy('name')->get(['id', 'name', 'phone', 'is_active']);

        $report = null;
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $report = $this->buildReport($request);
        }

        return view('dashboard.finance.reports', compact('drivers', 'report'));
    }

    public function exportReportPdf(Request $request)
    {
        $this->authorize('export payroll');

        $report = $this->buildReport($request);

        $pdf = Pdf::loadView('dashboard.finance.reports-pdf', ['report' => $report])->setPaper('a4', 'landscape');

        return $pdf->download('finance-report-' . $report['start_date'] . '-to-' . $report['end_date'] . '.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        $this->authorize('export payroll');

        $report = $this->buildReport($request);

        return Excel::download(
            new FinanceReportExport($report),
            'finance-report-' . $report['start_date'] . '-to-' . $report['end_date'] . '.xlsx'
        );
    }

    /**
     * Batch 1 Part 9 -- restaurant payable report, per restaurant per
     * period: sales at full price, restaurant-funded discounts,
     * commission, payable, paid status. Reads the Part 2 fare_breakdown
     * snapshot exclusively -- never recalculates from current commission
     * rates. Orders from before that snapshot shipped fall back to the
     * flat columns with 0 commission (can't retroactively know the rate
     * that applied, so they're reported as-is rather than guessed at).
     */
    public function restaurantPayable(Request $request)
    {
        $this->authorize('export payroll');

        $restaurants = Restaurant::orderBy('name')->get(['id', 'name']);

        $report = null;
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $report = $this->buildRestaurantPayableReport($request);
        }

        return view('dashboard.finance.restaurant-payable', compact('restaurants', 'report'));
    }

    private function buildRestaurantPayableReport(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'restaurant_id' => 'nullable|exists:restaurants,id',
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $orders = RestaurantOrder::where('status', 'completed')
            ->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59'])
            ->when($request->filled('restaurant_id'), fn ($q) => $q->where('restaurant_id', $request->restaurant_id))
            ->get();

        $rows = $orders->groupBy('restaurant_id')->map(function ($restaurantOrders, $restaurantId) use ($request) {
            $restaurant = Restaurant::find($restaurantId);

            $sales = 0.0;
            $discounts = 0.0;
            $commission = 0.0;
            $payable = 0.0;

            foreach ($restaurantOrders as $order) {
                if ($order->fare_breakdown) {
                    $sales += (float) $order->fare_breakdown['subtotal'];
                    $discounts += $order->discount_funded_by === 'restaurant' ? (float) $order->fare_breakdown['discount'] : 0;
                    $commission += (float) $order->fare_breakdown['commission_amount'];
                    $payable += (float) $order->fare_breakdown['restaurant_payable'];
                } else {
                    // Legacy order with no snapshot -- commission rate at
                    // the time is unknowable, reported as 0 rather than guessed.
                    $sales += (float) $order->subtotal;
                    $payable += (float) $order->subtotal - (float) $order->discount;
                }
            }

            $alreadyPaid = RestaurantPayout::where('restaurant_id', $restaurantId)
                ->where('period_start', $request->start_date)
                ->where('period_end', $request->end_date)
                ->exists();

            return [
                'restaurant_id' => $restaurantId,
                'restaurant_name' => $restaurant->name ?? 'N/A',
                'order_count' => $restaurantOrders->count(),
                'sales_full_price' => round($sales, 2),
                'restaurant_funded_discounts' => round($discounts, 2),
                'commission' => round($commission, 2),
                'payable_amount' => round($payable, 2),
                'paid' => $alreadyPaid,
            ];
        })->values();

        return [
            'rows' => $rows,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'grand_total_sales' => round($rows->sum('sales_full_price'), 2),
            'grand_total_discounts' => round($rows->sum('restaurant_funded_discounts'), 2),
            'grand_total_commission' => round($rows->sum('commission'), 2),
            'grand_total_payable' => round($rows->sum('payable_amount'), 2),
        ];
    }

    public function markRestaurantPayoutAsPaid(Request $request)
    {
        $this->authorize('export payroll');

        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|exists:restaurants,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'sales_full_price' => 'required|numeric|min:0',
            'restaurant_funded_discounts' => 'required|numeric|min:0',
            'commission' => 'required|numeric|min:0',
            'payable_amount' => 'required|numeric|min:0',
            'method' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        try {
            RestaurantPayout::create([
                'restaurant_id' => $request->restaurant_id,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'sales_full_price' => $request->sales_full_price,
                'restaurant_funded_discounts' => $request->restaurant_funded_discounts,
                'commission' => $request->commission,
                'payable_amount' => $request->payable_amount,
                'method' => $request->method,
                'reference' => $request->reference,
                'paid_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Restaurant payout recorded successfully');
        } catch (\Throwable $th) {
            Log::error('Restaurant Payout Mark As Paid Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    private function buildReport(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'driver_ids' => 'nullable|array',
            'driver_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        return app(FinanceReportService::class)->build(
            $request->start_date,
            $request->end_date,
            $request->input('driver_ids', []),
            $request->input('type', 'all')
        );
    }
}
