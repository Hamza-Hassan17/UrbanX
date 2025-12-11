<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Urban – Payment Receipt</title>

    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #0A0D1A;
            margin: 0;
            padding: 50px 0;
        }

        /* MAIN RECEIPT CARD */
        .receipt-wrapper {
            width: 780px;
            margin: auto;
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0px 0px 28px rgba(0, 0, 0, 0.55);
            border: 1px solid #dadada;
        }

        /* HEADER */
        .receipt-header {
            background: #0A0D1A;
            color: #fff;
            text-align: center;
            padding: 35px 0 45px 0;
            position: relative;
        }

        .receipt-header img {
            width: 115px;
            margin-bottom: 10px;
            filter: drop-shadow(0px 0px 6px rgba(255,255,255,0.75));
        }

        .receipt-header h2 {
            margin: 0;
            font-size: 30px;
            letter-spacing: 2px;
            font-weight: 600;
        }

        /* CONTENT */
        .content {
            padding: 40px 45px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #0A0D1A;
            margin: 35px 0 18px 0;
            padding-bottom: 10px;
            border-bottom: 2.5px solid #ececec;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            row-gap: 12px;
            font-size: 16px;
            color: #333;
            line-height: 1.4;
        }

        /* BADGES */
        .status-badge {
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            font-size: 13px;
            font-weight: 600;
        }
        .confirmed { background: #28a745; }
        .complete { background: #28a745; }
        .pending { background: #ffc107; color: #000; }
        .cancelled { background: #dc3545; }
        .failed { background: #dc3545; }

        /* AMOUNT BOX */
        .amount-box {
            margin-top: 25px;
            padding: 22px 25px;
            border-radius: 12px;
            background: #F0F2FF;
            border: 1px solid #d6d9f5;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 16px;
        }

        .amount-row.total {
            font-weight: 700;
            font-size: 21px;
            padding-top: 14px;
            margin-top: 6px;
            border-top: 1px solid #bfc2e0;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            padding: 18px 0 20px;
            background: #fafafa;
            font-size: 14px;
            color: #555;
            border-top: 1px solid #e5e5e5;
        }
    </style>

</head>
<body>

<div class="receipt-wrapper">

    <!-- HEADER -->
    <div class="receipt-header">
        <img src="{{ asset('assets/img/logo/logo-full.png') }}" alt="Urban Logo">
        <h2>PAYMENT RECEIPT</h2>
    </div>

    <div class="content">

        <!-- BOOKING DETAILS -->
        <div class="section-title">Booking Details</div>
        <div class="grid">
            <div><strong>Booking ID:</strong> {{ $booking->booking_id }}</div>
            @php
                $status = $booking->status

            @endphp
            <div><strong>Status:</strong> <span class="status-badge {{ strtolower($booking->status) }}">{{ ucwords($booking->status) }}</span></div>
            <div><strong>Name:</strong> {{ $booking->name }}</div>
            <div><strong>Email:</strong> {{ $booking->email }}</div>
            <div><strong>Phone:</strong> {{ $booking->phone }}</div>
            <div><strong>Rent Type:</strong> {{ ucfirst($booking->rent_type) }}</div>
            <div><strong>With Driver:</strong> {{ $booking->with_driver == '1' ? 'Yes' : 'No' }}</div>
            <div><strong>Pickup:</strong> {{ $booking->pickup_location }}</div>
            <div><strong>Dropoff:</strong> {{ $booking->dropoff_location }}</div>
            <div><strong>Start:</strong> {{ \Carbon\Carbon::parse($booking->start_time)->format('Y-m-d h:i A') }}</div>
            <div><strong>End:</strong> {{ \Carbon\Carbon::parse($booking->end_time)->format('Y-m-d h:i A') }}</div>

        </div>

        <!-- TRANSACTION DETAILS -->
        <div class="section-title">Transaction Details</div>
        <div class="grid">
            <div><strong>Transaction ID:</strong> {{ $transaction->trx_id }}</div>
            <div><strong>Payment Method:</strong> {{ ucfirst($transaction->payment_method) }}</div>
            <div><strong>Payment Status:</strong> <span class="status-badge {{ strtolower($transaction->payment_status) }}">{{ ucwords($transaction->payment_status) }}</span></div>
            <div><strong>Date:</strong> {{ $transaction->created_at->format('Y-m-d h:i A') }}</div>
        </div>

        <!-- PAYMENT SUMMARY -->
        <div class="section-title">Payment Summary</div>
        <div class="amount-box">
            <div class="amount-row"><span>Subtotal:</span> <span>{{ \App\Helpers\Helper::formatCurrency($transaction->subtotal) }}</span></div>
            <div class="amount-row"><span>Discount:</span> <span>{{ \App\Helpers\Helper::formatCurrency($transaction->discount) }}</span></div>
            <div class="amount-row"><span>Tax:</span> <span>{{ \App\Helpers\Helper::formatCurrency($transaction->tax) }}</span></div>
            <div class="amount-row total"><span>Total Amount:</span> <span>{{ \App\Helpers\Helper::formatCurrency($transaction->total_amount) }}</span></div>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="footer">
        © {{ date('Y') }} {{ \App\Helpers\Helper::getCompanyName() }} — This is a system-generated receipt.
    </div>

</div>

</body>
</html>
