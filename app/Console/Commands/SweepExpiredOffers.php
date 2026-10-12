<?php

namespace App\Console\Commands;

use App\Models\RideOffer;
use App\Services\OfferDispatchService;
use Illuminate\Console\Command;

/**
 * Batch 2 Part 2 -- lazy enforcement (getLatestRides/acceptRide) already
 * expires an offer the moment its own driver interacts with it again, but
 * a driver who never comes back (app closed, phone died) would otherwise
 * leave the job stuck on a dead offer forever. This sweep catches that
 * case for everyone, every minute.
 */
class SweepExpiredOffers extends Command
{
    protected $signature = 'app:sweep-expired-offers';

    protected $description = 'Expire pending ride offers past their timeout and re-offer the job to the next eligible driver';

    public function handle()
    {
        $expired = RideOffer::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $offer) {
            OfferDispatchService::expireOffer($offer);
        }

        $this->info("Swept {$expired->count()} expired offer(s).");
    }
}
