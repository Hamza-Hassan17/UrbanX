<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .subtitle { color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background-color: #f3f4f6; }
        .driver-row td { background-color: #e5e7eb; font-weight: bold; }
        .subtotal td, .subtotal th { font-weight: bold; background-color: #f9fafb; }
        .deduction { color: #b91c1c; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h1>{{ $report['type_label'] }}</h1>
    <p class="subtitle">
        {{ $report['start_date'] }} to {{ $report['end_date'] }}
        &middot; Commission {{ $report['commission_percent'] }}% &middot; SST {{ $report['sst_percent'] }}%
    </p>

    @if ($report['type'] === 'all')
        @foreach ($report['groups'] as $group)
            <table>
                <thead>
                    <tr>
                        <th>Ride</th>
                        <th>Completed</th>
                        <th>Pickup</th>
                        <th>Drop-off</th>
                        <th>Km / Min</th>
                        <th class="num">Gross Fare</th>
                        <th class="num">Commission</th>
                        <th class="num">SST on Comm.</th>
                        <th class="num">SST on Fare</th>
                        <th class="num">Net Income</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="driver-row">
                        <td colspan="10">
                            {{ $group['driver_name'] }}@if($group['driver_id']) (#{{ $group['driver_id'] }})@endif &middot; {{ $group['total_rides'] }} ride(s)
                        </td>
                    </tr>
                    @foreach ($group['rides'] as $ride)
                        <tr>
                            <td>#{{ $ride['ride_id'] }} {{ $ride['ride_type'] }}</td>
                            <td>{{ $ride['completed_at'] }}</td>
                            <td>{{ $ride['pickup'] }}</td>
                            <td>{{ $ride['dropoff'] }}</td>
                            <td>{{ $ride['distance_km'] }} / {{ $ride['duration_minutes'] }}</td>
                            <td class="num">{{ \App\Helpers\Helper::formatCurrency($ride['gross_fare']) }}</td>
                            <td class="num deduction">({{ \App\Helpers\Helper::formatCurrency($ride['commission']) }})</td>
                            <td class="num deduction">({{ \App\Helpers\Helper::formatCurrency($ride['sst_on_commission']) }})</td>
                            <td class="num deduction">({{ \App\Helpers\Helper::formatCurrency($ride['sst_on_ride_fare']) }})</td>
                            <td class="num">{{ \App\Helpers\Helper::formatCurrency($ride['driver_income']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        <td colspan="5">Subtotal: {{ $group['driver_name'] }}</td>
                        <td class="num">{{ \App\Helpers\Helper::formatCurrency($group['gross_fare']) }}</td>
                        <td class="num deduction">({{ \App\Helpers\Helper::formatCurrency($group['commission']) }})</td>
                        <td class="num deduction" colspan="2">({{ \App\Helpers\Helper::formatCurrency($group['sst']) }})</td>
                        <td class="num">{{ \App\Helpers\Helper::formatCurrency($group['net_income']) }}</td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        @if ($report['groups']->isNotEmpty())
            <table>
                <tr class="subtotal">
                    <td>Grand Total ({{ $report['totals']['total_rides'] }} rides)</td>
                    <td class="num">{{ \App\Helpers\Helper::formatCurrency($report['totals']['gross_fare']) }}</td>
                    <td class="num deduction">({{ \App\Helpers\Helper::formatCurrency($report['totals']['commission']) }})</td>
                    <td class="num deduction" colspan="2">({{ \App\Helpers\Helper::formatCurrency($report['totals']['sst']) }})</td>
                    <td class="num">{{ \App\Helpers\Helper::formatCurrency($report['totals']['net_income']) }}</td>
                </tr>
            </table>
        @endif
    @else
        @foreach ($report['groups'] as $group)
            <table>
                <thead>
                    <tr>
                        <th>Ride</th>
                        <th>Completed</th>
                        <th>Pickup</th>
                        <th>Drop-off</th>
                        <th>Km / Min</th>
                        <th class="num">Gross Fare</th>
                        <th class="num">{{ $report['type_label'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="driver-row">
                        <td colspan="7">
                            {{ $group['driver_name'] }}@if($group['driver_id']) (#{{ $group['driver_id'] }})@endif &middot; {{ $group['total_rides'] }} ride(s)
                        </td>
                    </tr>
                    @foreach ($group['rides'] as $ride)
                        <tr>
                            <td>#{{ $ride['ride_id'] }} {{ $ride['ride_type'] }}</td>
                            <td>{{ $ride['completed_at'] }}</td>
                            <td>{{ $ride['pickup'] }}</td>
                            <td>{{ $ride['dropoff'] }}</td>
                            <td>{{ $ride['distance_km'] }} / {{ $ride['duration_minutes'] }}</td>
                            <td class="num">{{ \App\Helpers\Helper::formatCurrency($ride['gross_fare']) }}</td>
                            <td class="num deduction">{{ \App\Helpers\Helper::formatCurrency($ride['selected_amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        <td colspan="6">Subtotal: {{ $group['driver_name'] }}</td>
                        <td class="num deduction">{{ \App\Helpers\Helper::formatCurrency($group['selected_total']) }}</td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        @if ($report['groups']->isNotEmpty())
            <table>
                <tr class="subtotal">
                    <td colspan="6">Grand Total ({{ $report['totals']['total_rides'] }} rides)</td>
                    <td class="num deduction">{{ \App\Helpers\Helper::formatCurrency($report['totals']['selected_total']) }}</td>
                </tr>
            </table>
        @endif
    @endif

    @if ($report['groups']->isEmpty())
        <p>No completed rides found for this selection.</p>
    @endif
</body>
</html>
