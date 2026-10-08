<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RiderCashLedger;
use App\Models\User;
use App\Services\FareBreakdownService;
use App\Services\RiderCashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Batch 1 Part 8 -- Delivery workspace page for cash-in-hand balances and
 * settlements. 'manage rider cash' permission, granted to Admin and
 * Finance (see UserRolePermissionSeeder).
 */
class RiderCashController extends Controller
{
    public function index()
    {
        $this->authorize('manage rider cash');

        try {
            // Same delivery-rider query DriverController::deliveryIndex()
            // uses -- cash-in-hand only applies to delivery riders (COD on
            // food orders and parcels), not taxi drivers.
            $riders = User::with('profile:id,user_id,phone_number,city')
                ->role('driver')
                ->whereHas('driverVehicle.vehicleType', fn ($q) => $q->where('is_delivery', '1'))
                ->get()
                ->map(function ($rider) {
                    $rider->cash_balance = RiderCashService::currentBalance($rider->id);
                    return $rider;
                })
                ->sortByDesc('cash_balance')
                ->values();

            $cashLimit = FareBreakdownService::riderCashLimit();

            return view('dashboard.rider-cash.index', compact('riders', 'cashLimit'));
        } catch (\Throwable $th) {
            Log::error('Rider Cash Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function show(string $riderId)
    {
        $this->authorize('manage rider cash');

        try {
            $rider = User::findOrFail($riderId);
            $ledger = RiderCashLedger::where('rider_id', $riderId)
                ->with('recordedBy:id,name')
                ->latest('id')
                ->get();

            $cashLimit = FareBreakdownService::riderCashLimit();
            $currentBalance = RiderCashService::currentBalance((int) $riderId);

            return view('dashboard.rider-cash.show', compact('rider', 'ledger', 'cashLimit', 'currentBalance'));
        } catch (\Throwable $th) {
            Log::error('Rider Cash Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function recordSettlement(Request $request, string $riderId)
    {
        $this->authorize('manage rider cash');

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        try {
            RiderCashService::recordSettlement(
                (int) $riderId,
                (float) $request->amount,
                $request->method,
                $request->note,
                auth()->id()
            );

            return redirect()->back()->with('success', 'Settlement recorded successfully');
        } catch (\Throwable $th) {
            Log::error('Record Settlement Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }
}
