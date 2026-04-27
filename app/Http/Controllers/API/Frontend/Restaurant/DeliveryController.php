<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\Request;
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
}
