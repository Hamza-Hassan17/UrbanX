<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\HasQueuePresets;
use App\Models\Ride;
use App\Models\RestaurantOrder;
use App\Services\GeocodingService;
use App\Services\RideQueueFilterService;
use Illuminate\Http\Request;

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
