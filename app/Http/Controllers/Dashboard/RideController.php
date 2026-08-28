<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BoostHour;
use App\Models\Ride;
use App\Models\RideExtraCharge;
use App\Models\RideOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            $rideExtraCharges = RideExtraCharge::where('ride_id', $id)->get();

            // Check for boost hour
            $requestedTime = \Carbon\Carbon::parse($ride->requested_at)->format('H:i:s');
            $boostHour = BoostHour::where('start', '<=', $requestedTime)
                ->where('end', '>=', $requestedTime)
                ->first();

            return view('dashboard.rides.show',compact('ride','rideOffers','rideExtraCharges','boostHour'));
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
        $this->authorize('update ride');

        $wantsJson = $request->wantsJson();

        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'status' => 'required|in:requested,accepted,en_route,arrived,started,completed,cancelled',
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'message' => $validator->errors()->first() ?: 'Validation Error!',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();

            $ride = Ride::findOrFail($id);
            $ride->status = $request->status;

            if ($request->status == 'accepted') {
                $ride->accepted_at = now();
            } elseif ($request->status == 'started') {
                $ride->started_at = now();
            } elseif ($request->status == 'completed') {
                $ride->completed_at = now();
            } elseif ($request->status == 'cancelled') {
                $ride->cancelled_at = now();
            }

            $ride->status_updated_by = auth()->id();
            $ride->status_updated_by_role = 'admin';

            $ride->save();

            DB::commit();

            if ($wantsJson) {
                return response()->json(['message' => 'Ride status updated successfully'], 200);
            }
            return redirect()->route('dashboard.rides.index')->with('success', 'Ride status Updated successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Ride status update Failed', ['error' => $th->getMessage()]);

            if ($wantsJson) {
                return response()->json(['message' => 'Something went wrong! Please try again later'], 500);
            }
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete ride');

        try {
            $ride = Ride::findOrFail($id);
            $ride->delete();

            return redirect()->route('dashboard.rides.index')->with('success', 'Ride deleted successfully');
        } catch (\Throwable $th) {
            Log::error('Ride Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }
}
