<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function getHomeData(Request $request)
    {
        try {
            $user = $request->user();

            $popularRestaurantCategories = RestaurantCategory::where('is_active', 'active')->where('is_popular', '1')->limit(5)->get();
            $popularRestaurantCategories = $popularRestaurantCategories->map(function ($item) {
                $item->image = url('storage/'.$item->image);
                return $item;
            });

           $popularRestaurants = Restaurant::withCount([
                'orders as total_orders' => function ($query) {
                    $query->whereIn('status', ['delivered', 'completed']);
                }
            ])
            ->where('is_active', 'active')
            ->orderByDesc('total_orders')
            ->limit(5)
            ->get();

            $popularRestaurants = $popularRestaurants->map(function ($item) {
                $item->logo = url('storage/'.$item->logo);
                $item->cover_image = url('storage/'.$item->cover_image);
                return $item;
            });

            return response()->json([
                'message' => 'Restaurant Customer Home Data',
                'user' => $user,
                'popular_categories' => $popularRestaurantCategories,
                'pupular_restaurants' => $popularRestaurants,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Customer Home failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
