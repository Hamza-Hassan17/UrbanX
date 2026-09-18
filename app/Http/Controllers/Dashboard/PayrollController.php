<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\DriverEarningsExport;
use App\Http\Controllers\Controller;
use App\Mail\DriverEarningsReportMail;
use App\Models\Ride;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Live Ops Task 8 -- payroll/accounting exports. Earnings are the driver's
 * full total_fare across completed rides in the date range (no commission
 * split modeled -- there's no platform-fee column on rides today), summed
 * across both ride_type values since a driver can do taxi and delivery
 * jobs alike.
 */
class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('export payroll');

        $drivers = User::role('driver')->orderBy('name')->get(['id', 'name', 'is_active']);

        $summary = null;
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $summary = $this->buildSummary($request);
        }

        return view('dashboard.payroll.index', compact('drivers', 'summary'));
    }

    public function exportPdf(Request $request)
    {
        $this->authorize('export payroll');

        $summary = $this->buildSummary($request);

        $pdf = Pdf::loadView('dashboard.payroll.pdf', [
            'summary' => $summary,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
        ]);

        return $pdf->download('driver-earnings-' . $request->start_date . '-to-' . $request->end_date . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $this->authorize('export payroll');

        $summary = $this->buildSummary($request);

        return Excel::download(
            new DriverEarningsExport($summary),
            'driver-earnings-' . $request->start_date . '-to-' . $request->end_date . '.xlsx'
        );
    }

    /**
     * Bulk-emails each active driver their own individual earnings PDF for
     * the date range -- per-driver report, not the combined summary table
     * exportPdf() produces.
     */
    public function bulkSend(Request $request)
    {
        $this->authorize('export payroll');

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $activeDrivers = User::role('driver')->where('is_active', 'active')->get();

            $sent = 0;
            $failed = 0;

            foreach ($activeDrivers as $driver) {
                $rows = $this->earningsRowsForDrivers(collect([$driver]), $request->start_date, $request->end_date);
                $row = $rows->first();

                if (!$row || $row['total_rides'] === 0) {
                    continue; // nothing to report for this driver this period
                }

                $pdf = Pdf::loadView('dashboard.payroll.driver-pdf', [
                    'driver' => $driver,
                    'row' => $row,
                    'startDate' => $request->start_date,
                    'endDate' => $request->end_date,
                ]);

                try {
                    Mail::to($driver->email)->send(new DriverEarningsReportMail(
                        $driver,
                        $row,
                        $request->start_date,
                        $request->end_date,
                        $pdf->output()
                    ));
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('Payroll bulk email failed', ['driver_id' => $driver->id, 'error' => $e->getMessage()]);
                }
            }

            $message = "Sent {$sent} earnings report(s).";
            if ($failed > 0) {
                $message .= " {$failed} failed to send (see logs).";
            }

            return redirect()->back()->with($failed > 0 && $sent === 0 ? 'error' : 'success', $message);
        } catch (\Throwable $th) {
            Log::error('Payroll Bulk Send Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    private function buildSummary(Request $request): array
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

        $driversQuery = User::role('driver');

        if ($request->filled('driver_ids')) {
            $driversQuery->whereIn('id', $request->driver_ids);
        }
        // No is_active filter here by default -- the spec explicitly requires
        // being able to include inactive drivers in the filtered report; the
        // bulk-send action is the one that restricts to active only.

        $drivers = $driversQuery->get();

        $rows = $this->earningsRowsForDrivers($drivers, $request->start_date, $request->end_date);

        return [
            'rows' => $rows,
            'grand_total' => $rows->sum('total_earnings'),
            'grand_total_rides' => $rows->sum('total_rides'),
        ];
    }

    private function earningsRowsForDrivers($drivers, string $startDate, string $endDate)
    {
        return $drivers->map(function ($driver) use ($startDate, $endDate) {
            $rides = Ride::where('driver_id', $driver->id)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->get();

            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'is_active' => $driver->is_active,
                'total_rides' => $rides->count(),
                'total_earnings' => (float) $rides->sum('total_fare'),
                'rides' => $rides,
            ];
        })->values();
    }
}
