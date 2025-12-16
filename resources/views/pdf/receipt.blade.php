<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>

    <style>
        /* DOMPDF PAGE FIX */
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

        /* MAIN WRAPPER */
        .receipt {
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
            padding: 0;
        }

        /* HEADER */
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

        /* SECTION TITLE */
        .section-title {
            font-size: 15px;
            font-weight: bold;
            margin: 22px 0 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        /* TABLES */
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

        /* STATUS BADGE */
        .status {
            padding: 3px 8px;
            font-size: 11px;
            font-weight: bold;
            color: #fff;
            border-radius: 4px;
            display: inline-block;
        }

        .pending { background: #f0ad4e; color:#000; }
        .confirmed, .complete { background: #28a745; }
        .cancelled, .failed { background: #dc3545; }

        /* AMOUNT TABLE */
        table.amount td {
            padding: 7px 5px;
        }

        table.amount tr.total td {
            border-top: 2px solid #000;
            font-weight: bold;
            font-size: 15px;
        }

        /* FOOTER */
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

    <!-- HEADER -->
    <div class="header">
        <img src="{{ public_path('assets/img/logo/logo-full.png') }}" alt="Company Logo">
        <h2>PAYMENT RECEIPT</h2>
    </div>

    <!-- BOOKING DETAILS -->
    <div class="section-title">Booking Details</div>
    <table class="details">
        <tr>
            <td class="label">Booking ID</td>
            <td class="value">{{ $booking->booking_id }}</td>

            <td class="label">Status</td>
            <td class="value">
                <span class="status {{ strtolower($booking->status) }}">
                    {{ ucfirst($booking->status) }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="label">Customer Name</td>
            <td class="value">{{ $booking->name }}</td>

            <td class="label">Email</td>
            <td class="value">{{ $booking->email }}</td>
        </tr>

        <tr>
            <td class="label">Phone</td>
            <td class="value">{{ $booking->phone ?? '—' }}</td>

            <td class="label">Rent Type</td>
            <td class="value">{{ ucfirst($booking->rent_type) }}</td>
        </tr>

        <tr>
            <td class="label">With Driver</td>
            <td class="value">{{ $booking->with_driver == '1' ? 'Yes' : 'No' }}</td>

            <td class="label">Pickup</td>
            <td class="value">{{ $booking->pickup_location ?? '—' }}</td>
        </tr>

        <tr>
            <td class="label">Dropoff</td>
            <td class="value">{{ $booking->dropoff_location ?? '—' }}</td>

            <td class="label">Duration</td>
            <td class="value">
                {{ \Carbon\Carbon::parse($booking->start_time)->format('d M Y h:i A') }}<br>
                {{ \Carbon\Carbon::parse($booking->end_time)->format('d M Y h:i A') }}
            </td>
        </tr>
    </table>

    <!-- TRANSACTION DETAILS -->
    <div class="section-title">Transaction Details</div>
    <table class="details">
        <tr>
            <td class="label">Transaction ID</td>
            <td class="value">{{ $transaction->trx_id }}</td>

            <td class="label">Payment Method</td>
            <td class="value">{{ ucfirst($transaction->payment_method) }}</td>
        </tr>

        <tr>
            <td class="label">Payment Status</td>
            <td class="value">
                <span class="status {{ strtolower($transaction->payment_status) }}">
                    {{ ucfirst($transaction->payment_status) }}
                </span>
            </td>

            <td class="label">Date</td>
            <td class="value">{{ $transaction->created_at->format('d M Y h:i A') }}</td>
        </tr>
    </table>

    <!-- PAYMENT SUMMARY -->
    <div class="section-title">Payment Summary</div>
    <table class="amount">
        <tr>
            <td>Subtotal</td>
            <td align="right">{{ \App\Helpers\Helper::formatCurrency($booking->subtotal) }}</td>
        </tr>
        <tr>
            <td>Discount</td>
            <td align="right">{{ \App\Helpers\Helper::formatCurrency($booking->discount) }}</td>
        </tr>
        <tr>
            <td>Tax</td>
            <td align="right">{{ \App\Helpers\Helper::formatCurrency($booking->tax) }}</td>
        </tr>
        <tr class="total">
            <td>Total Amount</td>
            <td align="right">{{ \App\Helpers\Helper::formatCurrency($booking->total_amount) }}</td>
        </tr>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        © {{ date('Y') }} {{ \App\Helpers\Helper::getCompanyName() }}<br>
        This is a system-generated receipt.
    </div>

</div>

</body>
</html>
