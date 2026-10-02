<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriverEarningsExport implements FromArray, WithHeadings, WithStyles
{
    private array $summary;

    public function __construct(array $summary)
    {
        $this->summary = $summary;
    }

    public function array(): array
    {
        return $this->summary['rows']->map(function ($row) {
            return [
                $row['driver_id'],
                $row['driver_name'],
                ucfirst($row['is_active']),
                $row['total_rides'],
                round($row['gross_fare'], 2),
                round($row['commission'], 2),
                round($row['sst_on_commission'], 2),
                round($row['sst_on_ride_fare'], 2),
                round($row['total_earnings'], 2),
            ];
        })->push([
            '', '', '', 'Grand Total',
            round($this->summary['grand_total_gross'], 2),
            round($this->summary['grand_total_commission'], 2),
            round($this->summary['grand_total_sst'], 2),
            '',
            round($this->summary['grand_total'], 2),
        ])->toArray();
    }

    public function headings(): array
    {
        return ['Driver ID', 'Driver Name', 'Status', 'Total Rides', 'Gross Fare', 'Commission', 'SST on Commission', 'SST on Ride Fare', "Driver's Net Income"];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
