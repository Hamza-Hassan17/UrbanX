<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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
            $booking = Booking::with('user', 'vehicle', 'transaction')->findOrFail($id);
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
            $booking = Booking::with('transaction')->where('id', $id)->first();
            $transaction = $booking->transaction;
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

            return redirect()->back()->with('success', 'Booking status updated successfully');
        } catch (\Throwable $th) {
            Log::error('Booking Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
