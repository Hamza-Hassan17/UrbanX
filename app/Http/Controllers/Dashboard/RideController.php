<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BoostHour;
use App\Models\Ride;
use App\Models\RideExtraCharge;
use App\Models\RideOffer;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RideController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase->getDatabase();
    }

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
            'pickup_latitude' => 'nullable|string',
            'pickup_longitude' => 'nullable|string',
            'dropoff_latitude' => 'nullable|string',
            'dropoff_longitude' => 'nullable|string',
            'driver_id' => 'nullable|exists:users,id',
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

            // Super admin can also correct pickup/dropoff on a dispatch/booked ride.
            // Only touch these when both lat+lng arrive together, so a partial
            // payload can never leave one coordinate stale against the other.
            if ($request->filled('pickup_latitude') && $request->filled('pickup_longitude')) {
                $ride->pickup_latitude = $request->pickup_latitude;
                $ride->pickup_longitude = $request->pickup_longitude;
            }
            if ($request->filled('dropoff_latitude') && $request->filled('dropoff_longitude')) {
                $ride->dropoff_latitude = $request->dropoff_latitude;
                $ride->dropoff_longitude = $request->dropoff_longitude;
            }

            $assignedDriver = null;
            if ($request->filled('driver_id')) {
                // Assigning a driver to an unclaimed ride from the dispatch queue is
                // only supported for taxi rides right now -- delivery jobs need their
                // linked restaurant_orders row synced the same way
                // DeliveryController::acceptRide does, which this doesn't do yet.
                if ($ride->ride_type !== 'ride') {
                    DB::rollBack();
                    $message = 'Manually assigning a driver to a delivery job is not supported yet.';
                    if ($wantsJson) {
                        return response()->json(['message' => $message], 422);
                    }
                    return redirect()->back()->with('error', $message);
                }
                if ($ride->driver_id) {
                    DB::rollBack();
                    $message = 'This ride already has a driver assigned.';
                    if ($wantsJson) {
                        return response()->json(['message' => $message], 422);
                    }
                    return redirect()->back()->with('error', $message);
                }

                $assignedDriver = User::find($request->driver_id);
                $ride->driver_id = $assignedDriver->id;
            }

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

            if ($assignedDriver) {
                // Same discovery pipeline the driver app already listens to, mirroring
                // CustomRideController::requestCustomRide()'s admin-assign branch.
                $this->firebase
                    ->getReference('ride_requests/vehicle_type_' . $ride->vehicle_type_id . '/ride_' . $ride->id)
                    ->set([
                        'ride_id' => $ride->id,
                        'passenger_id' => $ride->passenger_id,
                        'driver_id' => $ride->driver_id,
                        'vehicle_type_id' => $ride->vehicle_type_id,
                        'pickup_latitude' => $ride->pickup_latitude,
                        'pickup_longitude' => $ride->pickup_longitude,
                        'dropoff_latitude' => $ride->dropoff_latitude,
                        'dropoff_longitude' => $ride->dropoff_longitude,
                        'distance_km' => $ride->distance_km,
                        'duration_minutes' => $ride->duration_minutes,
                        'subtotal' => $ride->subtotal,
                        'discount_amount' => $ride->discount_amount,
                        'total_fare' => $ride->total_fare,
                        'status' => $ride->status,
                        'ride_type' => 'ride',
                        'requested_at' => optional($ride->requested_at)->toDateTimeString(),
                    ]);

                app('notificationService')->notifyUsers(
                    [$assignedDriver],
                    'New Ride Assigned',
                    'You have been assigned a new ride by the admin.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );
            }

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
