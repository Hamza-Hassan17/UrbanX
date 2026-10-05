<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinanceReportExport implements FromArray, WithHeadings, WithStyles
{
    private array $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function array(): array
    {
        return $this->report['type'] === 'all' ? $this->fullRows() : $this->singleTypeRows();
    }

    public function headings(): array
    {
        if ($this->report['type'] === 'all') {
            return [
                'Driver', 'Ride ID', 'Completed At', 'Type', 'Pickup', 'Drop-off', 'Distance (km)', 'Duration (min)',
                'Gross Fare', 'Commission', 'SST on Commission', 'SST on Ride Fare', "Driver's Net Income",
            ];
        }

        return [
            'Driver', 'Ride ID', 'Completed At', 'Type', 'Pickup', 'Drop-off', 'Distance (km)', 'Duration (min)',
            'Gross Fare', $this->report['type_label'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function fullRows(): array
    {
        $rows = [];

        foreach ($this->report['groups'] as $group) {
            foreach ($group['rides'] as $ride) {
                $rows[] = [
                    $group['driver_name'],
                    $ride['ride_id'],
                    $ride['completed_at'],
                    $ride['ride_type'],
                    $ride['pickup'],
                    $ride['dropoff'],
                    $ride['distance_km'],
                    $ride['duration_minutes'],
                    round($ride['gross_fare'], 2),
                    round($ride['commission'], 2),
                    round($ride['sst_on_commission'], 2),
                    round($ride['sst_on_ride_fare'], 2),
                    round($ride['driver_income'], 2),
                ];
            }

            $rows[] = [
                $group['driver_name'] . ' - Subtotal',
                '', '', '', '', '', '', '',
                $group['gross_fare'],
                $group['commission'],
                '',
                $group['sst'],
                $group['net_income'],
            ];
        }

        $rows[] = [
            'Grand Total', '', '', '', '', '', '', '',
            $this->report['totals']['gross_fare'],
            $this->report['totals']['commission'],
            '',
            $this->report['totals']['sst'],
            $this->report['totals']['net_income'],
        ];

        return $rows;
    }

    private function singleTypeRows(): array
    {
        $rows = [];

        foreach ($this->report['groups'] as $group) {
            foreach ($group['rides'] as $ride) {
                $rows[] = [
                    $group['driver_name'],
                    $ride['ride_id'],
                    $ride['completed_at'],
                    $ride['ride_type'],
                    $ride['pickup'],
                    $ride['dropoff'],
                    $ride['distance_km'],
                    $ride['duration_minutes'],
                    round($ride['gross_fare'], 2),
                    round($ride['selected_amount'], 2),
                ];
            }

            $rows[] = [
                $group['driver_name'] . ' - Subtotal',
                '', '', '', '', '', '', '', '',
                $group['selected_total'],
            ];
        }

        $rows[] = [
            'Grand Total', '', '', '', '', '', '', '', '',
            $this->report['totals']['selected_total'],
        ];

        return $rows;
    }
}
