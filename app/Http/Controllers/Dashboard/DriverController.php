<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view driver');
        try {
            $drivers = User::with('profile:id,user_id,phone_number,city')->role('driver')->get();
            return view('dashboard.drivers.index', compact('drivers'));
        } catch (\Throwable $th) {
            Log::error('Drivers Index Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('view driver');
        try {
            $driver = User::with('driverCnic', 'driverLicense', 'driverVehicle.vehicleType', 'profile')->findOrFail($id);
            return view('dashboard.drivers.show', compact('driver'));
        } catch (\Throwable $th) {
            Log::error('Drivers Show Failed', ['error' => $th->getMessage()]);
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
     * Currently just the city field -- admin-assigned once when a driver is
     * onboarded/verified, not derived from GPS (Live Ops brief Task 1). Kept
     * deliberately minimal since there's no full driver-edit screen yet.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update driver');
        $request->validate([
            'city' => 'nullable|string|max:255',
        ]);
        try {
            $driver = User::findOrFail($id);
            $profile = $driver->profile()->firstOrCreate([], ['first_name' => $driver->name]);
            $profile->city = $request->city;
            $profile->save();

            return redirect()->back()->with('success', 'Driver city updated successfully');
        } catch (\Throwable $th) {
            Log::error('Driver Update Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
