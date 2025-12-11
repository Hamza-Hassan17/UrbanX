<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CarBrand;
use App\Models\Transaction;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ChauffersController extends Controller
{
    public function getHomeData(Request $request)
    {
        try {
            $user = $request->user();

            $featuredCarBrands = CarBrand::where('is_featured', '1')->where('is_active', 'active')->get();

            $featuredVehicles = Vehicle::where('is_active', 'active')->where('is_featured', '1')
                ->get();

            // Convert image paths to full URLs (same style as driver license)
            $featuredCarBrands = $featuredCarBrands->map(function ($item) {
                $item->logo = url($item->logo);
                return $item;
            });

            $featuredVehicles = $featuredVehicles->map(function ($item) {
                $item->main_image = url($item->main_image);
                return $item;
            });


            return response()->json([
                'featured_car_brands' => $featuredCarBrands,
                'featured_vehicles' => $featuredVehicles,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Chauffeurs Home failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getVehicles(Request $request)
    {
        try {
            $vehicles = Vehicle::where('is_active', 'active')->get();

            // Convert image paths to full URLs
            $vehicles = $vehicles->map(function ($item) {
                $item->main_image = url($item->main_image);
                return $item;
            });

            return response()->json([
                'vehicles' => $vehicles,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Vehicles failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'rent_type' => 'required|in:hour,day,week,month',
            'with_driver' => 'required|in:0,1',
            'pickup_location' => 'nullable|string|max:255',
            'dropoff_location' => 'nullable|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            // 'subtotal' => 'nullable|string',
            // 'discount' => 'nullable|string',
            // 'tax' => 'nullable|string',
            // 'total_amount' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {

            $user = $request->user();

            $vehicle = Vehicle::find($request->vehicle_id);
            if (!$vehicle) {
                return response()->json([
                    'message' => 'Vehicle not found!'
                ], Response::HTTP_NOT_FOUND);
            }

            $start = Carbon::parse($request->start_time);
            $end = Carbon::parse($request->end_time);

            // Total hours
            $totalHours = $start->diffInHours($end);

            // Total days
            $totalDays = $start->diffInDays($end);

            // Total weeks
            $totalWeeks = floor($totalDays / 7);

            // Total months
            $totalMonths = $start->diffInMonths($end);

            $price = 0;

            // --------- PRICE CALCULATION ---------
            if ($totalHours < 24) {
                // Rent by Hours
                $price = $vehicle->price_per_hour * $totalHours;

            } elseif ($totalDays < 7) {
                // Rent by Days
                $price = $vehicle->price_per_day * $totalDays;

            } elseif ($totalDays >= 7 && $totalDays < 30) {
                // Rent by Weeks
                $price = $vehicle->price_per_week * $totalWeeks;

            } else {
                // Rent by Months
                $price = $vehicle->price_per_month * $totalMonths;
            }

            $booking = new Booking();
            $booking->user_id = $user->id;
            $booking->vehicle_id = $request->vehicle_id;
            $booking->name = $request->name;
            $booking->email = $request->email;
            $booking->phone = $request->phone;
            $booking->rent_type = $request->rent_type;
            $booking->with_driver = $request->with_driver;
            $booking->pickup_location = $request->pickup_location;
            $booking->dropoff_location = $request->dropoff_location;
            $booking->start_time = $request->start_time;
            $booking->end_time = $request->end_time;
            $booking->subtotal = $price;
            // $booking->discount = $request->discount ?? 0;
            // $booking->tax = $request->tax ?? 0;
            $booking->total_amount = $price;
            $booking->notes = $request->notes;
            $booking->status = 'pending';
            $booking->save();

            return response()->json([
                'message' => 'Booking created successfully!',
                'booking' => $booking,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            Log::error('API Create Booking failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'payment_method' => 'required|in:cash,card',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();

            $booking = Booking::find($request->booking_id);
            if (!$booking) {
                return response()->json([
                    'message' => 'Booking not found!'
                ], Response::HTTP_NOT_FOUND);
            }

            $transaction = new Transaction();
            $transaction->user_id = $booking->user_id;
            $transaction->booking_id = $booking->id;
            $transaction->amount = $booking->total_amount;
            $transaction->payment_method = $request->payment_method;
            $transaction->payment_status = 'pending';
            $transaction->save();

            return response()->json([
                'message' => 'Transaction created successfully!',
                'transaction' => $transaction,
                'booking' => $booking,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            Log::error('API Create Transaction failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function downloadReceipt($booking_id)
    {
        try {
            $booking = Booking::with('transaction')->where('booking_id', $booking_id)->first();
            if (!$booking) {
                return response()->json([
                    'message' => 'Booking not found!'
                ], Response::HTTP_NOT_FOUND);
            }

            $transaction = $booking->transaction;
            if (!$transaction) {
                return response()->json([
                    'message' => 'Transaction not found for this booking!'
                ], Response::HTTP_NOT_FOUND);
            }

            // LOAD PDF VIEW
            $pdf = PDF::loadView('pdf.receipt', [
                'booking' => $booking,
                'transaction' => $transaction
            ])->setPaper('a4');

            // Return file for download
            return $pdf->download('URBAN_RECEIPT_' . $booking_id . '.pdf');
        } catch (\Throwable $th) {
            Log::error('API Download Receipt failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
