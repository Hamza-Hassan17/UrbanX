<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Complain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComplainController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Phase 4 of the admin workspace split: Rides/Delivery workspaces only
     * see complaints tagged for their service; Platform sees everything,
     * with an optional ?service= filter. Most existing complaints have no
     * service tag (nothing to backfill from -- see BackfillServiceColumns),
     * so they only ever show up in Platform, same as any other
     * null-service row. New complaints start getting tagged once mobile
     * sends an optional `service` field on submission.
     */
    public function index(Request $request)
    {
        $this->authorize('view complain');
        try {
            $workspace = session('workspace');

            $complains = Complain::latest()
                ->when($workspace === 'rides', fn ($q) => $q->where('service', 'ride'))
                ->when($workspace === 'delivery', fn ($q) => $q->whereIn('service', ['food', 'parcel']))
                ->when($workspace !== 'rides' && $workspace !== 'delivery' && $request->filled('service'), fn ($q) => $q->where('service', $request->service))
                ->get();

            return view('dashboard.complains.index', compact('complains', 'workspace'));
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
            $complain = Complain::with('user')->findOrFail($id);
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
