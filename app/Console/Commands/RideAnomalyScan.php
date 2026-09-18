<?php

namespace App\Console\Commands;

use App\Models\Ride;
use App\Services\RideAnomalyDetector;
use Illuminate\Console\Command;

class RideAnomalyScan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ride-anomaly-scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flag rides whose driver has stopped sending GPS pings for 4+ minutes';

    /**
     * Runs every minute (see Kernel::schedule). Wrong-direction detection
     * happens inline on ping arrival (RideAnomalyDetector::recordPing); this
     * covers the case pings can't cover on their own -- a driver who stops
     * sending them at all.
     */
    public function handle(RideAnomalyDetector $detector)
    {
        $activeStatuses = ['accepted', 'en_route', 'arrived', 'started'];

        $activeRides = Ride::whereIn('status', $activeStatuses)
            ->whereNotNull('driver_id')
            ->get();

        foreach ($activeRides as $ride) {
            $detector->checkStaleGps($ride);
        }

        $this->info("Scanned {$activeRides->count()} active rides for stale GPS.");
    }
}
