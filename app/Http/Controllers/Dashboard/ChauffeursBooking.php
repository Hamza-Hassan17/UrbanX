<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class ChauffeursBooking extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view chauffeur booking');
        try {
            $bookings = Booking::with('user', 'vehicle')->get();
            return view('dashboard.chauffeurs.bookings.index', compact('bookings'));
        } catch (\Throwable $th) {
            Log::error('Bookings Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $this->authorize('view chauffeur booking');
        try {
            $booking = Booking::with('user', 'vehicle', 'transactions')->findOrFail($id);
            return view('dashboard.chauffeurs.bookings.show', compact('booking'));
        } catch (\Throwable $th) {
            Log::error('Booking Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function downloadReceipt(string $id)
    {
        $this->authorize('update chauffeur booking');
        try {
            $booking = Booking::with('transactions')->where('id', $id)->first();
            // Multiple transaction attempts can exist per booking -- the receipt
            // is for the most recent one.
            $transaction = $booking->transactions->sortByDesc('created_at')->first();
            $pdf = PDF::loadView('pdf.receipt', [
                'booking' => $booking,
                'transaction' => $transaction
            ])->setPaper('a4');

            // Return file for download
            return $pdf->download('URBAN_RECEIPT_' . $id . '.pdf');
        } catch (\Throwable $th) {
            Log::error('Vehicle Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(Request $request, string $id)
    {
        $this->authorize('update chauffeur booking');
        try {
            $booking = Booking::findOrFail($id);
            $booking->status = $request->status;
            $booking->save();

            $customer = $booking->user;
            app('notificationService')->notifyUsers(
                [$customer],
                'Booking Status Updated',
                'Your booking status has been updated to ' . $booking->status . '.',
                'bookings',
                $booking->id,
                'booking_details'
            );

            return redirect()->back()->with('success', 'Booking status updated successfully');
        } catch (\Throwable $th) {
            Log::error('Booking Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Manually confirm a cash transaction was actually collected. There's no
     * payment gateway for chauffeur bookings -- cash on pickup is the
     * intended flow, not a gap waiting on Stripe/PayPal integration -- so
     * this is the only way a transaction ever leaves 'pending'.
     */
    public function markTransactionReceived(string $id)
    {
        $this->authorize('update chauffeur booking');
        try {
            $transaction = Transaction::findOrFail($id);

            if ($transaction->payment_status !== 'pending') {
                return redirect()->back()->with('error', 'This transaction is already ' . $transaction->payment_status . '.');
            }

            $transaction->payment_status = 'complete';
            $transaction->save();

            $customer = $transaction->user;
            app('notificationService')->notifyUsers(
                [$customer],
                'Payment Received',
                'Your payment for booking has been marked as received.',
                'transactions',
                $transaction->id,
                'transaction_details'
            );

            return redirect()->back()->with('success', 'Transaction marked as received.');
        } catch (\Throwable $th) {
            Log::error('Mark Transaction Received Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }
}
