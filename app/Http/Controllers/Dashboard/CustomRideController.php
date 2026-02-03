<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ride;
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
            ->whereHas('driverVehicle')
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
                    'vehicle'  => $driver->driverVehicle ? $driver->driverVehicle->vehicle_name.' '.$driver->driverVehicle->vehicle_make.' '.$driver->driverVehicle->vehicle_year : 'N/A',
                    'vehicle_type_id' => $driver->driverVehicle ? $driver->driverVehicle->vehicle_type_id : null,
                    'city'   => 'Karachi',
                ];
            });

            $driver = User::role('driver')
                ->where('driver_status', 'available') // only available drivers
                ->whereHas('driverVehicle')          // must have a vehicle
                ->inRandomOrder()                    // randomize
                ->with('driverVehicle')              // eager load vehicle info
                ->first();

            $rides = Ride::with(['driver'])
                ->latest()
                ->take(4)
                ->get();

            $activeRidesCount = Ride::whereIn('status', ['requested', 'accepted', 'en_route', 'arrived', 'started'])->count();
            return view('dashboard.custom-rides.index', compact('drivers', 'rides', 'activeRidesCount', 'driver'));
        } catch (\Throwable $th) {
            Log::error('Custom Rides Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
