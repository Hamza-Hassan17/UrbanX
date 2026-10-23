<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\HasQueuePresets;
use App\Models\AdminActivityLog;
use App\Models\Ride;
use App\Models\RestaurantOrder;
use App\Services\GeocodingService;
use App\Services\RestaurantOrderCancellationService;
use App\Services\RideQueueFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Delivery workspace orders queue (Phase 2 of the admin workspace split).
 * Food and parcel jobs are both rows in the `rides` table with
 * ride_type='delivery' -- a food job has a matching restaurant_orders row
 * (restaurant_orders.ride_id), a parcel job doesn't. That's a real,
 * data-backed distinction (not invented), so the Food/Parcel chip is live
 * here, unlike Recurring/Ticket/Groups which stay disabled.
 *
 * Shares its window/status filtering with the Rides queue via
 * RideQueueFilterService, and its saved-filters storage via
 * HasQueuePresets -- same underlying table, namespaced by queue key so
 * the two queues' presets/last-used state don't collide.
 */
class DeliveryController extends Controller
{
    use HasQueuePresets;

    public function index()
    {
        $this->authorize('view delivery');

        [$queuePresets, $lastQueueFilters] = $this->loadQueuePresets('delivery');

        $geocoder = app(GeocodingService::class);
        $rides = Ride::where('ride_type', 'delivery')
            ->with(['driver:id,name', 'passenger:id,name,phone'])
            ->whereIn('status', array_merge(RideQueueFilterService::ACTIVE_STATUSES, ['completed', 'cancelled']))
            ->latest('requested_at')
            ->take(50)
            ->get();

        $rides = $this->withTypeKeys($rides)->map(fn ($ride) => $this->queueRow($ride, $geocoder));

        return view('dashboard.delivery.index', compact('rides', 'queuePresets', 'lastQueueFilters'));
    }

    public function queue(Request $request)
    {
        $this->authorize('view delivery');

        $query = Ride::where('ride_type', 'delivery')
            ->with(['driver:id,name', 'passenger:id,name,phone']);

        RideQueueFilterService::apply($query, $request);

        $total = (clone $query)->count();
        $rides = $query->take(200)->get();

        if (in_array($request->type, ['food', 'parcel'], true)) {
            $foodRideIds = $this->foodRideIds($rides->pluck('id')->all());
            $rides = $rides->filter(fn ($ride) => $request->type === 'food'
                ? in_array($ride->id, $foodRideIds, true)
                : !in_array($ride->id, $foodRideIds, true))
                ->values();
        }

        $geocoder = app(GeocodingService::class);
        $rides = $this->withTypeKeys($rides)
            ->map(fn ($ride) => $this->queueRow($ride, $geocoder))
            ->values();

        return response()->json([
            'count' => $total,
            'rides' => $rides,
        ]);
    }

    public function savePreset(Request $request)
    {
        $this->authorize('view delivery');

        return $this->saveQueuePreset($request, 'delivery');
    }

    /**
     * Manual Delivery Assignment (Delivery workspace) -- live counters, map
     * of riders + waiting pickups, and the delivery queue with Assign /
     * Reassign. Food orders are never created here (they need a real
     * restaurant order); they are only assigned from the queue. Parcels
     * moved to taxi drivers -- see parcelAssignment() (Rides workspace).
     */
    public function assignment()
    {
        $this->authorize('view delivery');
        $this->authorize('assign ride');

        try {
            [$queuePresets, $lastQueueFilters] = $this->loadQueuePresets('delivery');
            $mode = 'food';

            $geocoder = app(GeocodingService::class);
            $rides = Ride::where('ride_type', 'delivery')
                ->with(['driver:id,name', 'passenger:id,name,phone'])
                ->whereIn('status', array_merge(RideQueueFilterService::ACTIVE_STATUSES, ['completed', 'cancelled']))
                ->latest('requested_at')
                ->take(50)
                ->get();
            $rides = $this->withTypeKeys($rides)->map(fn ($ride) => $this->queueRow($ride, $geocoder));

            $restaurants = \App\Models\Restaurant::where('is_active', 'active')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('name')
                ->get(['id', 'name', 'latitude', 'longitude']);

            $parcelSettings = \App\Services\FareBreakdownService::parcelDeliveryFeeSettings();
            $cashLimit = \App\Services\FareBreakdownService::riderCashLimit();

            return view('dashboard.delivery.assignment', array_merge(
                compact('rides', 'queuePresets', 'lastQueueFilters', 'restaurants', 'parcelSettings', 'cashLimit', 'mode'),
                $this->dispatchSnapshot('food')
            ));
        } catch (\Throwable $th) {
            Log::error('Delivery Assignment Page Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function assignmentStats()
    {
        $this->authorize('view delivery');

        try {
            return response()->json($this->dispatchSnapshot('food'));
        } catch (\Throwable $th) {
            Log::error('Delivery Dispatch Stats Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], 500);
        }
    }

    /**
     * Manual Parcel Assignment (Rides workspace) -- parcels are handled by
     * taxi drivers of the "Parcel vehicle type" (Pricing & Fees). Same page
     * as Manual Delivery Assignment in parcel mode: booking form, counters,
     * map of those drivers, and the parcel queue with Assign / Reassign.
     */
    public function parcelAssignment()
    {
        $this->authorize('view custom rides');
        $this->authorize('assign ride');

        try {
            [$queuePresets, $lastQueueFilters] = $this->loadQueuePresets('parcel');
            $mode = 'parcel';

            $geocoder = app(GeocodingService::class);
            $rides = Ride::where('ride_type', 'parcel')
                ->with(['driver:id,name', 'passenger:id,name,phone'])
                ->whereIn('status', array_merge(RideQueueFilterService::ACTIVE_STATUSES, ['completed', 'cancelled']))
                ->latest('requested_at')
                ->take(50)
                ->get();
            $rides = $this->withTypeKeys($rides)->map(fn ($ride) => $this->queueRow($ride, $geocoder));

            $restaurants = \App\Models\Restaurant::where('is_active', 'active')
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('name')
                ->get(['id', 'name', 'latitude', 'longitude']);

            $parcelSettings = \App\Services\FareBreakdownService::parcelDeliveryFeeSettings();
            $cashLimit = \App\Services\DispatchSettingsService::taxiOutstandingLimit();
            $parcelVehicleTypeId = \App\Services\DispatchSettingsService::parcelVehicleTypeId();
            $parcelVehicleType = $parcelVehicleTypeId ? \App\Models\VehicleType::find($parcelVehicleTypeId) : null;

            return view('dashboard.delivery.assignment', array_merge(
                compact('rides', 'queuePresets', 'lastQueueFilters', 'restaurants', 'parcelSettings', 'cashLimit', 'mode', 'parcelVehicleType'),
                $this->dispatchSnapshot('parcel')
            ));
        } catch (\Throwable $th) {
            Log::error('Parcel Assignment Page Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function parcelAssignmentStats()
    {
        $this->authorize('view custom rides');

        try {
            return response()->json($this->dispatchSnapshot('parcel'));
        } catch (\Throwable $th) {
            Log::error('Parcel Dispatch Stats Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], 500);
        }
    }

    public function parcelQueue(Request $request)
    {
        $this->authorize('view custom rides');

        $query = Ride::where('ride_type', 'parcel')
            ->with(['driver:id,name', 'passenger:id,name,phone']);

        RideQueueFilterService::apply($query, $request);

        $total = (clone $query)->count();
        $geocoder = app(GeocodingService::class);
        $rides = $this->withTypeKeys($query->take(200)->get())
            ->map(fn ($ride) => $this->queueRow($ride, $geocoder))
            ->values();

        return response()->json(['count' => $total, 'rides' => $rides]);
    }

    public function saveParcelPreset(Request $request)
    {
        $this->authorize('view custom rides');

        return $this->saveQueuePreset($request, 'parcel');
    }

    /**
     * Parcel booking, quoting and reassigning are reachable from the Rides
     * workspace (parcels now) and from Delivery (older parcels, food).
     */
    private function authorizeDispatch(): void
    {
        if (!auth()->user()->can('view custom rides') && !auth()->user()->can('view delivery')) {
            abort(403);
        }
    }

    public function quoteParcel(Request $request)
    {
        $this->authorizeDispatch();

        $validator = Validator::make($request->all(), [
            'pickup_latitude' => 'required|numeric',
            'pickup_longitude' => 'required|numeric',
            'dropoff_latitude' => 'required|numeric',
            'dropoff_longitude' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $quote = $this->parcelQuote($request);
        if (isset($quote['error'])) {
            return response()->json(['message' => $quote['error']], 422);
        }

        return response()->json($quote);
    }

    /**
     * Admin books a parcel on a customer's behalf (phone/WhatsApp booking).
     * Same pricing and fields as the customer app's bookParcel -- fee is
     * always computed server-side. Optionally hands it straight to a rider.
     */
    public function storeParcel(Request $request)
    {
        $this->authorizeDispatch();
        $this->authorize('assign ride');

        $validator = Validator::make($request->all(), [
            'pickup_latitude' => 'required|numeric',
            'pickup_longitude' => 'required|numeric',
            'dropoff_latitude' => 'required|numeric',
            'dropoff_longitude' => 'required|numeric',
            'sender_name' => 'required|string|max:255',
            'sender_phone' => 'required|string|max:30',
            'receiver_name' => 'required|string|max:255',
            'receiver_phone' => 'required|string|max:30',
            'package_type' => 'required|string|max:255',
            'package_size' => 'required|in:small,medium,large',
            'parcel_notes' => 'nullable|string|max:500',
            'delivery_fee_paid_by' => 'required|in:sender,receiver',
            'rider_id' => 'nullable|exists:users,id',
            'scheduled_pickup_at' => 'nullable|date_format:Y-m-d\TH:i',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $quote = $this->parcelQuote($request);
        if (isset($quote['error'])) {
            return response()->json(['message' => $quote['error']], 422);
        }

        // Parcels are handled by taxi drivers of the "Parcel vehicle type".
        $parcelVehicleTypeId = \App\Services\DispatchSettingsService::parcelVehicleTypeId();
        if (!$parcelVehicleTypeId) {
            return response()->json(['message' => 'Choose the Parcel vehicle type in Pricing & Fees first.'], 422);
        }
        $vehicleType = \App\Models\VehicleType::find($parcelVehicleTypeId);

        $rider = null;
        if ($request->filled('rider_id')) {
            $rider = \App\Models\User::role('driver')
                ->whereHas('driverVehicle', fn ($q) => $q->where('vehicle_type_id', $parcelVehicleTypeId))
                ->find($request->rider_id);
            if (!$rider) {
                return response()->json(['message' => 'Selected driver does not drive the parcel vehicle type.'], 422);
            }
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $sender = $this->findOrCreateCustomer($request->sender_name, $request->sender_phone);

            $ride = new Ride();
            $ride->passenger_id = $sender->id;
            $ride->vehicle_type_id = $vehicleType->id;
            $ride->pickup_latitude = $request->pickup_latitude;
            $ride->pickup_longitude = $request->pickup_longitude;
            $ride->dropoff_latitude = $request->dropoff_latitude;
            $ride->dropoff_longitude = $request->dropoff_longitude;
            $ride->distance_km = $quote['distance_actual_km'];
            $ride->distance_charged_km = $quote['distance_charged_km'];
            $ride->subtotal = $quote['delivery_fee'];
            $ride->total_fare = $quote['delivery_fee'];
            $ride->requested_at = now();
            $ride->scheduled_pickup_at = $request->filled('scheduled_pickup_at')
                ? \Carbon\Carbon::parse($request->scheduled_pickup_at, 'Asia/Karachi')->utc()
                : null;
            $ride->status = 'requested';
            $ride->ride_type = 'parcel';
            $ride->sender_name = $request->sender_name;
            $ride->sender_phone = $request->sender_phone;
            $ride->receiver_name = $request->receiver_name;
            $ride->receiver_phone = $request->receiver_phone;
            $ride->package_type = $request->package_type;
            $ride->package_size = $request->package_size;
            $ride->parcel_notes = $request->parcel_notes;
            $ride->delivery_fee_paid_by = $request->delivery_fee_paid_by;
            $ride->payment_method = 'cod';
            $ride->payment_status = 'unpaid';
            $ride->delivery_code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $ride->created_by = auth()->id();

            if ($rider) {
                $ride->driver_id = $rider->id;
                $ride->status = 'accepted';
                $ride->accepted_at = now();
                $ride->status_updated_by = auth()->id();
                $ride->status_updated_by_role = 'admin';
            }
            $ride->save();

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();
            Log::error('Admin Parcel Booking Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], 500);
        }

        if ($rider) {
            \App\Services\DeliveryAssignmentService::assign($ride, $rider, null);
        } else {
            try {
                broadcast(new \App\Events\RideStatusUpdated($ride));
            } catch (\Throwable $e) {
                Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
            }
        }

        AdminActivityLog::record(
            'Booked parcel for customer',
            [],
            ['ride_id' => $ride->id, 'rider_id' => $rider?->id, 'delivery_fee' => $quote['delivery_fee']],
            Ride::class,
            $ride->id
        );

        return response()->json([
            'ride_id' => $ride->id,
            'delivery_fee' => $quote['delivery_fee'],
            'delivery_code' => $ride->delivery_code,
            'message' => $rider
                ? "Parcel booked and assigned to {$rider->name}. Delivery code: {$ride->delivery_code}"
                : "Parcel booked and sent to dispatch. Delivery code: {$ride->delivery_code}",
        ]);
    }

    /**
     * Swap the rider on a booked delivery that hasn't been picked up yet
     * (rider went quiet, broke down, etc). Old rider is told it was taken
     * back; the new one gets the same assignment side effects as Assign.
     */
    public function reassign(Request $request, $ride_id)
    {
        $this->authorizeDispatch();
        $this->authorize('reassign ride');

        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:users,id',
            'eta_minutes' => 'nullable|integer|min:0',
            'reason' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $ride = Ride::whereIn('ride_type', ['delivery', 'parcel'])->lockForUpdate()->findOrFail($ride_id);
            $order = RestaurantOrder::where('ride_id', $ride->id)->first();

            if (!$this->isReassignable($ride, $order)) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['message' => 'This delivery can no longer be reassigned (already picked up or finished).'], 422);
            }

            if ((int) $ride->driver_id === (int) $request->driver_id) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['message' => 'That rider already has this delivery.'], 422);
            }

            // The new rider/driver must drive the job's vehicle type (a
            // delivery bike for food, the parcel vehicle type for parcels).
            $newRider = \App\Models\User::role('driver')
                ->whereHas('driverVehicle', fn ($q) => $q->where('vehicle_type_id', $ride->vehicle_type_id))
                ->find($request->driver_id);
            if (!$newRider) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['message' => 'Selected driver does not drive the vehicle type for this job.'], 422);
            }

            $oldRider = \App\Models\User::find($ride->driver_id);

            \App\Models\RideOffer::where('ride_id', $ride->id)
                ->where('driver_id', $ride->driver_id)
                ->where('status', 'accepted')
                ->update(['status' => 'rejected']);

            \App\Models\RideDriverLog::updateOrCreate(
                ['ride_id' => $ride->id, 'driver_id' => $ride->driver_id],
                ['action' => 'cancelled', 'note' => 'Reassigned by admin. ' . ($request->reason ?? '')]
            );

            $ride->driver_id = $newRider->id;
            $ride->status = 'accepted';
            $ride->accepted_at = now();
            $ride->status_updated_by = auth()->id();
            $ride->status_updated_by_role = 'admin';
            $ride->save();

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['message' => 'Delivery not found.'], 404);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();
            Log::error('Delivery Reassign Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], 500);
        }

        if ($oldRider) {
            app('notificationService')->notifyUsers(
                [$oldRider],
                'Delivery Reassigned',
                'This delivery has been taken back by the admin and given to another rider.',
                'rides',
                $ride->id,
                'ride_details'
            );
        }

        \App\Services\DeliveryAssignmentService::assign(
            $ride,
            $newRider,
            $order,
            (int) $request->input('eta_minutes', 15),
            'Reassigned by admin'
        );

        AdminActivityLog::record(
            'Reassigned delivery rider',
            ['driver_id' => $oldRider?->id],
            ['driver_id' => $newRider->id, 'reason' => $request->reason],
            Ride::class,
            $ride->id
        );

        return response()->json(['message' => "Delivery reassigned to {$newRider->name}."]);
    }

    /**
     * Counters, rider positions and waiting-pickup pins for the dispatch
     * page -- shared by the first render and the 10s poll.
     */
    /**
     * $mode 'food' = Delivery workspace (delivery riders, food + older
     * parcels); 'parcel' = Rides workspace (taxi drivers of the parcel
     * vehicle type, parcels only). Balance shown is cash-in-hand for
     * riders, outstanding balance for taxi drivers.
     */
    private function dispatchSnapshot(string $mode = 'food'): array
    {
        $isParcel = $mode === 'parcel';
        $parcelVehicleTypeId = \App\Services\DispatchSettingsService::parcelVehicleTypeId();
        $cashLimit = $isParcel
            ? \App\Services\DispatchSettingsService::taxiOutstandingLimit()
            : \App\Services\FareBreakdownService::riderCashLimit();
        $rideTypes = $isParcel ? ['parcel'] : ['delivery'];

        $riders = \App\Models\User::role('driver')
            ->when($isParcel,
                fn ($q) => $q->whereHas('driverVehicle', fn ($v) => $v->where('vehicle_type_id', $parcelVehicleTypeId ?? 0)),
                fn ($q) => $q->whereHas('driverVehicle.vehicleType', fn ($v) => $v->where('is_delivery', '1')))
            ->with(['profile:id,user_id,city', 'driverVehicle:id,driver_id,vehicle_name,vehicle_make'])
            ->get(['id', 'name', 'phone', 'lat', 'lang', 'driver_status', 'online_status', 'last_location_at'])
            ->map(function ($rider) use ($cashLimit, $isParcel) {
                $cash = $isParcel
                    ? \App\Services\TaxiBalanceService::currentBalance($rider->id)
                    : \App\Services\RiderCashService::currentBalance($rider->id);
                return [
                    'id' => $rider->id,
                    'name' => $rider->name,
                    'phone' => $rider->phone,
                    'lat' => $rider->lat ? (float) $rider->lat : null,
                    'lng' => $rider->lang ? (float) $rider->lang : null,
                    'status' => $rider->driver_status,
                    // Batch 3 Part 4 -- online | busy | offline
                    'presence' => $rider->presenceStatus(),
                    'city' => $rider->profile->city ?? null,
                    'vehicle' => $rider->driverVehicle
                        ? trim($rider->driverVehicle->vehicle_name . ' ' . $rider->driverVehicle->vehicle_make)
                        : null,
                    'cash_balance' => round($cash, 2),
                    'over_cash_limit' => $cash >= $cashLimit,
                ];
            })
            ->values();

        $activeStatuses = RideQueueFilterService::ACTIVE_STATUSES;

        $unassigned = Ride::whereIn('ride_type', $rideTypes)
            ->whereIn('status', ['requested', 'no_driver_found'])
            ->whereNull('driver_id')
            ->get(['id', 'pickup_latitude', 'pickup_longitude', 'requested_at', 'scheduled_pickup_at']);
        $orderStatuses = RestaurantOrder::whereIn('ride_id', $unassigned->pluck('id'))
            ->pluck('status', 'ride_id');

        $waitingRestaurant = $unassigned->filter(fn ($r) => in_array($orderStatuses->get($r->id), ['pending', 'preparing'], true));
        $assignable = $unassigned->filter(function ($r) use ($orderStatuses) {
            $status = $orderStatuses->get($r->id);
            return $status === null || $status === 'accepted';
        });

        $pins = $assignable->map(fn ($r) => [
            'ride_id' => $r->id,
            'type' => $orderStatuses->has($r->id) ? 'food' : 'parcel',
            'lat' => (float) $r->pickup_latitude,
            'lng' => (float) $r->pickup_longitude,
        ])->values();

        $stats = [
            'available' => $riders->where('presence', 'online')->where('status', 'available')->count(),
            'busy' => $riders->where('presence', 'busy')->count(),
            'offline' => $riders->where('presence', 'offline')->count(),
            'dispatch' => $assignable->count(),
            'waiting_restaurant' => $waitingRestaurant->count(),
            'booked' => Ride::whereIn('ride_type', $rideTypes)->whereIn('status', $activeStatuses)->whereNotNull('driver_id')->count(),
            'completed' => Ride::whereIn('ride_type', $rideTypes)->where('status', 'completed')->whereDate('completed_at', today())->count(),
            'cancelled' => Ride::whereIn('ride_type', $rideTypes)->where('status', 'cancelled')->whereDate('cancelled_at', today())->count(),
        ];

        return compact('stats', 'riders', 'pins');
    }

    private function parcelQuote(Request $request): array
    {
        $settings = \App\Services\FareBreakdownService::parcelDeliveryFeeSettings();

        $lat1 = deg2rad((float) $request->pickup_latitude);
        $lng1 = deg2rad((float) $request->pickup_longitude);
        $lat2 = deg2rad((float) $request->dropoff_latitude);
        $lng2 = deg2rad((float) $request->dropoff_longitude);
        $actualKm = 6371 * acos(min(1, max(-1,
            cos($lat1) * cos($lat2) * cos($lng2 - $lng1) + sin($lat1) * sin($lat2)
        )));
        $chargedKm = \App\Services\FareBreakdownService::roundUpToKm($actualKm);

        if ($chargedKm > $settings['max_distance_km']) {
            return ['error' => "This address is beyond the {$settings['max_distance_km']} km parcel delivery limit."];
        }

        $fee = $settings['first_km_fee'];
        if ($chargedKm > 1) {
            $fee += ($chargedKm - 1) * $settings['per_km_fee'];
        }

        return [
            'distance_actual_km' => round($actualKm, 2),
            'distance_charged_km' => max($chargedKm, 1),
            'delivery_fee' => round($fee, 2),
            'max_distance_km' => $settings['max_distance_km'],
        ];
    }

    private function findOrCreateCustomer(string $name, string $phone): \App\Models\User
    {
        $existing = \App\Models\User::where('phone', $phone)->first();
        if ($existing) {
            return $existing;
        }

        $user = new \App\Models\User();
        $user->name = $name;
        $user->phone = $phone;
        $base = preg_replace('/[^0-9]/', '', $phone) ?: 'guest';
        $email = $base . '@guest.urbanx.local';
        while (\App\Models\User::where('email', $email)->exists()) {
            $email = $base . rand(1000, 9999) . '@guest.urbanx.local';
        }
        $user->email = $email;
        $user->password = \Illuminate\Support\Facades\Hash::make($phone);
        $username = strtolower(str_replace(' ', '', $name)) . rand(1000, 9999);
        while (\App\Models\User::where('username', $username)->exists()) {
            $username = strtolower(str_replace(' ', '', $name)) . rand(1000, 9999);
        }
        $user->username = $username;
        $user->save();
        $user->syncRoles('user');

        return $user;
    }

    /**
     * Before pickup only: a food order still at rider_assigned, or a parcel
     * the rider hasn't started yet.
     */
    private function isReassignable(Ride $ride, ?RestaurantOrder $order): bool
    {
        if (!$ride->driver_id) {
            return false;
        }

        if ($order) {
            return $order->status === 'rider_assigned';
        }

        return in_array($ride->status, ['accepted', 'en_route', 'arrived'], true);
    }

    /**
     * Batch 1 Part 6 -- admin can cancel a restaurant order at ANY status
     * (unlike the customer/restaurant paths, which are gated by status),
     * since this is the Delivery workspace's escalation path for a job
     * that's stuck or needs manual intervention.
     */
    public function cancelOrder(Request $request, $order_id)
    {
        $this->authorize('view delivery');

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $order = RestaurantOrder::findOrFail($order_id);

            if ($order->status === 'cancelled') {
                return redirect()->back()->with('error', 'This order is already cancelled.');
            }

            RestaurantOrderCancellationService::cancel($order, 'admin', $request->reason);

            return redirect()->back()->with('success', 'Order cancelled successfully');
        } catch (\Throwable $th) {
            Log::error('Admin Cancel Order Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    /**
     * Batch 1 Part 7 -- admin override when a rider can't get the delivery
     * code (locked after 5 failed attempts, or any other reason). Mandatory
     * reason, logged via AdminActivityLog same as Pricing & Fees changes.
     */
    public function overrideFoodDelivery(Request $request, $order_id)
    {
        $this->authorize('view delivery');

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $order = RestaurantOrder::findOrFail($order_id);
            $order->status = 'delivered';
            $order->delivery_code_locked = false;
            $order->delivery_override_by = auth()->id();
            $order->delivery_override_reason = $request->reason;
            $order->delivery_override_at = now();
            $order->save();

            AdminActivityLog::record(
                'Overrode food order delivery',
                [],
                ['order_id' => $order->id, 'reason' => $request->reason],
                RestaurantOrder::class,
                $order->id
            );

            try {
                broadcast(new \App\Events\RestaurantOrderUpdated($order));
            } catch (\Throwable $e) {
                Log::error('RestaurantOrderUpdated broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            return redirect()->back()->with('success', 'Delivery overridden successfully');
        } catch (\Throwable $th) {
            Log::error('Override Food Delivery Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function overrideParcelDelivery(Request $request, $ride_id)
    {
        $this->authorize('view delivery');

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $ride = Ride::findOrFail($ride_id);
            $ride->status = 'completed';
            $ride->completed_at = now();
            $ride->delivery_code_locked = false;
            $ride->delivery_override_by = auth()->id();
            $ride->delivery_override_reason = $request->reason;
            $ride->delivery_override_at = now();
            $ride->save();

            AdminActivityLog::record(
                'Overrode parcel delivery',
                [],
                ['ride_id' => $ride->id, 'reason' => $request->reason],
                Ride::class,
                $ride->id
            );

            return redirect()->back()->with('success', 'Delivery overridden successfully');
        } catch (\Throwable $th) {
            Log::error('Override Parcel Delivery Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    /**
     * Review follow-up -- resolves a parcel an admin has investigated
     * after Driver\RideController::markParcelReceiverUnreachable flagged
     * it. Two outcomes: send it back (closes the job as cancelled, same
     * as any other cancellation) or put it back in the dispatch queue for
     * a fresh rider (status back to 'requested', driver already cleared
     * when it was flagged).
     */
    public function resolveUnreachableParcel(Request $request, $ride_id)
    {
        $this->authorize('view delivery');

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:return_to_sender,reassign',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $ride = Ride::findOrFail($ride_id);

            if ($ride->status !== 'delivery_failed') {
                return redirect()->back()->with('error', 'This parcel is not flagged for review.');
            }

            if ($request->action === 'return_to_sender') {
                $ride->status = 'cancelled';
                $ride->cancelled_at = now();
                $ride->cancel_reason = 'Receiver unreachable -- returned to sender. ' . ($request->note ?? '');
            } else {
                $ride->status = 'requested';
                $ride->driver_id = null;
            }

            $ride->flagged_for_review = false;
            $ride->save();

            AdminActivityLog::record(
                'Resolved unreachable parcel (' . $request->action . ')',
                [],
                ['ride_id' => $ride->id, 'action' => $request->action, 'note' => $request->note],
                Ride::class,
                $ride->id
            );

            return redirect()->back()->with('success', 'Parcel resolved successfully');
        } catch (\Throwable $th) {
            Log::error('Resolve Unreachable Parcel Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    /**
     * Tags each Ride with a transient type_key/type_label (food/parcel)
     * based on whether a restaurant_orders row points at it. Batched into
     * one query rather than checked per-row.
     */
    private function withTypeKeys($rides)
    {
        $rideIds = $rides->pluck('id')->all();
        $orderStatuses = empty($rideIds)
            ? collect()
            : RestaurantOrder::whereIn('ride_id', $rideIds)->pluck('status', 'ride_id');
        $openAlertRideIds = empty($rideIds)
            ? []
            : \App\Models\StaleJobAlert::where('status', 'open')
                ->where('subject_type', Ride::class)
                ->whereIn('subject_id', $rideIds)
                ->pluck('subject_id')
                ->all();

        return $rides->map(function (Ride $ride) use ($orderStatuses, $openAlertRideIds) {
            $ride->is_food = $orderStatuses->has($ride->id);
            $ride->order_status = $orderStatuses->get($ride->id);
            $ride->has_open_alert = in_array($ride->id, $openAlertRideIds);
            return $ride;
        });
    }

    private function foodRideIds(array $rideIds): array
    {
        if (empty($rideIds)) {
            return [];
        }

        return RestaurantOrder::whereIn('ride_id', $rideIds)->pluck('ride_id')->all();
    }

    private function queueRow(Ride $ride, GeocodingService $geocoder): array
    {
        $queue = $ride->status === 'completed'
            ? 'completed'
            : ($ride->status === 'cancelled'
                ? 'cancelled'
                : ($ride->driver_id ? 'booked' : 'dispatch'));

        $pickupAt = $ride->scheduled_pickup_at ?? $ride->requested_at;
        $isFood = (bool) ($ride->is_food ?? false);
        $orderStatus = $ride->order_status ?? null;

        $assignable = $queue === 'dispatch' && in_array($ride->status, ['requested', 'no_driver_found'], true)
            && (!$isFood || $orderStatus === 'accepted');
        $reassignable = $queue === 'booked' && ($isFood
            ? $orderStatus === 'rider_assigned'
            : in_array($ride->status, ['accepted', 'en_route', 'arrived'], true));

        $staleReason = null;
        if (!empty($ride->has_open_alert)) {
            $staleReason = 'Open stale-job alert';
        } elseif ($queue === 'dispatch' && $pickupAt
            && $pickupAt->lte(now()->subMinutes(\App\Services\DispatchSettingsService::staleUnassignedMinutes()))) {
            $staleReason = $isFood && $orderStatus !== 'accepted'
                ? 'Restaurant has not accepted yet'
                : 'Waiting for a rider too long';
        }

        return [
            'driver_id' => $ride->driver_id,
            'pickup_lat' => (float) $ride->pickup_latitude,
            'pickup_lng' => (float) $ride->pickup_longitude,
            'order_status' => $orderStatus,
            'assignable' => $assignable,
            'reassignable' => $reassignable,
            'stale' => $staleReason !== null,
            'stale_reason' => $staleReason,
            'id'        => $ride->id,
            'time'      => optional($pickupAt)->setTimezone('Asia/Karachi')->format('H:i'),
            'pickup'    => $geocoder->reverseGeocode($ride->pickup_latitude, $ride->pickup_longitude)
                            ?? $ride->pickup_latitude . ', ' . $ride->pickup_longitude,
            'dropoff'   => $geocoder->reverseGeocode($ride->dropoff_latitude, $ride->dropoff_longitude)
                            ?? $ride->dropoff_latitude . ', ' . $ride->dropoff_longitude,
            'driver'    => $ride->driver->name ?? null,
            'passenger' => $ride->passenger->name ?? null,
            'passenger_id' => $ride->passenger_id,
            'phone'     => $ride->passenger->phone ?? null,
            'status'    => $ride->status,
            'queue'     => $queue,
            'fare'      => (float) $ride->total_fare,
            'ride_type' => $ride->ride_type,
            'type_key'  => $isFood ? 'food' : 'parcel',
            'type_label' => $isFood ? 'Food' : 'Parcel',
            // Batch 1 Part 5 -- null for food rows, populated for parcels.
            'sender_name' => $ride->sender_name,
            'sender_phone' => $ride->sender_phone,
            'receiver_name' => $ride->receiver_name,
            'receiver_phone' => $ride->receiver_phone,
            'package_type' => $ride->package_type,
            'package_size' => $ride->package_size,
            'parcel_notes' => $ride->parcel_notes,
            'delivery_fee_paid_by' => $ride->delivery_fee_paid_by,
        ];
    }
}
