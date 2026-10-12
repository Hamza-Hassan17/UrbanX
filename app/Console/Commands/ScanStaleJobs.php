<?php

namespace App\Console\Commands;

use App\Models\Ride;
use App\Models\RestaurantOrder;
use App\Services\DispatchSettingsService;
use App\Services\StaleJobAlertService;
use Illuminate\Console\Command;

/**
 * Batch 2 Part 3 -- stale job alerts, scanned every minute alongside
 * app:sweep-expired-offers and app:ride-anomaly-scan. Every raise() call
 * is a no-op if that job already has an open alert of the same type (see
 * StaleJobAlertService), so running this every minute never creates
 * duplicates.
 */
class ScanStaleJobs extends Command
{
    protected $signature = 'app:scan-stale-jobs';

    protected $description = 'Raise alerts for unassigned jobs, slow restaurants, un-picked-up orders, failed parcels, and cash discrepancies';

    public function handle()
    {
        $raised = 0;

        $raised += $this->scanUnassignedRides();
        $raised += $this->scanRestaurantsNotAccepting();
        $raised += $this->scanReadyForPickupNotPickedUp();
        $raised += $this->scanFailedParcels();
        $raised += $this->scanCashDiscrepancies();

        $this->info("Scan complete -- {$raised} new alert(s) raised.");
    }

    private function scanUnassignedRides(): int
    {
        $thresholdMinutes = DispatchSettingsService::staleUnassignedMinutes();
        $maxAttempts = DispatchSettingsService::maxReofferAttempts();
        $cutoff = now()->subMinutes($thresholdMinutes);

        $rides = Ride::where('status', 'requested')
            ->where(function ($q) use ($cutoff, $maxAttempts) {
                $q->where('requested_at', '<=', $cutoff)
                    ->orWhere('reoffer_attempts', '>=', $maxAttempts);
            })
            ->get();

        $count = 0;
        foreach ($rides as $ride) {
            $workspace = $ride->ride_type === 'delivery' ? 'delivery' : 'rides';
            $alert = StaleJobAlertService::raise(
                'unassigned_job',
                Ride::class,
                $ride->id,
                $workspace,
                "Job #{$ride->id} has been unassigned for over {$thresholdMinutes} minute(s) (or exhausted re-offer attempts)."
            );
            if ($alert) {
                $count++;
            }
        }

        return $count;
    }

    private function scanRestaurantsNotAccepting(): int
    {
        $thresholdMinutes = DispatchSettingsService::staleRestaurantAcceptMinutes();
        $cutoff = now()->subMinutes($thresholdMinutes);

        $orders = RestaurantOrder::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $alert = StaleJobAlertService::raise(
                'restaurant_not_accepting',
                RestaurantOrder::class,
                $order->id,
                'delivery',
                "Order #{$order->order_number} has been pending restaurant acceptance for over {$thresholdMinutes} minute(s)."
            );
            if ($alert) {
                $count++;
            }
        }

        return $count;
    }

    private function scanReadyForPickupNotPickedUp(): int
    {
        $thresholdMinutes = DispatchSettingsService::staleReadyForPickupMinutes();
        $cutoff = now()->subMinutes($thresholdMinutes);

        $orders = RestaurantOrder::where('status', 'ready_for_pickup')
            ->where('updated_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $alert = StaleJobAlertService::raise(
                'ready_for_pickup_not_picked_up',
                RestaurantOrder::class,
                $order->id,
                'delivery',
                "Order #{$order->order_number} has been ready for pickup for over {$thresholdMinutes} minute(s) with no rider pickup."
            );
            if ($alert) {
                $count++;
            }
        }

        return $count;
    }

    private function scanFailedParcels(): int
    {
        $rides = Ride::where('status', 'delivery_failed')->get();

        $count = 0;
        foreach ($rides as $ride) {
            $alert = StaleJobAlertService::raise(
                'parcel_delivery_failed',
                Ride::class,
                $ride->id,
                'delivery',
                "Parcel #{$ride->id} is flagged receiver-unreachable and needs admin resolution."
            );
            if ($alert) {
                $count++;
            }
        }

        return $count;
    }

    private function scanCashDiscrepancies(): int
    {
        $count = 0;

        foreach (RestaurantOrder::where('cash_discrepancy_flagged', true)->get() as $order) {
            $alert = StaleJobAlertService::raise(
                'cash_discrepancy',
                RestaurantOrder::class,
                $order->id,
                'delivery',
                "Order #{$order->order_number} has a cash amount discrepancy flagged for review."
            );
            if ($alert) {
                $count++;
            }
        }

        foreach (Ride::where('cash_discrepancy_flagged', true)->get() as $ride) {
            $workspace = $ride->ride_type === 'delivery' ? 'delivery' : 'rides';
            $alert = StaleJobAlertService::raise(
                'cash_discrepancy',
                Ride::class,
                $ride->id,
                $workspace,
                "Ride/parcel #{$ride->id} has a cash amount discrepancy flagged for review."
            );
            if ($alert) {
                $count++;
            }
        }

        return $count;
    }
}
