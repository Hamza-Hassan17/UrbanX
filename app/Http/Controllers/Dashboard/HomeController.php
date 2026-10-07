<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RestaurantOrder;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $workspace = session('workspace');

            // Platform keeps today's existing (placeholder) dashboard content
            // unchanged -- the spec only asks Phase 3 to split out Rides and
            // Delivery into their own, real KPIs; it explicitly says Platform
            // stays "the combined overview (current dashboard content)".
            if ($workspace === 'rides') {
                return view('dashboard.index', ['ridesKpis' => $this->ridesKpis()]);
            }

            if ($workspace === 'delivery') {
                return view('dashboard.index', ['deliveryKpis' => $this->deliveryKpis()]);
            }

            return view('dashboard.index');
        } catch (\Throwable $th) {
            Log::error('Dashboard Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Real KPIs for the Rides workspace dashboard (Phase 3 of the admin
     * workspace split). Taxi only (ride_type='ride'), matching the Rides
     * queue scope from Phase 2. canSeeRevenue mirrors the gate already used
     * on the Payroll/Finance pages ('export payroll') -- Operator doesn't
     * have it, Finance/Admin/Super Admin do, so the fare total is simply
     * omitted from the payload (not just hidden in the view) for anyone
     * who shouldn't see it.
     */
    private function ridesKpis(): array
    {
        $today = today();
        $canSeeRevenue = auth()->user()->can('export payroll');

        $trend = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [
                'label' => $date->format('D'),
                'count' => Ride::where('ride_type', 'ride')->whereDate('requested_at', $date)->count(),
            ];
        })->values();

        return [
            'rides_today' => Ride::where('ride_type', 'ride')->whereDate('requested_at', $today)->count(),
            'completed_today' => Ride::where('ride_type', 'ride')->whereDate('completed_at', $today)->where('status', 'completed')->count(),
            'cancelled_today' => Ride::where('ride_type', 'ride')->whereDate('cancelled_at', $today)->where('status', 'cancelled')->count(),
            'drivers_available' => User::role('driver')->where('driver_status', 'available')->count(),
            'drivers_busy' => User::role('driver')->where('driver_status', 'busy')->count(),
            'revenue_today' => $canSeeRevenue
                ? (float) Ride::where('ride_type', 'ride')->whereDate('completed_at', $today)->where('status', 'completed')->sum('total_fare')
                : null,
            'trend' => $trend,
        ];
    }

    /**
     * Real KPIs for the Delivery workspace dashboard. Food vs Parcel split
     * mirrors DeliveryController's queue logic -- a food job has a matching
     * restaurant_orders row, a parcel job doesn't.
     */
    private function deliveryKpis(): array
    {
        $today = today();
        $canSeeRevenue = auth()->user()->can('export payroll');

        $deliveryRideIdsToday = Ride::where('ride_type', 'delivery')
            ->whereDate('requested_at', $today)
            ->pluck('id');

        $foodToday = RestaurantOrder::whereIn('ride_id', $deliveryRideIdsToday)->count();
        $parcelToday = max(0, $deliveryRideIdsToday->count() - $foodToday);

        $trend = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return [
                'label' => $date->format('D'),
                'count' => Ride::where('ride_type', 'delivery')->whereDate('requested_at', $date)->count(),
            ];
        })->values();

        return [
            'orders_today' => $deliveryRideIdsToday->count(),
            'food_today' => $foodToday,
            'parcel_today' => $parcelToday,
            'completed_today' => Ride::where('ride_type', 'delivery')->whereDate('completed_at', $today)->where('status', 'completed')->count(),
            'riders_available' => User::role('driver')
                ->where('driver_status', 'available')
                ->whereHas('driverVehicle.vehicleType', fn ($q) => $q->where('is_delivery', true))
                ->count(),
            'revenue_today' => $canSeeRevenue
                ? (float) Ride::where('ride_type', 'delivery')->whereDate('completed_at', $today)->where('status', 'completed')->sum('total_fare')
                : null,
            'trend' => $trend,
        ];
    }

    public function getRoute(Request $request)
    {
        $validated = $request->validate([
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'drop_lat' => 'required|numeric',
            'drop_lng' => 'required|numeric',
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
        ]);

        $url = "https://router.project-osrm.org/route/v1/driving/{$validated['pickup_lng']},{$validated['pickup_lat']};{$validated['drop_lng']},{$validated['drop_lat']}?overview=full&geometries=geojson";

        $response = Http::get($url)->json();

        if (!isset($response['routes'][0])) {
            return response()->json(['error' => 'No route found'], 400);
        }

        $route = $response['routes'][0];
        $distanceKm = $route['distance'] / 1000;  // meters → km
        $durationMin = $route['duration'] / 60;   // seconds → minutes

        $vehicle = VehicleType::find($validated['vehicle_type_id']);

        // Example fare calculation logic
        $fare = ($vehicle->base_fare ?? 100) + ($distanceKm * 50);

        return response()->json([
            'coordinates' => $route['geometry']['coordinates'],  // polyline points
            'distance_km' => round($distanceKm, 2),
            'duration_min' => round($durationMin),
            'estimated_fare' => round($fare, 0),
        ]);
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
        //
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
}
