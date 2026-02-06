<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RideOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RideController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view ride');
        try {
            $rides = Ride::with('passenger:id,name','driver:id,name')->latest()->get();
            return view('dashboard.rides.index',compact('rides'));
        } catch (\Throwable $th) {
            Log::error('Rides Index Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('view ride');
        try {
            $ride = Ride::with('passenger','driver','vehicleType','promoCode')->findOrFail($id);
            $rideOffers = RideOffer::where('ride_id', $id)->with('driver:id,name,phone')->get();
            return view('dashboard.rides.show',compact('ride','rideOffers'));
        } catch (\Throwable $th) {
            Log::error('Ride Show Failed', ['error' => $th->getMessage()]);
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
}
