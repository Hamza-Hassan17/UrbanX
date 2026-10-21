<?php

namespace App\Console\Commands;

use App\Services\DispatchSettingsService;
use App\Services\DriverPresenceService;
use Illuminate\Console\Command;

/**
 * Batch 3 Part 4 -- switches drivers/riders who use the online toggle to
 * offline after "Auto-offline after N minutes" (Dispatch settings) without
 * a location update, so offers aren't wasted on dead phones. Drivers who
 * never used the toggle (online_status NULL, older app builds) are never
 * touched.
 */
class AutoOfflineDrivers extends Command
{
    protected $signature = 'app:auto-offline-drivers';

    protected $description = 'Switch online drivers with no recent location update to offline';

    public function handle()
    {
        $count = DriverPresenceService::autoOfflineStale(DispatchSettingsService::autoOfflineMinutes());

        $this->info("Auto-offline complete -- {$count} driver(s) switched offline.");
    }
}
