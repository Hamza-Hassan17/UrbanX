<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\PerformanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Batch 3 Part 5 -- Driver Performance (Rides workspace) and Rider
 * Performance (Delivery workspace). Read-only; 'view performance reports'.
 */
class PerformanceReportController extends Controller
{
    public function drivers(Request $request)
    {
        return $this->render($request, 'ride');
    }

    public function riders(Request $request)
    {
        return $this->render($request, 'delivery');
    }

    public function exportDrivers(Request $request)
    {
        return $this->export($request, 'ride');
    }

    public function exportRiders(Request $request)
    {
        return $this->export($request, 'delivery');
    }

    private function range(Request $request): array
    {
        $tz = 'Asia/Karachi';
        try {
            $from = $request->filled('from') ? Carbon::parse($request->from, $tz)->startOfDay() : now($tz)->subDays(6)->startOfDay();
            $to = $request->filled('to') ? Carbon::parse($request->to, $tz)->endOfDay() : now($tz)->endOfDay();
        } catch (\Throwable $e) {
            $from = now($tz)->subDays(6)->startOfDay();
            $to = now($tz)->endOfDay();
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from->utc(), $to->utc()];
    }

    private function rows(Request $request, string $scope): array
    {
        $this->authorize('view performance reports');

        [$from, $to] = $this->range($request);
        $sort = $request->input('sort', 'completed_jobs');
        $dir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $rows = PerformanceReportService::sort(PerformanceReportService::build($scope, $from, $to), $sort, $dir);

        return [$rows, $from, $to, $sort, $dir];
    }

    private function render(Request $request, string $scope)
    {
        [$rows, $from, $to, $sort, $dir] = $this->rows($request, $scope);
        $isDelivery = $scope === 'delivery';

        return view('dashboard.performance.index', [
            'rows' => $rows,
            'from' => $from->copy()->setTimezone('Asia/Karachi'),
            'to' => $to->copy()->setTimezone('Asia/Karachi'),
            'sort' => $sort,
            'dir' => $dir,
            'columns' => PerformanceReportService::COLUMNS,
            'title' => $isDelivery ? 'Rider Performance' : 'Driver Performance',
            'personLabel' => $isDelivery ? 'Rider' : 'Driver',
            'indexRoute' => $isDelivery ? 'dashboard.rider-performance.index' : 'dashboard.driver-performance.index',
            'exportRoute' => $isDelivery ? 'dashboard.rider-performance.export' : 'dashboard.driver-performance.export',
        ]);
    }

    private function export(Request $request, string $scope): StreamedResponse
    {
        [$rows, $from, $to] = $this->rows($request, $scope);
        $filename = ($scope === 'delivery' ? 'rider' : 'driver') . '-performance-'
            . $from->copy()->setTimezone('Asia/Karachi')->format('Y-m-d') . '-to-'
            . $to->copy()->setTimezone('Asia/Karachi')->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['ID'], array_values(PerformanceReportService::COLUMNS), ['City', 'Reviews']));
            foreach ($rows as $row) {
                $line = [$row['driver_id']];
                foreach (array_keys(PerformanceReportService::COLUMNS) as $key) {
                    // Guard against spreadsheet formula injection in names.
                    $value = $row[$key];
                    if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
                        $value = "'" . $value;
                    }
                    $line[] = $value;
                }
                $line[] = $row['city'];
                $line[] = $row['reviews'];
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
