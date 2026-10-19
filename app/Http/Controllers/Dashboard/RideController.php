<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BoostHour;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideDriverLog;
use App\Models\RideExtraCharge;
use App\Models\RideLocationPing;
use App\Models\RideOffer;
use App\Models\User;
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
            // Review follow-up -- a food-delivery ride's cash-discrepancy
            // flag (if any) lives on its restaurant_orders row, not here.
            $relatedOrder = RestaurantOrder::where('ride_id', $ride->id)->first();

            // Check for boost hour
            $requestedTime = \Carbon\Carbon::parse($ride->requested_at)->format('H:i:s');
            $boostHour = BoostHour::where('start', '<=', $requestedTime)
                ->where('end', '>=', $requestedTime)
                ->first();

            return view('dashboard.rides.show',compact('ride','rideOffers','rideExtraCharges','boostHour','relatedOrder'));
        } catch (\Throwable $th) {
            Log::error('Ride Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * GPS breadcrumbs for a ride's "Replay Route" control on the show
     * page -- fetched on demand (not eager-loaded into show()) so rides
     * with long ping histories don't bloat the normal page load.
     */
    public function routePings(string $id)
    {
        $this->authorize('view ride');

        $ride = Ride::findOrFail($id);
        $pings = RideLocationPing::where('ride_id', $ride->id)
            ->orderBy('id')
            ->get(['latitude', 'longitude', 'heading', 'speed_kmh', 'created_at']);

        return response()->json([
            'pings' => $pings->map(fn ($p) => [
                'lat' => (float) $p->latitude,
                'lng' => (float) $p->longitude,
                'heading' => $p->heading,
                'speed_kmh' => $p->speed_kmh,
                'ts' => $p->created_at->toIso8601String(),
            ]),
        ]);
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

        // Assigning a driver is a distinct action from editing status/pickup/
        // dropoff -- checked here (before the transaction below starts) so an
        // AuthorizationException surfaces as a real 403 instead of getting
        // caught by the generic \Throwable handler further down and masked
        // as "Something went wrong".
        if ($request->filled('driver_id')) {
            $this->authorize('assign ride');

            // Reassigning a CANCELLED ride's driver (Live Ops Task 6) is a
            // distinct, more sensitive action than assigning an unclaimed
            // dispatch-queue ride -- it overrides a completed decision rather
            // than filling a gap, so it's gated separately.
            $existingRide = Ride::find($id);
            if ($existingRide && $existingRide->status === 'cancelled' && $existingRide->driver_id) {
                $this->authorize('reassign ride');
            }
        }

        // Editing the fare is a separate capability again -- 'update ride' lets
        // you touch status/pickup/dropoff, but not the money.
        if ($request->filled('total_fare')) {
            $this->authorize('edit ride payment');
        }

        $wantsJson = $request->wantsJson();

        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'status' => 'required|in:requested,accepted,en_route,arrived,started,completed,cancelled',
            'pickup_latitude' => 'nullable|string',
            'pickup_longitude' => 'nullable|string',
            'dropoff_latitude' => 'nullable|string',
            'dropoff_longitude' => 'nullable|string',
            'driver_id' => 'nullable|exists:users,id',
            'eta_minutes' => 'nullable|integer|min:0',
            'total_fare' => 'nullable|numeric|min:0',
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

            if ($request->filled('total_fare')) {
                $ride->total_fare = $request->total_fare;
            }

            $assignedDriver = null;
            $assignedRestaurantOrder = null;
            $bumpedDriver = null;
            if ($request->filled('driver_id')) {
                // Reassigning a cancelled ride to a different driver (Live Ops
                // Task 6) is the one case where an existing driver_id is
                // expected and allowed to be overwritten -- everywhere else,
                // an already-assigned ride blocks a second assignment as a
                // race-condition guard. The original driver is notified their
                // ride was pulled, same as a "taken back to base" message.
                // getOriginal(), not the live attribute -- $ride->status was
                // already overwritten to the new status above.
                $wasCancelled = $ride->getOriginal('status') === 'cancelled';

                if ($ride->driver_id && !$wasCancelled) {
                    DB::rollBack();
                    $message = 'This ride already has a driver assigned.';
                    if ($wantsJson) {
                        return response()->json(['message' => $message], 422);
                    }
                    return redirect()->back()->with('error', $message);
                }

                if ($wasCancelled && $ride->driver_id && (int) $ride->driver_id !== (int) $request->driver_id) {
                    $bumpedDriver = User::find($ride->driver_id);
                }

                // For a delivery job, "claimed" is gated by restaurant_orders.status
                // (accepted -> rider_assigned), not by rides.driver_id -- confirmed
                // against DeliveryController::acceptRide()/getLatestRides(), which
                // never touch/filter on driver_id at all for delivery. So an
                // unclaimed delivery job must still have its linked order sitting
                // at 'accepted'.
                // Parcel jobs have no restaurant_orders row -- unclaimed means the
                // ride itself is still 'requested'.
                if ($ride->ride_type !== 'ride') {
                    $assignedRestaurantOrder = RestaurantOrder::where('ride_id', $ride->id)->first();
                    $available = $assignedRestaurantOrder
                        ? $assignedRestaurantOrder->status === 'accepted'
                        : $ride->getOriginal('status') === 'requested';
                    if (!$available) {
                        DB::rollBack();
                        $message = 'This delivery job is no longer available to assign.';
                        if ($wantsJson) {
                            return response()->json(['message' => $message], 422);
                        }
                        return redirect()->back()->with('error', $message);
                    }
                }

                $assignedDriver = User::find($request->driver_id);
                $ride->driver_id = $assignedDriver->id;
                // Operator-traceability audit field (RBAC brief Task 4) -- this
                // ride was organically requested by a customer, not created by an
                // operator, so created_by would otherwise stay null forever even
                // though a dispatcher/super-admin just made the actual assignment
                // decision from the dispatch queue.
                $ride->created_by = auth()->id();
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

            if ($bumpedDriver) {
                app('notificationService')->notifyUsers(
                    [$bumpedDriver],
                    'Ride Reassigned',
                    'This ride has been taken back to base and reassigned to another driver.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );
            }

            if ($assignedDriver && $ride->ride_type === 'ride') {
                // Drivers discover new rides via getLatestRides() polling MySQL
                // directly, not via this broadcast -- this is purely a "current
                // state" push for the passenger watching their own ride.
                try {
                    broadcast(new \App\Events\RideStatusUpdated($ride));
                } catch (\Throwable $e) {
                    Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
                }

                app('notificationService')->notifyUsers(
                    [$assignedDriver],
                    'New Ride Assigned',
                    'You have been assigned a new ride by the admin.',
                    'rides',
                    $ride->id,
                    'ride_details'
                );
            } elseif ($assignedDriver && $ride->ride_type !== 'ride') {
                \App\Services\DeliveryAssignmentService::assign(
                    $ride,
                    $assignedDriver,
                    $assignedRestaurantOrder,
                    (int) $request->input('eta_minutes', 15)
                );
            }

            // Plain edit of an already-active ride (status / pickup / dropoff /
            // fare) -- push the new values everywhere the apps read ride data
            // from, and ping the driver. Without this the app keeps showing
            // whatever it cached when the ride was accepted.
            if (!$assignedDriver && $ride->driver_id && $ride->ride_type === 'ride') {
                // Notify the passenger app + the driver's status flow watch.
                try {
                    broadcast(new \App\Events\RideStatusUpdated($ride));
                } catch (\Throwable $e) {
                    Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
                }

                // The accepted offer is what the driver app's active-ride banner
                // actually reads (proposed_price -> the "PKR xxx" it shows). Keep
                // it in step with the edited fare, in the DB and in Firebase.
                $acceptedOffer = $ride->rideOffers()
                    ->where('driver_id', $ride->driver_id)
                    ->where('status', 'accepted')
                    ->latest()
                    ->first();
                if ($acceptedOffer) {
                    $acceptedOffer->proposed_price = $ride->total_fare;
                    $acceptedOffer->save();

                    try {
                        broadcast(new \App\Events\RideOfferStatusUpdated($acceptedOffer));
                    } catch (\Throwable $e) {
                        Log::error('RideOfferStatusUpdated broadcast failed', ['offer_id' => $acceptedOffer->id, 'error' => $e->getMessage()]);
                    }
                }

                if ($ride->driver) {
                    // A cancellation while the driver already has this ride
                    // active is a "drop what you're doing" event, not a minor
                    // detail tweak -- distinct title/message so it doesn't
                    // read as just another routine update, and flagged as a
                    // popup so the driver app can surface it immediately
                    // rather than leaving it to be discovered in the bell.
                    if ($ride->status === 'cancelled') {
                        $title = 'Ride Cancelled';
                        $message = 'This ride has been cancelled by the admin.';
                        $isPopup = true;
                    } elseif ($ride->status === 'completed') {
                        $title = 'Ride Completed';
                        $message = 'This ride has been marked completed by the admin.';
                        $isPopup = true;
                    } else {
                        $title = 'Ride Updated';
                        $message = 'The admin has updated the details of your ride.';
                        $isPopup = false;
                    }

                    app('notificationService')->notifyUsers(
                        [$ride->driver],
                        $title,
                        $message,
                        'rides',
                        $ride->id,
                        'ride_details',
                        $isPopup
                    );
                }
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
