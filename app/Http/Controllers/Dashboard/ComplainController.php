<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Complain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComplainController extends Controller
{
    /**
     * Service tags each workspace sees (Batch 3 Part 1). Platform sees all.
     */
    private const WORKSPACE_SERVICES = [
        'rides' => ['ride', 'rental'],
        'delivery' => ['food', 'parcel'],
    ];

    private function workspaceServices(): ?array
    {
        return self::WORKSPACE_SERVICES[session('workspace')] ?? null;
    }

    /**
     * Rides and Delivery workspaces list only their own services' complaints;
     * Platform lists all of them with a ?service= filter.
     */
    public function index(Request $request)
    {
        $this->authorize('view complain');
        try {
            $services = $this->workspaceServices();
            $complains = Complain::with(['ride:id,ride_type', 'restaurantOrder:id,order_number,restaurant_id,ride_id'])
                ->latest()
                ->when($services, fn ($q) => $q->whereIn('service', $services))
                ->when(!$services && $request->filled('service'), fn ($q) => $q->where('service', $request->service))
                ->get();

            return view('dashboard.complains.index', ['complains' => $complains, 'workspace' => session('workspace')]);
        } catch (\Throwable $th) {
            Log::error('Complains Index Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('view complain');
        try {
            $complain = Complain::with(['user', 'ride.driver:id,name', 'restaurantOrder.restaurant:id,name'])->findOrFail($id);

            $services = $this->workspaceServices();
            if ($services && !in_array($complain->service, $services, true)) {
                abort(403, 'This complaint belongs to another workspace.');
            }

            return view('dashboard.complains.show', compact('complain'));
        } catch (\Throwable $th) {
            Log::error('Complain Show Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('delete complain');
        try {
            $complain = Complain::findOrFail($id);
            $complain->delete();
            return redirect()->back()->with('success', 'Complain deleted successfully');
        } catch (\Throwable $th) {
            Log::error('Complain Deletion Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(Request $request, string $id)
    {
        $this->authorize('update complain');
        try {
            $complain = Complain::findOrFail($id);
            $complain->status = $request->status;
            $complain->save();

            return redirect()->back()->with('success', 'Complain status updated successfully');
        } catch (\Throwable $th) {
            Log::error('Complain Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
