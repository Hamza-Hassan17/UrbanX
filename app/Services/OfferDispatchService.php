<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\RideDriverLog;
use App\Models\RideOffer;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Batch 2 Part 2 -- offer timeout & re-offer. Formalizes the "sent to
 * nearest driver" step (previously tracked only via a ride_driver_logs
 * 'sent' row with no expiry, so a driver who never responded could
 * monopolize a job for up to 10 hours) into a real RideOffer row with
 * expires_at, which ride_offers already had a column for but nothing used.
 *
 * Covers taxi rides, parcels, and food-delivery rides alike -- they're all
 * `rides` rows, offered/accepted/rejected through the same Driver\
 * RideController endpoints. Admin manual assignment (Dashboard\
 * RideController's "Assign Trip") is a deliberate human choice, not an
 * automatic broadcast, and is left untouched by this service.
 *
 * Eligibility deliberately does NOT check driver verification status or an
 * "online" flag: verification is already disabled app-wide (see the TEMP
 * comments in Driver\RideController, dated 2026-09-19) and there is no
 * online/offline column yet (that's a later, unbuilt feature) -- enforcing
 * either here would be inconsistent with every other part of the app.
 */
class OfferDispatchService
{
    /**
     * Finds the nearest eligible driver for $ride who hasn't already been
     * offered this specific job (any status -- pending, rejected, expired,
     * or accepted-by-someone-else all count as "already offered").
     */
    public static function findNextEligibleDriver(Ride $ride): ?User
    {
        $alreadyOfferedDriverIds = RideOffer::where('ride_id', $ride->id)->pluck('driver_id');

        $isDelivery = $ride->ride_type === 'delivery';

        $query = User::role('driver')
            ->whereNotNull('lat')
            ->whereNotNull('lang')
            ->whereNotIn('id', $alreadyOfferedDriverIds)
            ->whereHas('driverVehicle', function ($q) use ($ride) {
                $q->where('vehicle_type_id', $ride->vehicle_type_id);
            });

        $drivers = $query->get(['id', 'lat', 'lang']);

        $nearest = null;
        $nearestDistance = null;

        foreach ($drivers as $driver) {
            // Batch 1 Part 8 -- delivery riders over their cash-in-hand
            // limit get no new offers. Taxi drivers get the equivalent
            // outstanding-balance check added in Part 5.
            if ($isDelivery && RiderCashService::isOverLimit($driver->id)) {
                continue;
            }
            if (!$isDelivery && class_exists(TaxiBalanceService::class) && TaxiBalanceService::isOverLimit($driver->id)) {
                continue;
            }

            $distance = self::haversineKm(
                (float) $ride->pickup_latitude,
                (float) $ride->pickup_longitude,
                (float) $driver->lat,
                (float) $driver->lang
            );

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearest = $driver;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }

    /**
     * Creates a pending offer for $driver on $ride and increments the
     * ride's reoffer_attempts counter (0 on the very first offer this
     * tracks how many times dispatch has had to move on from someone).
     */
    public static function createOffer(Ride $ride, User $driver): RideOffer
    {
        $offer = RideOffer::create([
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'proposed_price' => $ride->total_fare,
            'eta_minutes' => 0,
            'note' => 'Auto-dispatched',
            'status' => 'pending',
            'offered_at' => now(),
            'expires_at' => now()->addSeconds(DispatchSettingsService::offerAcceptanceTimeoutSeconds()),
        ]);

        $ride->increment('reoffer_attempts');

        RideDriverLog::create([
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'action' => 'sent',
            'note' => 'Offered via dispatch (offer #' . $offer->id . ')',
        ]);

        Log::info('Ride offer created', ['ride_id' => $ride->id, 'driver_id' => $driver->id, 'offer_id' => $offer->id]);

        try {
            app('notificationService')->notifyUsers(
                [$driver],
                'New Job Available',
                'A new ' . ($ride->ride_type === 'delivery' ? 'delivery' : 'ride') . ' request is available.',
                'rides',
                $ride->id,
                'ride_details'
            );
        } catch (\Throwable $e) {
            Log::error('Offer notification failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        try {
            broadcast(new \App\Events\RideOfferCreated($offer));
        } catch (\Throwable $e) {
            Log::error('RideOfferCreated broadcast failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        return $offer;
    }

    /**
     * Finds the next eligible driver and offers them the job, if any exist.
     * Returns false (job stays unassigned, picked up by Part 3's stale
     * alert once past threshold) when nobody eligible is left.
     */
    public static function offerToNextDriver(Ride $ride): bool
    {
        if ($ride->status !== 'requested') {
            return false;
        }

        $driver = self::findNextEligibleDriver($ride);

        if (!$driver) {
            Log::info('No eligible driver left to offer ride to', ['ride_id' => $ride->id]);
            return false;
        }

        self::createOffer($ride, $driver);
        return true;
    }

    public static function expireOffer(RideOffer $offer): void
    {
        $offer->status = 'expired';
        $offer->save();

        Log::info('Ride offer expired', ['offer_id' => $offer->id, 'ride_id' => $offer->ride_id, 'driver_id' => $offer->driver_id]);

        try {
            broadcast(new \App\Events\RideOfferStatusUpdated($offer));
        } catch (\Throwable $e) {
            Log::error('RideOfferStatusUpdated broadcast failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        $ride = Ride::find($offer->ride_id);
        if ($ride) {
            self::offerToNextDriver($ride);
        }
    }

    public static function rejectOffer(RideOffer $offer): void
    {
        $offer->status = 'rejected';
        $offer->save();

        Log::info('Ride offer rejected', ['offer_id' => $offer->id, 'ride_id' => $offer->ride_id, 'driver_id' => $offer->driver_id]);

        try {
            broadcast(new \App\Events\RideOfferStatusUpdated($offer));
        } catch (\Throwable $e) {
            Log::error('RideOfferStatusUpdated broadcast failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        $ride = Ride::find($offer->ride_id);
        if ($ride) {
            self::offerToNextDriver($ride);
        }
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        return $earthRadiusKm * acos(min(1, max(-1,
            cos($lat1) * cos($lat2) * cos($lng2 - $lng1) + sin($lat1) * sin($lat2)
        )));
    }
}
