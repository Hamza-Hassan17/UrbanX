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
     * Manual Delivery Assignment -- unclaimed food/parcel jobs plus the
     * delivery riders who can take them. Assigning posts to the shared
     * dashboard.rides.update action, which already carries the full
     * delivery side-effect set (offer, order status, broadcasts, pushes).
     */
    public function assignment()
    {
        $this->authorize('view delivery');
        $this->authorize('assign ride');

        try {
            $rides = Ride::where('ride_type', 'delivery')
                ->where('status', 'requested')
                ->whereNull('driver_id')
                ->with('passenger:id,name,phone')
                ->latest('requested_at')
                ->get();

            // Food jobs are only claimable once the restaurant has accepted.
            $orders = RestaurantOrder::whereIn('ride_id', $rides->pluck('id'))
                ->with('restaurant:id,name')
                ->get()
                ->keyBy('ride_id');

            $rides = $rides->filter(function ($ride) use ($orders) {
                $order = $orders->get($ride->id);
                return !$order || $order->status === 'accepted';
            })->values();

            $geocoder = app(GeocodingService::class);
            $rows = $rides->map(function ($ride) use ($orders, $geocoder) {
                $order = $orders->get($ride->id);
                return [
                    'id' => $ride->id,
                    'type_label' => $order ? 'Food' : 'Parcel',
                    'restaurant' => $order->restaurant->name ?? null,
                    'time' => optional($ride->scheduled_pickup_at ?? $ride->requested_at)->setTimezone('Asia/Karachi')->format('d M H:i'),
                    'pickup' => $geocoder->reverseGeocode($ride->pickup_latitude, $ride->pickup_longitude)
                        ?? $ride->pickup_latitude . ', ' . $ride->pickup_longitude,
                    'dropoff' => $geocoder->reverseGeocode($ride->dropoff_latitude, $ride->dropoff_longitude)
                        ?? $ride->dropoff_latitude . ', ' . $ride->dropoff_longitude,
                    'customer' => $ride->passenger->name ?? ($ride->sender_name ?? null),
                    'phone' => $ride->passenger->phone ?? ($ride->sender_phone ?? null),
                    'fare' => (float) $ride->total_fare,
                ];
            });

            $riders = \App\Models\User::role('driver')
                ->where('driver_status', 'available')
                ->whereHas('driverVehicle.vehicleType', fn ($q) => $q->where('is_delivery', '1'))
                ->with('profile:id,user_id,city')
                ->orderBy('name')
                ->get(['id', 'name', 'phone']);

            return view('dashboard.delivery.assignment', compact('rows', 'riders'));
        } catch (\Throwable $th) {
            Log::error('Delivery Assignment Page Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
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
        $foodRideIds = $this->foodRideIds($rides->pluck('id')->all());

        return $rides->map(function (Ride $ride) use ($foodRideIds) {
            $ride->is_food = in_array($ride->id, $foodRideIds, true);
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

        return [
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
