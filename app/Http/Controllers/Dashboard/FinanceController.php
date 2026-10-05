<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\FinanceReportExport;
use App\Http\Controllers\Controller;
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
