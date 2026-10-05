<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Ride-level earnings and tax report for admin: every completed ride in a
 * date range for all drivers or a chosen subset, with pickup/drop-off
 * addresses and the commission/SST breakdown per ride, grouped by driver
 * with subtotals. A report type can narrow it to a single tax or commission.
 */
class FinanceReportService
{
    public const TYPES = [
        'all' => ['label' => 'Driver Earnings (all)', 'key' => null],
        'service_commission' => ['label' => 'Service Commission', 'key' => 'commission'],
        'sst_service_commission' => ['label' => 'SST on Service Commission', 'key' => 'sst_on_commission'],
        'sst_ride_fare' => ['label' => 'SST on Ride Fare', 'key' => 'sst_on_ride_fare'],
    ];

    public function __construct(private GeocodingService $geocoder)
    {
    }

    public function build(string $startDate, string $endDate, array $driverIds = [], string $type = 'all'): array
    {
        $type = array_key_exists($type, self::TYPES) ? $type : 'all';
        $key = self::TYPES[$type]['key'];

        $driversQuery = User::role('driver')->orderBy('name');
        if (!empty($driverIds)) {
            $driversQuery->whereIn('id', $driverIds);
        }

        $groups = $driversQuery->get(['id', 'name', 'phone', 'is_active'])->map(function (User $driver) use ($startDate, $endDate, $key) {
            $rides = Ride::where('driver_id', $driver->id)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('completed_at')
                ->get();

            return $this->group($driver->id, $driver->name, $driver->phone, $driver->is_active, $rides, $key);
        })->filter(fn (array $group) => $group['total_rides'] > 0)->values();

        if (empty($driverIds)) {
            $unassigned = Ride::whereNull('driver_id')
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('completed_at')
                ->get();

            if ($unassigned->isNotEmpty()) {
                $groups->push($this->group(null, 'Driver unknown', null, null, $unassigned, $key));
            }
        }

        return [
            'type' => $type,
            'type_label' => self::TYPES[$type]['label'],
            'selected_key' => $key,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'groups' => $groups,
            'totals' => [
                'total_rides' => $groups->sum('total_rides'),
                'gross_fare' => round($groups->sum('gross_fare'), 2),
                'commission' => round($groups->sum('commission'), 2),
                'sst' => round($groups->sum('sst'), 2),
                'net_income' => round($groups->sum('net_income'), 2),
                'selected_total' => round($groups->sum('selected_total'), 2),
            ],
            'commission_percent' => FareBreakdownService::commissionPercent(),
            'sst_percent' => FareBreakdownService::sstPercent(),
        ];
    }

    private function group(?int $driverId, string $driverName, ?string $phone, ?string $isActive, Collection $rides, ?string $key): array
    {
        $rideRows = $rides->map(fn (Ride $ride) => $this->rideRow($ride, $key))->values();

        return [
            'driver_id' => $driverId,
            'driver_name' => $driverName,
            'phone' => $phone,
            'is_active' => $isActive,
            'total_rides' => $rideRows->count(),
            'gross_fare' => round($rideRows->sum('gross_fare'), 2),
            'commission' => round($rideRows->sum('commission'), 2),
            'sst' => round($rideRows->sum('sst_on_commission') + $rideRows->sum('sst_on_ride_fare'), 2),
            'net_income' => round($rideRows->sum('driver_income'), 2),
            'selected_total' => round($rideRows->sum('selected_amount'), 2),
            'rides' => $rideRows,
        ];
    }

    private function rideRow(Ride $ride, ?string $key): array
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
            'selected_amount' => $key ? $breakdown[$this->breakdownKey($key)] : null,
        ];
    }

    private function breakdownKey(string $key): string
    {
        return match ($key) {
            'commission' => 'commission',
            'sst_on_commission' => 'sst_on_commission',
            'sst_on_ride_fare' => 'sst_on_ride_fare',
        };
    }
}
