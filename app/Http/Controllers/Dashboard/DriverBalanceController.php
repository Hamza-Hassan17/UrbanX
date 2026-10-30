<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RiderCashLedger;
use App\Models\User;
use App\Services\DispatchSettingsService;
use App\Services\RiderCashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Batch 2 Part 5 -- Rides workspace page for taxi driver outstanding
 * balances and settlements. Near-identical to RiderCashController, which
 * it deliberately mirrors -- same ledger table (rider_cash_ledgers),
 * same balance mechanics (RiderCashService::currentBalance()/
 * recordSettlement() are entry-type-agnostic), just scoped to taxi
 * drivers (not delivery vehicle type) and the taxi outstanding limit
 * instead of the rider cash limit. 'manage driver balances' permission,
 * granted to Admin and Finance (see UserRolePermissionSeeder).
 */
class DriverBalanceController extends Controller
{
    public function index()
    {
        $this->authorize('manage driver balances');

        try {
            $drivers = User::with('profile:id,user_id,phone_number,city')
                ->role('driver')
                ->get()
                ->map(function ($driver) {
                    $driver->outstanding_balance = RiderCashService::currentBalance($driver->id);
                    return $driver;
                })
                ->sortByDesc('outstanding_balance')
                ->values();

            $outstandingLimit = DispatchSettingsService::taxiOutstandingLimit();

            return view('dashboard.driver-balances.index', compact('drivers', 'outstandingLimit'));
        } catch (\Throwable $th) {
            Log::error('Driver Balances Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function show(string $driverId)
    {
        $this->authorize('manage driver balances');

        try {
            $driver = User::findOrFail($driverId);
            $ledger = RiderCashLedger::where('rider_id', $driverId)
                ->with('recordedBy:id,name')
                ->latest('id')
                ->get();

            $outstandingLimit = DispatchSettingsService::taxiOutstandingLimit();
            $currentBalance = RiderCashService::currentBalance((int) $driverId);

            return view('dashboard.driver-balances.show', compact('driver', 'ledger', 'outstandingLimit', 'currentBalance'));
        } catch (\Throwable $th) {
            Log::error('Driver Balances Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function recordSettlement(Request $request, string $driverId)
    {
        $this->authorize('manage driver balances');

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
                (int) $driverId,
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
