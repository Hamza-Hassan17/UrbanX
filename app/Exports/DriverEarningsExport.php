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
                round($row['total_earnings'], 2),
            ];
        })->push([
            '', '', '', 'Grand Total', round($this->summary['grand_total'], 2),
        ])->toArray();
    }

    public function headings(): array
    {
        return ['Driver ID', 'Driver Name', 'Status', 'Total Rides', 'Total Earnings'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
