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
 * Eligibility does NOT check an "online" flag -- there is no online/offline
 * column yet (that's a later, unbuilt feature) -- but DOES check driver
 * verification status, behind DispatchSettingsService::
 * driverVerificationEnabled(), the same single switch every other
 * verification gate in the app uses (currently false/disabled since
 * 2026-09-19, so this check is a no-op today; flipping that one switch
 * re-enables it here too, with nothing in this file to remember to change).
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
        $alreadyOfferedDriverIds = RideOffer::where('ride_id', $ride->id)->current()->pluck('driver_id');

        $isDelivery = $ride->ride_type === 'delivery';

        // Batch 3 Part 4 -- offline drivers get no offers (NULL = never used
        // the toggle, treated as online like before).
        $query = User::role('driver')
            ->acceptingJobs()
            ->whereNotNull('lat')
            ->whereNotNull('lang')
            ->whereNotIn('id', $alreadyOfferedDriverIds)
            ->whereHas('driverVehicle', function ($q) use ($ride) {
                $q->where('vehicle_type_id', $ride->vehicle_type_id);
            });

        $drivers = $query->get(['id', 'lat', 'lang']);

        $nearest = null;
        $nearestDistance = null;

        $verificationEnabled = DispatchSettingsService::driverVerificationEnabled();

        foreach ($drivers as $driver) {
            if ($verificationEnabled && $driver->driverVerification?->status !== 'approved') {
                continue;
            }

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

        // Batch 3 Part 6 -- only when "No-driver handling" is On (off by
        // default; appliesTo() is false then, so this is skipped entirely).
        $noDriverHandling = NoDriverFoundService::appliesTo($ride);

        if ($noDriverHandling && NoDriverFoundService::attemptsExhausted($ride)) {
            NoDriverFoundService::markNoDriverFound($ride);
            return false;
        }

        $driver = self::findNextEligibleDriver($ride);

        if (!$driver) {
            Log::info('No eligible driver left to offer ride to', ['ride_id' => $ride->id]);
            if ($noDriverHandling) {
                NoDriverFoundService::markNoDriverFound($ride);
            }
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
