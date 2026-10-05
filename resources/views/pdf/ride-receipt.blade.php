<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ride Receipt</title>

    <style>
        @page {
            margin: 25px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .receipt {
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
            padding: 0;
        }

        .header {
            background: #000;
            text-align: center;
            padding: 25px 0 20px;
            margin-bottom: 25px;
        }

        .header img {
            width: 150px;
            margin-bottom: 8px;
        }

        .header h2 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 1px;
            color: #fff;
        }

        .section-title {
            font-size: 15px;
            font-weight: bold;
            margin: 22px 0 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        table.details,
        table.amount {
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
            border-collapse: collapse;
        }

        table.details td {
            padding: 6px 4px;
            vertical-align: top;
        }

        table.details td.label {
            width: 25%;
            font-weight: bold;
            color: #555;
        }

        table.details td.value {
            width: 25%;
        }

        .status {
            padding: 3px 8px;
            font-size: 11px;
            font-weight: bold;
            color: #fff;
            border-radius: 4px;
            display: inline-block;
        }

        .completed { background: #28a745; }
        .cancelled { background: #dc3545; }

        table.amount td {
            padding: 7px 5px;
        }

        table.amount tr.deduction td {
            color: #b91c1c;
        }

        table.amount tr.total td {
            border-top: 2px solid #000;
            font-weight: bold;
            font-size: 15px;
        }

        .footer {
            margin-top: 35px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>

<body>

<div class="receipt">

    <div class="header">
        <img src="{{ public_path('assets/img/logo/logo-full.png') }}" alt="Company Logo">
        <h2>RIDE RECEIPT</h2>
    </div>

    <div class="section-title">Ride Details</div>
    <table class="details">
        <tr>
            <td class="label">Ride ID</td>
            <td class="value">#{{ $ride->id }}</td>

            <td class="label">Status</td>
            <td class="value">
                <span class="status {{ strtolower($ride->status) }}">
                    {{ ucfirst($ride->status) }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="label">Date</td>
            <td class="value">{{ optional($ride->completed_at)->format('d M Y h:i A') ?? optional($ride->created_at)->format('d M Y h:i A') }}</td>

            <td class="label">Distance / Duration</td>
            <td class="value">{{ $ride->distance_km }} km &middot; {{ $ride->duration_minutes }} min</td>
        </tr>

        <tr>
            <td class="label">Pickup</td>
            <td class="value" colspan="3">{{ $pickupAddress }}</td>
        </tr>

        <tr>
            <td class="label">Drop-off</td>
            <td class="value" colspan="3">{{ $dropoffAddress }}</td>
        </tr>
    </table>

    <div class="section-title">Fare Breakdown</div>
    <table class="amount">
        <tr>
            <td>Ride Fare (Gross)</td>
            <td align="right">{{ \App\Helpers\Helper::formatCurrency($breakdown['gross_fare']) }}</td>
        </tr>
        <tr class="deduction">
            <td>Service Commission ({{ $commissionPercent }}%)</td>
            <td align="right">({{ \App\Helpers\Helper::formatCurrency($breakdown['commission']) }})</td>
        </tr>
        <tr class="deduction">
            <td>SST on Service Commission ({{ $sstCommissionPercent }}%)</td>
            <td align="right">({{ \App\Helpers\Helper::formatCurrency($breakdown['sst_on_commission']) }})</td>
        </tr>
        <tr>
            <td>Remaining</td>
            <td align="right">{{ \App\Helpers\Helper::formatCurrency($breakdown['remaining']) }}</td>
        </tr>
        <tr class="deduction">
            <td>SST on Ride Fare ({{ $sstRideFarePercent }}%)</td>
            <td align="right">({{ \App\Helpers\Helper::formatCurrency($breakdown['sst_on_ride_fare']) }})</td>
        </tr>
        <tr class="total">
            <td>Driver's Income</td>
            <td align="right">{{ \App\Helpers\Helper::formatCurrency($breakdown['driver_income']) }}</td>
        </tr>
        <tr>
            <td>Driver's Share</td>
            <td align="right">{{ $breakdown['income_percent'] }}%</td>
        </tr>
    </table>

    <div class="footer">
        &copy; {{ date('Y') }} {{ \App\Helpers\Helper::getCompanyName() }}<br>
        This is a system-generated receipt.
    </div>

</div>

</body>
</html>
