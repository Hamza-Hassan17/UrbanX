<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\DriverEarningsExport;
use App\Http\Controllers\Controller;
use App\Mail\DriverEarningsReportMail;
use App\Models\Ride;
use App\Models\User;
use App\Services\FareBreakdownService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Live Ops Task 8 -- payroll/accounting exports. Gross earnings are the
 * driver's full total_fare across completed rides in the date range, summed
 * across both ride_type values since a driver can do taxi and delivery jobs
 * alike. That gross is then run through FareBreakdownService (commission +
 * SST, admin-configurable via Settings > System Settings) to get the
 * driver's actual net payable income.
 */
class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('export payroll');

        $drivers = User::role('driver')->orderBy('name')->get(['id', 'name', 'phone', 'is_active']);

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
            'grand_total_gross' => $rows->sum('gross_fare'),
            'grand_total_commission' => $rows->sum('commission'),
            'grand_total_sst' => $rows->sum('sst_on_commission') + $rows->sum('sst_on_ride_fare'),
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

            // Batch 1 Part 2 -- sum each ride's OWN stored snapshot rather
            // than aggregating gross fare first and splitting it once at
            // today's rate. Summing first would silently misprice any
            // period spanning a Pricing & Fees change, and would apply the
            // taxi-only commission rate to delivery rides mixed into the
            // same driver_id. Rides completed before this feature shipped
            // have no snapshot yet -- fall back to a live calculation for
            // those only, same as the old behavior.
            $grossFare = 0.0;
            $commission = 0.0;
            $sstOnCommission = 0.0;
            $sstOnRideFare = 0.0;
            $totalEarnings = 0.0;

            foreach ($rides as $ride) {
                $fare = (float) $ride->total_fare;
                $grossFare += $fare;

                if ($ride->fare_breakdown) {
                    $snapshot = $ride->fare_breakdown;
                    $commission += (float) $snapshot['commission_amount'];
                    $sstOnRideFare += (float) $snapshot['sst_amount'];
                    $totalEarnings += (float) $snapshot['rider_net'];
                } else {
                    $breakdown = FareBreakdownService::calculate(
                        $fare,
                        $ride->ride_type === 'delivery' ? FareBreakdownService::platformSharePercent() : null
                    );
                    $commission += $breakdown['commission'];
                    $sstOnCommission += $breakdown['sst_on_commission'];
                    $sstOnRideFare += $breakdown['sst_on_ride_fare'];
                    $totalEarnings += $breakdown['driver_income'];
                }
            }

            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'is_active' => $driver->is_active,
                'total_rides' => $rides->count(),
                'gross_fare' => round($grossFare, 2),
                'commission' => round($commission, 2),
                'sst_on_commission' => round($sstOnCommission, 2),
                'sst_on_ride_fare' => round($sstOnRideFare, 2),
                'total_earnings' => round($totalEarnings, 2),
                'rides' => $rides,
            ];
        })->values();
    }
}
