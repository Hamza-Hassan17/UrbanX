<?php

namespace App\Console\Commands;

use App\Models\Ride;
use Illuminate\Console\Command;

class BackfillRideDrivers extends Command
{
    protected $signature = 'rides:backfill-drivers {--apply : Write changes instead of only listing them}';

    protected $description = 'Set driver_id on completed rides that have none, using the driver who last updated their status';

    public function handle(): int
    {
        $rides = Ride::where('status', 'completed')
            ->whereNull('driver_id')
            ->where('status_updated_by_role', 'driver')
            ->whereNotNull('status_updated_by')
            ->get(['id', 'status_updated_by', 'completed_at']);

        $this->info("Rides to backfill: {$rides->count()}");

        foreach ($rides as $ride) {
            $this->line("Ride #{$ride->id} -> driver #{$ride->status_updated_by} (completed {$ride->completed_at})");
        }

        if (!$this->option('apply')) {
            $this->comment('Dry run only. Re-run with --apply to write these changes.');
            return self::SUCCESS;
        }

        foreach ($rides as $ride) {
            Ride::where('id', $ride->id)->whereNull('driver_id')->update(['driver_id' => $ride->status_updated_by]);
        }

        $this->info('Backfill applied.');
        return self::SUCCESS;
    }
}
