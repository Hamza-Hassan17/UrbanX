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
use Illuminate\Support\Facades\DB;
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
    /** Food order details are editable by admin until the rider has the food. */
    private const EDITABLE_ORDER_STATUSES = ['pending', 'accepted', 'rider_assigned', 'preparing', 'ready_for_pickup'];

    use HasQueuePresets;

    public function index()
    {
        $this->authorize('view delivery');

        [$queuePresets, $lastQueueFilters] = $this->loadQueuePresets('delivery');

        $geocoder = app(GeocodingService::class);
        $rides = Ride::where('ride_type', 'delivery')
            ->whereIn('id', RestaurantOrder::whereNotNull('ride_id')->select('ride_id')) // food only; parcels live in the Rides workspace
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
            ->whereIn('id', RestaurantOrder::whereNotNull('ride_id')->select('ride_id')) // food only; parcels live in the Rides workspace
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
                ->whereIn('id', RestaurantOrder::whereNotNull('ride_id')->select('ride_id')) // food only; parcels live in the Rides workspace
                ->with(['driver:id,name', 'passenger:id,name,phone'])
                ->whereIn('status', array_merge(RideQueueFilterService::ACTIVE_STATUSES, ['completed', 'cancelled']))
                ->latest('requested_at')
                ->take(50)
                ->get();
            $rides = $this->withTypeKeys($rides)->map(fn ($ride) => $this->queueRow($ride, $geocoder));

            // Only verified (approved) restaurants can be booked.
            $restaurants = \App\Models\Restaurant::where('is_active', 'active')
                ->where('is_approved', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('name')
                ->get(['id', 'name', 'latitude', 'longitude']);

            $parcelSettings = \App\Services\FareBreakdownService::parcelDeliveryFeeSettings();
            $cashLimit = \App\Services\FareBreakdownService::riderCashLimit();
            $foodSettings = \App\Services\FareBreakdownService::foodDeliveryFeeSettings();

            return view('dashboard.delivery.assignment', array_merge(
                compact('rides', 'queuePresets', 'lastQueueFilters', 'restaurants', 'parcelSettings', 'cashLimit', 'mode', 'foodSettings'),
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

            // Only verified (approved) restaurants can be booked.
            $restaurants = \App\Models\Restaurant::where('is_active', 'active')
                ->where('is_approved', true)
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
            if ($busy = $this->busyMessage($rider)) {
                return response()->json(['message' => $busy], 422);
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
     * Admin books a food delivery (phone order): pickup at a restaurant,
     * drop-off at the customer. Creates the order (already accepted by the
     * restaurant) + its delivery ride; optionally hands it to a rider.
     * Delivery fee always computed server-side from Pricing & Fees.
     */
    /** Orderable menu of one restaurant, for the manual booking form. */
    public function restaurantMenu($restaurant_id)
    {
        $this->authorize('view delivery');
        $this->authorize('assign ride');

        $menuRestaurant = \App\Models\Restaurant::find($restaurant_id);
        if (!$menuRestaurant || !$menuRestaurant->is_approved) {
            return response()->json(['message' => 'This restaurant is not verified yet. Only verified restaurants can take orders.'], 422);
        }

        $menus = \App\Models\RestaurantMenu::where('restaurant_id', $restaurant_id)->pluck('name', 'id');

        $items = \App\Models\RestaurantItem::where('restaurant_id', $restaurant_id)
            ->where('is_active', 'active')
            ->where('is_available', '1')
            ->with('optionGroups.options')
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'menu' => $menus[$item->restaurant_menu_id] ?? __('Other'),
                'price' => $item->basePrice(),
                'groups' => $item->optionGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'min' => $group->effectiveMin(),
                    'max' => $group->effectiveMax(),
                    'options' => $group->options->where('is_available', true)->values()->map(fn ($option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price' => (float) $option->price_adjustment,
                    ]),
                ]),
            ]);

        return response()->json(['items' => $items]);
    }

    public function storeFoodDelivery(Request $request)
    {
        $this->authorize('view delivery');
        $this->authorize('assign ride');

        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|exists:restaurants,id',
            'dropoff_latitude' => 'required|numeric',
            'dropoff_longitude' => 'required|numeric',
            'dropoff_address' => 'nullable|string|max:500',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:30',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'items.*.option_ids' => 'nullable|array',
            'items.*.option_ids.*' => 'integer',
            'admin_reason' => 'required|string|min:5|max:1000',
            'rider_note' => 'nullable|string|max:255',
            'rider_id' => 'nullable|exists:users,id',
        ], [
            'items.required' => 'Please select at least one food item.',
            'admin_reason.required' => 'Please write why this order is being booked manually.',
            'admin_reason.min' => 'Please write a clearer reason for this manual booking.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $restaurant = \App\Models\Restaurant::find($request->restaurant_id);

        // Even if an unverified restaurant reaches here (stale page, edited request).
        if (!$restaurant->is_approved) {
            return response()->json(['message' => "{$restaurant->name} is not verified yet. Only verified restaurants can take orders."], 422);
        }
        if ($restaurant->is_active !== 'active') {
            return response()->json(['message' => "{$restaurant->name} is switched off. Turn it on before booking an order."], 422);
        }

        // Same server-side pricing and add-on rules as a customer's order.
        $pricedLines = [];
        foreach ($request->input('items') as $input) {
            $menuItem = \App\Models\RestaurantItem::where('restaurant_id', $restaurant->id)->find($input['id']);
            if (!$menuItem) {
                return response()->json(['message' => 'A selected item does not belong to this restaurant.'], 422);
            }
            try {
                $line = \App\Services\MenuOptionService::priceLine($menuItem, $input['option_ids'] ?? []);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            $pricedLines[] = [$menuItem, (int) $input['quantity'], $line];
        }
        if (!$restaurant->latitude || !$restaurant->longitude) {
            return response()->json(['message' => 'This restaurant has no location set.'], 422);
        }

        $rider = null;
        if ($request->filled('rider_id')) {
            $rider = \App\Models\User::role('rider')
                ->whereHas('driverVehicle')
                ->with('driverVehicle')
                ->find($request->rider_id);
            if (!$rider) {
                return response()->json(['message' => 'Selected rider does not have a delivery vehicle.'], 422);
            }
            $prep = (int) collect($pricedLines)->max(fn ($l) => (int) ($l[0]->preparation_time ?? 0));
            $newJob = [
                'restaurant_id' => (int) $restaurant->id,
                'pickup_lat' => (float) $restaurant->latitude,
                'pickup_lng' => (float) $restaurant->longitude,
                'drop_lat' => (float) $request->dropoff_latitude,
                'drop_lng' => (float) $request->dropoff_longitude,
                // The kitchen starts now (admin bookings are already accepted).
                'prep_left' => $prep > 0 ? $prep : \App\Services\OrderBatchingService::defaultPrepMinutes(),
            ];
            if ($busy = $this->busyMessage($rider, $newJob)) {
                return response()->json(['message' => $busy], 422);
            }
        }

        $feeSettings = \App\Services\FareBreakdownService::foodDeliveryFeeSettings();
        $lat1 = deg2rad((float) $restaurant->latitude);
        $lng1 = deg2rad((float) $restaurant->longitude);
        $lat2 = deg2rad((float) $request->dropoff_latitude);
        $lng2 = deg2rad((float) $request->dropoff_longitude);
        $actualKm = 6371 * acos(min(1, max(-1,
            cos($lat1) * cos($lat2) * cos($lng2 - $lng1) + sin($lat1) * sin($lat2)
        )));
        $chargedKm = max(\App\Services\FareBreakdownService::roundUpToKm($actualKm), 1);
        $deliveryFee = $feeSettings['first_km_fee'] + max($chargedKm - 1, 0) * $feeSettings['per_km_fee'];
        $deliveryFee = round($deliveryFee, 2);
        $orderAmount = round(collect($pricedLines)->sum(fn ($l) => $l[2]['unit_price'] * $l[1]), 2);

        $vehicleTypeId = $rider
            ? $rider->driverVehicle->vehicle_type_id
            : \App\Models\VehicleType::where('is_delivery', '1')->where('is_active', 'active')->value('id');
        if (!$vehicleTypeId) {
            return response()->json(['message' => 'No delivery vehicle type is set up.'], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $customer = $this->findOrCreateCustomer($request->customer_name, $request->customer_phone);

            $order = new RestaurantOrder();
            $order->restaurant_id = $restaurant->id;
            $order->customer_id = $customer->id;
            $order->order_number = 'ORD-' . strtoupper(uniqid());
            $order->subtotal = $orderAmount;
            $order->discount = 0;
            $order->tax = 0;
            $order->platform_fee = 0;
            $order->delivery_fee = $deliveryFee;
            $order->total_price = round($orderAmount + $deliveryFee, 2);
            $order->delivery_lat = $request->dropoff_latitude;
            $order->delivery_lang = $request->dropoff_longitude;
            $order->delivery_address = $request->dropoff_address;
            $order->rider_note = $request->rider_note;
            $order->admin_booking_reason = $request->admin_reason;
            $order->payment_method = 'cod';
            $order->payment_status = 'unpaid';
            // Goes to the restaurant first, like a customer's order; riders
            // get it once the restaurant accepts.
            $order->status = 'pending';
            $order->delivery_code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $order->discount_funded_by = 'platform';
            $order->fare_breakdown = \App\Services\FareBreakdownService::buildOrderSnapshot([
                'restaurant' => \App\Models\Restaurant::find($order->restaurant_id),
                'subtotal' => $orderAmount,
                'discount' => 0,
                'discount_funded_by' => 'platform',
                'distance_actual_km' => $actualKm,
                'distance_charged_km' => $chargedKm,
                'delivery_fee' => $deliveryFee,
            ]);
            $order->save();

            foreach ($pricedLines as [$menuItem, $quantity, $line]) {
                $order->items()->create([
                    'restaurant_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'quantity' => $quantity,
                    'price_per_item' => $line['unit_price'],
                    'base_price' => $line['base_price'],
                    'total_price' => round($line['unit_price'] * $quantity, 2),
                    'selected_options' => $line['selected_options'] ?: null,
                    'notes' => null,
                ]);
            }

            $ride = new Ride();
            $ride->passenger_id = $customer->id;
            $ride->vehicle_type_id = $vehicleTypeId;
            $ride->pickup_latitude = $restaurant->latitude;
            $ride->pickup_longitude = $restaurant->longitude;
            $ride->dropoff_latitude = $request->dropoff_latitude;
            $ride->dropoff_longitude = $request->dropoff_longitude;
            $ride->distance_km = round($actualKm, 2);
            $ride->subtotal = $deliveryFee;
            $ride->total_fare = $deliveryFee;
            $ride->requested_at = now();
            $ride->status = 'requested';
            $ride->ride_type = 'delivery';
            $ride->created_by = auth()->id();
            // The admin's chosen rider gets it when the restaurant accepts
            // (DeliveryAssignmentService::assignPreferredRider).
            $ride->preferred_rider_id = $rider?->id;
            $ride->save();

            $order->ride_id = $ride->id;
            $order->save();

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();
            Log::error('Admin Food Delivery Booking Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], 500);
        }

        \App\Services\FoodOrderFlowService::notifyRestaurant($order, 'New order', "New order #{$order->order_number} - Rs. " . number_format((float) $order->total_price, 2) . ". Please accept it.");
        try {
            broadcast(new \App\Events\RideStatusUpdated($ride));
        } catch (\Throwable $e) {
            Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
        }

        AdminActivityLog::record(
            'Booked food delivery for customer',
            [],
            ['ride_id' => $ride->id, 'order_id' => $order->id, 'rider_id' => $rider?->id, 'delivery_fee' => $deliveryFee, 'reason' => $request->admin_reason],
            Ride::class,
            $ride->id
        );

        return response()->json([
            'ride_id' => $ride->id,
            'order_id' => $order->id,
            'delivery_fee' => $deliveryFee,
            'delivery_code' => $order->delivery_code,
            'message' => ($rider
                ? "Order sent to {$restaurant->name}. Once they accept it, it goes to {$rider->name}."
                : "Order sent to {$restaurant->name}. Once they accept it, it goes to riders near the restaurant.")
                . " Collect Rs " . round($orderAmount + $deliveryFee) . ". Delivery code: {$order->delivery_code}",
        ]);
    }

    /**
     * Swap the rider on a booked delivery that hasn't been picked up yet
     * (rider went quiet, broke down, etc). Old rider is told it was taken
     * back; the new one gets the same assignment side effects as Assign.
     */
    /**
     * Null if the rider/driver is free, else why they can't take another job:
     * they're already on a delivery, parcel or ride that isn't finished.
     */
    private function busyMessage(\App\Models\User $person, ?array $newJob = null, ?Ride $newRide = null): ?string
    {
        // A second food order from the same restaurant may be combined
        // (Pricing & Fees -> Order batching, OrderBatchingService).
        if ($newRide && \App\Services\OrderBatchingService::check((int) $person->id, $newRide) === null) {
            return null;
        }
        if ($newJob && \App\Services\OrderBatchingService::checkJob((int) $person->id, $newJob) === null) {
            return null;
        }

        $current = Ride::where('driver_id', $person->id)
            ->whereIn('status', ['accepted', 'en_route', 'arrived', 'started'])
            ->latest('id')
            ->first();
        if (!$current) {
            return null;
        }

        $order = RestaurantOrder::where('ride_id', $current->id)->first();
        if ($order && in_array($order->status, ['cancelled', 'rejected', 'delivered', 'completed'], true)) {
            return null; // the order is closed; the delivery row just lags behind
        }

        $job = $order ? "order #{$order->order_number}" : ($current->ride_type === 'ride' ? "ride #{$current->id}" : "parcel #{$current->id}");

        return "{$person->name} is already on duty with {$job}. Choose another rider, or wait until it's finished.";
    }

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
            $newRider = \App\Models\User::role($order ? 'rider' : 'driver')
                ->whereHas('driverVehicle', fn ($q) => $q->where('vehicle_type_id', $ride->vehicle_type_id))
                ->find($request->driver_id);
            if (!$newRider) {
                \Illuminate\Support\Facades\DB::rollBack();
                $hasVehicle = \App\Models\DriverVehicle::where('driver_id', $request->driver_id)->exists();
                return response()->json(['message' => $hasVehicle
                    ? 'Selected ' . ($order ? 'rider' : 'driver') . ' drives a different vehicle type than this job needs.'
                    : 'Selected ' . ($order ? 'rider' : 'driver') . ' has no vehicle saved yet (verified without documents?). Add their vehicle first.'], 422);
            }

            if ($busy = $this->busyMessage($newRider, null, $ride)) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['message' => $busy], 422);
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

        // The restaurant expects a specific rider; tell them it changed.
        // (The customer isn't told: they only hear about the rider at pickup.)
        if ($order) {
            \App\Services\FoodOrderFlowService::notifyRestaurant($order, 'Rider changed', "Order #{$order->order_number} will now be collected by {$newRider->name}.");
        }

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
        $jobs = fn () => Ride::whereIn('ride_type', $rideTypes)
            ->when(!$isParcel, fn ($q) => $q->whereIn('id', RestaurantOrder::whereNotNull('ride_id')->select('ride_id')));

        // Food jobs -> Rider app accounts; parcels -> Driver app accounts.
        $riders = \App\Models\User::role($isParcel ? 'driver' : 'rider')
            ->when($isParcel,
                fn ($q) => $q->whereHas('driverVehicle', fn ($v) => $v->where('vehicle_type_id', $parcelVehicleTypeId ?? 0)))
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

        $unassigned = $jobs()
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
            'booked' => $jobs()->whereIn('status', $activeStatuses)->whereNotNull('driver_id')->count(),
            'completed' => $jobs()->where('status', 'completed')->whereDate('completed_at', today())->count(),
            'cancelled' => $jobs()->where('status', 'cancelled')->whereDate('cancelled_at', today())->count(),
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
            // Until the rider has the food: the restaurant moving the order
            // on to preparing / ready doesn't mean it was picked up.
            return in_array($order->status, ['accepted', 'rider_assigned', 'preparing', 'ready_for_pickup'], true);
        }

        return in_array($ride->status, ['accepted', 'en_route', 'arrived'], true);
    }

    /**
     * Batch 1 Part 6 -- admin can cancel a restaurant order at ANY status
     * (unlike the customer/restaurant paths, which are gated by status),
     * since this is the Delivery workspace's escalation path for a job
     * that's stuck or needs manual intervention.
     */
    /** Food order details for the admin's Edit dialog (by delivery/ride id). */
    public function editOrder($ride_id)
    {
        $this->authorize('view delivery');
        $this->authorize('update ride');

        $order = RestaurantOrder::with('restaurant:id,name')->where('ride_id', $ride_id)->first();
        if (!$order) {
            return response()->json(['message' => 'Food order not found.'], 404);
        }
        $ride = Ride::find($ride_id);

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'delivery_code' => $order->delivery_code,
            'restaurant' => $order->restaurant?->name,
            'status' => $order->status,
            'has_rider' => (bool) $ride?->driver_id,
            'statuses' => $this->editableStatusesFor($ride),
            'delivery_address' => $order->delivery_address,
            'dropoff_lat' => (float) $order->delivery_lat,
            'dropoff_lng' => (float) $order->delivery_lang,
            'delivery_fee' => (float) $order->delivery_fee,
            'subtotal' => (float) $order->subtotal,
            'rider_note' => $order->rider_note,
            'editable' => in_array($order->status, self::EDITABLE_ORDER_STATUSES, true)
                && !in_array($ride?->status, ['completed', 'cancelled', 'started'], true),
            // Rider: the one on it, or (still at the restaurant) the one chosen for it.
            'rider_id' => $ride?->driver_id ? (int) $ride->driver_id : ($ride?->preferred_rider_id ? (int) $ride->preferred_rider_id : null),
            'rider_mode' => $ride?->driver_id ? 'reassign' : ($order->status === 'pending' ? 'preferred' : 'assign'),
            'riders' => $this->riderChoicesFor($ride),
        ]);
    }

    /**
     * Admin edits a food order before pickup: kitchen status, delivery
     * address/location, delivery fee, rider note. Picked up / delivered /
     * cancelled have their own actions (override delivery, cancel).
     */
    public function updateOrder(Request $request, $ride_id)
    {
        $this->authorize('view delivery');
        $this->authorize('update ride');

        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
            'delivery_address' => 'nullable|string|max:500',
            'dropoff_latitude' => 'nullable|numeric|between:-90,90|required_with:dropoff_longitude',
            'dropoff_longitude' => 'nullable|numeric|between:-180,180|required_with:dropoff_latitude',
            'delivery_fee' => 'nullable|numeric|min:0|max:100000',
            'rider_note' => 'nullable|string|max:255',
            'reason' => 'required|string|min:3|max:500',
            'rider_id' => 'nullable|integer|exists:users,id',
        ], ['reason.required' => 'Please write why the order is being changed.']);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $notifyCustomer = null;
        try {
            DB::beginTransaction();

            $ride = Ride::lockForUpdate()->find($ride_id);
            $order = RestaurantOrder::where('ride_id', $ride_id)->lockForUpdate()->first();
            if (!$ride || !$order) {
                DB::rollBack();
                return response()->json(['message' => 'Food order not found.'], 404);
            }
            if (!in_array($order->status, self::EDITABLE_ORDER_STATUSES, true)
                || in_array($ride->status, ['completed', 'cancelled', 'started'], true)) {
                DB::rollBack();
                return response()->json(['message' => 'This order can no longer be edited (already picked up or finished).'], 422);
            }
            $allowed = array_keys($this->editableStatusesFor($ride));
            if (!in_array($request->status, $allowed, true)) {
                DB::rollBack();
                return response()->json(['message' => 'That status is not allowed for this order right now.'], 422);
            }

            $before = $order->only(['status', 'delivery_address', 'delivery_lat', 'delivery_lang', 'delivery_fee', 'rider_note']);
            $oldStatus = $order->status;

            // New drop-off location: the fee follows the distance unless the admin typed one.
            $fee = $request->filled('delivery_fee') ? round((float) $request->delivery_fee, 2) : null;
            if ($request->filled('dropoff_latitude')) {
                $order->delivery_lat = $request->dropoff_latitude;
                $order->delivery_lang = $request->dropoff_longitude;
                $ride->dropoff_latitude = $request->dropoff_latitude;
                $ride->dropoff_longitude = $request->dropoff_longitude;
                $km = $this->km($ride->pickup_latitude, $ride->pickup_longitude, $request->dropoff_latitude, $request->dropoff_longitude);
                $ride->distance_km = round($km, 2);
                if ($fee === null) {
                    $s = \App\Services\FareBreakdownService::foodDeliveryFeeSettings();
                    $charged = max(\App\Services\FareBreakdownService::roundUpToKm($km), 1);
                    $fee = round($s['first_km_fee'] + max($charged - 1, 0) * $s['per_km_fee'], 2);
                }
            }
            if ($request->has('delivery_address')) {
                $order->delivery_address = $request->delivery_address;
            }
            if ($request->has('rider_note')) {
                $order->rider_note = $request->rider_note;
            }
            if ($fee !== null && $fee !== (float) $order->delivery_fee) {
                $order->delivery_fee = $fee;
                $order->total_price = round((float) $order->subtotal - (float) $order->discount + (float) $order->tax + (float) $order->platform_fee + $fee, 2);
                $breakdown = is_array($order->fare_breakdown) ? $order->fare_breakdown : (json_decode((string) $order->fare_breakdown, true) ?: null);
                if (is_array($breakdown) && array_key_exists('delivery_fee', $breakdown)) {
                    $breakdown['delivery_fee'] = $fee;
                    $order->fare_breakdown = $breakdown;
                }
                $ride->subtotal = $fee;
                $ride->total_fare = $fee;
                \App\Models\RideOffer::where('ride_id', $ride->id)->where('status', 'accepted')->update(['proposed_price' => $fee]);
            }

            // Still at the restaurant: the chosen rider is remembered now so
            // an "accepted" in this same save hands it straight to him.
            $riderHandled = false;
            if ($request->filled('rider_id') && !$ride->driver_id && $oldStatus === 'pending'
                && (int) $request->rider_id !== (int) $ride->preferred_rider_id) {
                $ok = \App\Models\User::role('rider')
                    ->whereHas('driverVehicle', fn ($q) => $q->where('vehicle_type_id', $ride->vehicle_type_id))
                    ->whereKey($request->rider_id)->exists();
                if (!$ok) {
                    DB::rollBack();
                    return response()->json(['message' => 'Selected rider does not drive the vehicle type this order needs.'], 422);
                }
                $ride->preferred_rider_id = (int) $request->rider_id;
                $riderHandled = true;
            }

            $order->status = $request->status;
            $order->save();
            if ($oldStatus === 'pending' && $request->status === 'accepted' && $ride->status === 'requested') {
                // Accepted on the restaurant's behalf: riders are offered it from now.
                $ride->requested_at = now();
            }
            $ride->status_updated_by = auth()->id();
            $ride->status_updated_by_role = 'admin';
            $ride->save();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Admin Edit Food Order Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], 500);
        }

        if ($oldStatus !== $order->status) {
            $labels = ['accepted' => 'Your order was accepted.', 'preparing' => 'Your food is being prepared.', 'ready_for_pickup' => 'Your food is ready and waiting for the rider.'];
            if (isset($labels[$order->status])) {
                \App\Services\FoodOrderFlowService::notifyCustomer($order, 'Order update', $labels[$order->status]);
            }
            \App\Services\FoodOrderFlowService::notifyRestaurant($order, 'Order updated by admin', "Order #{$order->order_number} status changed to " . str_replace('_', ' ', $order->status) . '.');
            if ($oldStatus === 'pending' && $order->status === 'accepted') {
                \App\Services\DeliveryAssignmentService::assignPreferredRider($order);
            }
        }
        if ($ride->driver_id) {
            app('notificationService')->notifyUsers([\App\Models\User::find($ride->driver_id)], 'Order updated', "Order #{$order->order_number} was updated by the admin. Please check the details.", 'rides', $ride->id, 'ride_details');
        }
        try {
            broadcast(new \App\Events\RestaurantOrderUpdated($order->fresh()));
            broadcast(new \App\Events\RideStatusUpdated($ride->fresh()));
        } catch (\Throwable $e) {
            Log::error('Order edit broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        AdminActivityLog::record(
            'Edited food order',
            $before,
            $order->only(['status', 'delivery_address', 'delivery_lat', 'delivery_lang', 'delivery_fee', 'rider_note']) + ['reason' => $request->reason],
            RestaurantOrder::class,
            $order->id
        );

        if ($request->filled('rider_id') && !$riderHandled) {
            try {
                $err = $this->applyRiderChange($ride->fresh(), $order->fresh(), (int) $request->rider_id, $request->reason);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $err = 'You are not allowed to reassign deliveries.';
            }
            if ($err) {
                return response()->json(['message' => "Order details saved, but the rider was not changed: {$err}"], 422);
            }
        }

        $rideNow = $ride->fresh();
        $riderName = $rideNow->driver_id ? \App\Models\User::find($rideNow->driver_id)?->name : null;
        $chosen = !$riderName && $rideNow->preferred_rider_id ? \App\Models\User::find($rideNow->preferred_rider_id)?->name : null;

        return response()->json(['message' => "Order #{$order->order_number} updated."
            . ($riderName ? " Rider: {$riderName}." : ($chosen ? " {$chosen} gets it when the restaurant accepts." : ''))]);
    }

    /** Riders who can drive this job, nearest to the restaurant first, with a busy flag. */
    private function riderChoicesFor(?Ride $ride): array
    {
        if (!$ride) {
            return [];
        }
        $busy = \App\Services\FoodDispatchService::busyRiderIds();

        return \App\Models\User::role('rider')
            ->whereHas('driverVehicle', fn ($q) => $q->where('vehicle_type_id', $ride->vehicle_type_id))
            ->get(['id', 'name', 'lat', 'lang', 'online_status'])
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'name' => $u->name,
                'busy' => in_array((int) $u->id, $busy, true) && (int) $u->id !== (int) $ride->driver_id,
                'online' => $u->online_status !== 'offline',
                'km' => ($u->lat && $u->lang) ? round($this->km($u->lat, $u->lang, $ride->pickup_latitude, $ride->pickup_longitude), 1) : null,
            ])
            ->sortBy(fn ($r) => $r['km'] ?? 9999)
            ->values()
            ->all();
    }

    /**
     * Rider change from the Edit dialog. Null on success, else the error.
     * Pending order -> remember as the chosen rider; accepted without a
     * rider -> assign now; has a rider -> reassign (same rules as Reassign).
     */
    private function applyRiderChange(Ride $ride, RestaurantOrder $order, int $riderId, string $reason): ?string
    {
        if ((int) $ride->driver_id === $riderId) {
            return null;
        }
        if ($ride->driver_id) {
            $res = $this->reassign(new Request(['driver_id' => $riderId, 'reason' => $reason]), $ride->id);
            return $res->getStatusCode() < 300 ? null : ($res->getData(true)['message'] ?? 'Could not reassign.');
        }

        $rider = \App\Models\User::role('rider')
            ->whereHas('driverVehicle', fn ($q) => $q->where('vehicle_type_id', $ride->vehicle_type_id))
            ->find($riderId);
        if (!$rider) {
            return 'Selected rider does not drive the vehicle type this order needs.';
        }

        if ($order->status === 'pending') {
            $ride->preferred_rider_id = $rider->id;
            $ride->save();
            return null;
        }

        if ($busy = $this->busyMessage($rider, null, $ride)) {
            return $busy;
        }
        $ride->driver_id = $rider->id;
        $ride->status = 'accepted';
        $ride->accepted_at = now();
        $ride->status_updated_by = auth()->id();
        $ride->status_updated_by_role = 'admin';
        $ride->save();
        \App\Services\DeliveryAssignmentService::assign($ride, $rider, $order->fresh());

        return null;
    }

    /** Status choices for the Edit dialog: kitchen steps only, before pickup. */
    private function editableStatusesFor(?Ride $ride): array
    {
        $labels = [
            'pending' => 'Waiting for restaurant',
            'accepted' => 'Accepted by restaurant',
            'rider_assigned' => 'Rider assigned',
            'preparing' => 'Preparing',
            'ready_for_pickup' => 'Ready for pickup',
        ];
        // With a rider on it, it can't go back to "waiting for restaurant / a rider".
        $keys = $ride?->driver_id
            ? ['rider_assigned', 'preparing', 'ready_for_pickup']
            : ['pending', 'accepted', 'preparing', 'ready_for_pickup'];

        return array_intersect_key($labels, array_flip($keys));
    }

    private function km($lat1, $lng1, $lat2, $lng2): float
    {
        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLng = deg2rad((float) $lng2 - (float) $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

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

            \App\Services\FoodOrderFlowService::delivered($order);

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
        $orderCodes = empty($rideIds)
            ? collect()
            : RestaurantOrder::whereIn('ride_id', $rideIds)->pluck('delivery_code', 'ride_id');
        $openAlertRideIds = empty($rideIds)
            ? []
            : \App\Models\StaleJobAlert::where('status', 'open')
                ->where('subject_type', Ride::class)
                ->whereIn('subject_id', $rideIds)
                ->pluck('subject_id')
                ->all();

        return $rides->map(function (Ride $ride) use ($orderStatuses, $orderCodes, $openAlertRideIds) {
            $ride->is_food = $orderStatuses->has($ride->id);
            $ride->order_status = $orderStatuses->get($ride->id);
            $ride->delivery_code = $orderCodes->get($ride->id);
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
            // Until pickup, same rule as reassign() / isReassignable().
            ? in_array($orderStatus, ['rider_assigned', 'preparing', 'ready_for_pickup'], true)
            : in_array($ride->status, ['accepted', 'en_route', 'arrived'], true));
        // Food order details can be edited until the rider has the food.
        $editable = $isFood && in_array($orderStatus, self::EDITABLE_ORDER_STATUSES, true)
            && !in_array($ride->status, ['completed', 'cancelled', 'started'], true);

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
            'delivery_code' => $ride->delivery_code ?? null,
            'assignable' => $assignable,
            'reassignable' => $reassignable,
            'editable' => $editable,
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
