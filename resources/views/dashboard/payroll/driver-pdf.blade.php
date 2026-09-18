<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
        .summary { margin: 16px 0; }
        .summary strong { display: inline-block; width: 140px; }
    </style>
</head>
<body>
    <h1>Earnings Report — {{ $driver->name }}</h1>
    <p class="subtitle">{{ $startDate }} to {{ $endDate }}</p>

    <div class="summary">
        <p><strong>Total Rides:</strong> {{ $row['total_rides'] }}</p>
        <p><strong>Total Earnings:</strong> {{ \App\Helpers\Helper::formatCurrency($row['total_earnings']) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Ride ID</th>
                <th>Type</th>
                <th>Completed At</th>
                <th>Fare</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($row['rides'] as $ride)
                <tr>
                    <td>{{ $ride->id }}</td>
                    <td>{{ ucfirst($ride->ride_type) }}</td>
                    <td>{{ optional($ride->completed_at)->format('M d, Y H:i') }}</td>
                    <td>{{ \App\Helpers\Helper::formatCurrency($ride->total_fare) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
