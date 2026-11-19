<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complain;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\Transaction;
use App\Models\User;

/**
 * Customer Profile (Phase 4 of the admin workspace split), under
 * Platform -> Customers. One page, cross-service, which is exactly why it
 * lives in Platform rather than Rides or Delivery -- a customer's Rides,
 * Rentals and Food & Parcel history all show here regardless of which
 * workspace tab an admin currently has open.
 */
class CustomerProfileController extends Controller
{
    public function show(string $id)
    {
        $this->authorize('view user');

        $customer = User::findOrFail($id);

        $rides = Ride::where('passenger_id', $id)
            ->where('ride_type', 'ride')
            ->latest('requested_at')
            ->get();

        $rentals = Booking::where('user_id', $id)
            ->latest()
            ->get();

        $deliveryRides = Ride::where('passenger_id', $id)
            ->whereIn('ride_type', ['delivery', 'parcel'])
            ->latest('requested_at')
            ->get();

        $foodRideIds = RestaurantOrder::whereIn('ride_id', $deliveryRides->pluck('id'))->pluck('ride_id');
        $deliveryRides = $deliveryRides->map(function (Ride $ride) use ($foodRideIds) {
            $ride->is_food = $foodRideIds->contains($ride->id);
            return $ride;
        });

        $transactions = Transaction::where('user_id', $id)
            ->latest()
            ->get();

        $complaints = Complain::where('user_id', $id)
            ->latest()
            ->get();

        $insights = \App\Services\CustomerInsightsService::forCustomer((int) $id);

        // Personal offers given to this customer, with how often each was used.
        $offers = \App\Models\PromoCode::where('user_id', $id)->latest()->get()->map(function ($p) use ($id) {
            $p->times_used = Ride::where('promo_code_id', $p->id)->where('passenger_id', $id)->where('status', '!=', 'cancelled')->count();
            return $p;
        });

        return view('dashboard.customers.show', compact(
            'insights',
            'offers',
            'customer',
            'rides',
            'rentals',
            'deliveryRides',
            'transactions',
            'complaints'
        ));
    }
}
