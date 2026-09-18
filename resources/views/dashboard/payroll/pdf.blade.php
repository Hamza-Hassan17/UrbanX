<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
        tfoot td { font-weight: bold; background-color: #f9fafb; }
        .status-inactive { color: #dc2626; }
    </style>
</head>
<body>
    <h1>Driver Earnings Report</h1>
    <p class="subtitle">{{ $startDate }} to {{ $endDate }}</p>

    <table>
        <thead>
            <tr>
                <th>Driver ID</th>
                <th>Driver Name</th>
                <th>Status</th>
                <th>Total Rides</th>
                <th>Total Earnings</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['rows'] as $row)
                <tr>
                    <td>{{ $row['driver_id'] }}</td>
                    <td>{{ $row['driver_name'] }}</td>
                    <td class="{{ $row['is_active'] !== 'active' ? 'status-inactive' : '' }}">{{ ucfirst($row['is_active']) }}</td>
                    <td>{{ $row['total_rides'] }}</td>
                    <td>{{ \App\Helpers\Helper::formatCurrency($row['total_earnings']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"></td>
                <td>{{ $summary['grand_total_rides'] }}</td>
                <td>{{ \App\Helpers\Helper::formatCurrency($summary['grand_total']) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
