<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomRideController extends Controller
{
    public function index()
    {
        $this->authorize('view custom rides');
        try {
            $drivers = User::with('driverVehicle')
            ->role('driver')
            ->get()
            ->map(function ($driver) {
                return [
                    'id'     => $driver->id,
                    'name'   => $driver->name,
                    'lat'    => $driver->lat ? (float) $driver->lat : null,
                    'lng'    => $driver->lang ? (float) $driver->lang : null,
                    'status' => $driver->driver_status, // busy | available
                    'icon'   => $driver->driver_status === 'available'
                                    ? 'taxiIcon'
                                    : 'taxiIconBusy',
                    'plate'  => optional($driver->driverVehicle)->vehicle_plate_number,
                    'city'   => 'Karachi',
                ];
            });
            return view('dashboard.custom-rides.index', compact('drivers'));
        } catch (\Throwable $th) {
            Log::error('Custom Rides Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
