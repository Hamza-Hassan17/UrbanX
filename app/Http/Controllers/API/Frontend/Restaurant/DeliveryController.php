<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\DriverVehicle;
use App\Models\Ride;
use App\Models\RideDriverLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class DeliveryController extends Controller
{
    public function getHomeData(Request $request)
    {
        try {
            $user = $request->user();

            $today = now()->toDateString();
            $startOfWeek = now()->startOfWeek(); // Monday
            $endOfWeek = now()->endOfWeek();

            // Today Deliveries
            $today_deliveries = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count();

            // Today Earnings
            $today_earning = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->sum('total_fare');

            // This Week Deliveries
            $this_week_deliveries = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->count();

            // This Week Earnings
            $this_week_earning = Ride::where('driver_id', $user->id)
                ->where('ride_type', 'delivery')
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
                ->sum('total_fare');

            return response()->json([
                'message' => 'Delivery Rider Home Stats',

                'stats' => [
                    'today_earning' => (float) $today_earning,
                    'today_deliveries' => $today_deliveries,
                    'this_week_earning' => (float) $this_week_earning,
                    'this_week_deliveries' => $this_week_deliveries,
                ]

            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('API Restaurant Delivery Home failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getLatestRides(Request $request)
    {
        try {
            $driver = $request->user();

            // -------------------------
            // Time windows
            // -------------------------
            $tenMinutesAgo   = now()->subMinutes(10);   // ride life
            $logWindowStart  = now()->subHours(10);     // logs window

            // -------------------------
            // Driver vehicle type
            // -------------------------
            $driverVehicleType = DriverVehicle::join('vehicle_types', 'driver_vehicles.vehicle_type_id', '=', 'vehicle_types.id')
                ->where('driver_vehicles.driver_id', $driver->id)
                ->where('vehicle_types.is_delivery', '1')
                ->value('driver_vehicles.vehicle_type_id');

            Log::info('Driver Vehicle Type', ['driver_id' => $driver->id, 'vehicle_type_id' => $driverVehicleType]);

            if (!$driverVehicleType) {
                Log::warning("Driver does not have a valid delivery vehicle type", ['driver_id' => $driver->id]);
                return response()->json(['rides' => []], 200);
            }

            // -------------------------
            // Rides already sent / accepted (last 10 hours)
            // -------------------------
            $busyRideIds = DB::table('ride_driver_logs')
                ->whereIn('action', ['sent', 'accepted'])
                ->where('created_at', '>=', $logWindowStart)
                ->pluck('ride_id');

            Log::info('Busy Ride IDs', ['driver_id' => $driver->id, 'busy_ride_ids' => $busyRideIds]);

            // -------------------------
            // Rides rejected by this driver (last 10 hours)
            // -------------------------
            $rejectedByMe = DB::table('ride_driver_logs')
                ->where('driver_id', $driver->id)
                ->where('action', 'rejected')
                ->where('created_at', '>=', $logWindowStart)
                ->pluck('ride_id');

            Log::info('Rides Rejected by Driver', ['driver_id' => $driver->id, 'rejected_ride_ids' => $rejectedByMe]);

            // -------------------------
            // Fetch nearest single ride
            // -------------------------
            $rides = Ride::selectRaw("
                    rides.*,
                    (6371 * acos(
                        cos(radians(?)) *
                        cos(radians(pickup_latitude)) *
                        cos(radians(pickup_longitude) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(pickup_latitude))
                    )) AS distance
                ", [
                    $driver->lat,
                    $driver->lang,
                    $driver->lat
                ])
                ->where('ride_type', 'delivery')
                ->where('status', 'requested')
                ->where('requested_at', '>=', $tenMinutesAgo)
                ->where('vehicle_type_id', $driverVehicleType)
                ->whereNotIn('id', $busyRideIds)
                ->whereNotIn('id', $rejectedByMe)
                ->orderBy('distance')
                ->limit(1)
                ->get();

            // -------------------------
            // Log ride as "sent"
            // -------------------------
            if ($rides->count()) {
                Log::info('Ride Sent to Driver', ['driver_id' => $driver->id, 'ride_id' => $rides[0]->id]);
                RideDriverLog::create([
                    'ride_id'   => $rides[0]->id,
                    'driver_id' => $driver->id,
                    'action'    => 'sent',
                    'note'      => 'Ride sent to nearest driver'
                ]);
            }

            // -------------------------
            // Boost logic
            // -------------------------
            $now = now()->format('H:i');
            $busyHour = DB::table('boost_hours')
                ->where('start', '<=', $now)
                ->where('end', '>=', $now)
                ->first();

            Log::info('Boost Hour Check', ['current_time' => $now, 'busy_hour' => $busyHour]);

            $multiplier = $busyHour ? (float)$busyHour->multiplier : 1.0;

            $rides->transform(function ($ride) use ($multiplier) {
                $ride->boost_multiplier = $multiplier;
                $ride->is_boost = $multiplier > 1;
                $ride->final_fare = isset($ride->total_fare)
                    ? $ride->total_fare * $multiplier
                    : null;
                return $ride;
            });

            Log::info('Rides after Boost Calculation', ['driver_id' => $driver->id, 'rides' => $rides]);

            return response()->json([
                'rides' => $rides
            ], 200);

        } catch (\Throwable $e) {
            Log::error('getLatestRides failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong'
            ], 500);
        }
    }
}
