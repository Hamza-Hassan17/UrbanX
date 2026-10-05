<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\User;

/**
 * Ride-level earnings report for admin: every completed ride in a date range
 * for all drivers or a chosen subset, with pickup/drop-off addresses and the
 * commission/SST breakdown per ride, grouped by driver with subtotals.
 */
class FinanceReportService
{
    public function __construct(private GeocodingService $geocoder)
    {
    }

    public function build(string $startDate, string $endDate, array $driverIds = []): array
    {
        $driversQuery = User::role('driver')->orderBy('name');
        if (!empty($driverIds)) {
            $driversQuery->whereIn('id', $driverIds);
        }

        $groups = $driversQuery->get(['id', 'name', 'phone', 'is_active'])->map(function (User $driver) use ($startDate, $endDate) {
            $rides = Ride::where('driver_id', $driver->id)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('completed_at')
                ->get();

            $rideRows = $rides->map(fn (Ride $ride) => $this->rideRow($ride))->values();

            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'phone' => $driver->phone,
                'is_active' => $driver->is_active,
                'total_rides' => $rideRows->count(),
                'gross_fare' => round($rideRows->sum('gross_fare'), 2),
                'commission' => round($rideRows->sum('commission'), 2),
                'sst' => round($rideRows->sum('sst_on_commission') + $rideRows->sum('sst_on_ride_fare'), 2),
                'net_income' => round($rideRows->sum('driver_income'), 2),
                'rides' => $rideRows,
            ];
        })->filter(fn (array $group) => $group['total_rides'] > 0)->values();

        if (empty($driverIds)) {
            $unassignedRides = Ride::whereNull('driver_id')
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('completed_at')
                ->get()
                ->map(fn (Ride $ride) => $this->rideRow($ride))
                ->values();

            if ($unassignedRides->isNotEmpty()) {
                $groups->push([
                    'driver_id' => null,
                    'driver_name' => 'Driver unknown',
                    'phone' => null,
                    'is_active' => null,
                    'total_rides' => $unassignedRides->count(),
                    'gross_fare' => round($unassignedRides->sum('gross_fare'), 2),
                    'commission' => round($unassignedRides->sum('commission'), 2),
                    'sst' => round($unassignedRides->sum('sst_on_commission') + $unassignedRides->sum('sst_on_ride_fare'), 2),
                    'net_income' => round($unassignedRides->sum('driver_income'), 2),
                    'rides' => $unassignedRides,
                ]);
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'groups' => $groups,
            'totals' => [
                'total_rides' => $groups->sum('total_rides'),
                'gross_fare' => round($groups->sum('gross_fare'), 2),
                'commission' => round($groups->sum('commission'), 2),
                'sst' => round($groups->sum('sst'), 2),
                'net_income' => round($groups->sum('net_income'), 2),
            ],
            'commission_percent' => FareBreakdownService::commissionPercent(),
            'sst_percent' => FareBreakdownService::sstPercent(),
        ];
    }

    private function rideRow(Ride $ride): array
    {
        $breakdown = FareBreakdownService::calculate((float) $ride->total_fare);

        return [
            'ride_id' => $ride->id,
            'ride_type' => ucfirst($ride->ride_type),
            'completed_at' => optional($ride->completed_at)->format('d M Y h:i A'),
            'pickup' => $this->geocoder->reverseGeocode($ride->pickup_latitude, $ride->pickup_longitude)
                ?? $ride->pickup_latitude . ', ' . $ride->pickup_longitude,
            'dropoff' => $this->geocoder->reverseGeocode($ride->dropoff_latitude, $ride->dropoff_longitude)
                ?? $ride->dropoff_latitude . ', ' . $ride->dropoff_longitude,
            'distance_km' => $ride->distance_km,
            'duration_minutes' => $ride->duration_minutes,
            'gross_fare' => $breakdown['gross_fare'],
            'commission' => $breakdown['commission'],
            'sst_on_commission' => $breakdown['sst_on_commission'],
            'sst_on_ride_fare' => $breakdown['sst_on_ride_fare'],
            'driver_income' => $breakdown['driver_income'],
        ];
    }
}
