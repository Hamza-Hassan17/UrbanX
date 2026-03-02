<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use App\Models\RestaurantItem;
use App\Models\RestaurantMenu;
use App\Models\RestaurantOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RestaurantController extends Controller
{
    public function getHomeData(Request $request)
    {
        try {
            $user = $request->user();

            $restaurant = Restaurant::where('user_id', $user->id)->first();

            return response()->json([
                'message' => 'Restaurant Home Data',
                'user' => $user,
                'restaurant' => $restaurant,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Home failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getShopDetails(Request $request)
    {
        try {
            $user = $request->user();

            $restaurant = Restaurant::with('category')->where('user_id', $user->id)->first();

            // Get only id and name of active vehicle types
            $categories = RestaurantCategory::where('is_active', 'active')
                ->select('id', 'name')
                ->get();

            if($restaurant){
                $restaurant->weekly_schedule = json_decode($restaurant->weekly_schedule);
                $restaurant->special_opening_hours = json_decode($restaurant->special_opening_hours);

                $restaurant->logo = url($restaurant->logo);
                $restaurant->cover_image = url($restaurant->cover_image);
            }

            return response()->json([
                'restaurant' => $restaurant,
                'categories' => $categories,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateShopDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_category_id' => 'required|exists:restaurant_categories,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'weekly_schedule' => 'nullable|string',
            'special_opening_hours' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max_size',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max_size',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $restaurant = Restaurant::where('user_id', $user->id)->first();

            if (!$restaurant) {
                $restaurant = new Restaurant();
                $restaurant->user_id = $user->id;
            }

            $restaurant->restaurant_category_id = $request->input('restaurant_category_id');
            $restaurant->name = $request->input('name');
            $restaurant->description = $request->input('description');
            $restaurant->address = $request->input('address');
            $restaurant->latitude = $request->input('latitude');
            $restaurant->longitude = $request->input('longitude');
            $restaurant->weekly_schedule = json_encode($request->input('weekly_schedule'));
            $restaurant->special_opening_hours = $request->input('special_opening_hours') ? json_encode($request->input('special_opening_hours')) : null;

            // Handle logo upload
            if ($request->hasFile('logo')) {
                if ($restaurant->logo) {
                    Storage::disk('public')->delete($restaurant->logo);
                }
                $path = $request->file('logo')->store('uploads/restaurant-logos', 'public');
                $restaurant->logo = $path;
            }

            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                if ($restaurant->cover_image) {
                    Storage::disk('public')->delete($restaurant->cover_image);
                }
                $path = $request->file('cover_image')->store('uploads/restaurant-cover-images', 'public');
                $restaurant->cover_image = $path;
            }

            $restaurant->save();
            return response()->json([
                'message' => 'Restaurant details updated successfully',
                'restaurant' => [
                    'restaurant_category_id' => $restaurant->restaurant_category_id,
                    'name' => $restaurant->name,
                    'description' => $restaurant->description,
                    'address' => $restaurant->address,
                    'latitude' => $restaurant->latitude,
                    'longitude' => $restaurant->longitude,
                    'phone' => $restaurant->phone,
                    'weekly_schedule' => json_decode($restaurant->weekly_schedule, true),
                    'special_opening_hours' => json_decode($restaurant->special_opening_hours, true),
                    'logo' => $restaurant->logo ? url($restaurant->logo) : null,
                    'cover_image' => $restaurant->cover_image ? url($restaurant->cover_image) : null,
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Restaurant Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getMenus(Request $request)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $menus = RestaurantMenu::withCount('items')->where('restaurant_id', $restaurant->id)->get();
            return response()->json([
                'message' => 'Restaurant Menus',
                'menus' => $menus
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Menus failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addMenu(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $menu = new RestaurantMenu();
            $menu->restaurant_id = $restaurant->id;
            $menu->name = $request->input('name');
            $menu->description = $request->input('description');
            $menu->save();

            return response()->json([
                'message' => 'Menu added successfully',
                'menu' => $menu
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Add Menu failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateMenu(Request $request, $menu_id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $menu = RestaurantMenu::where('restaurant_id', $restaurant->id)->where('id', $menu_id)->first();

            if (!$menu) {
                return response()->json([
                    'message' => 'Menu not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $menu->name = $request->input('name');
            $menu->description = $request->input('description');
            $menu->save();

            return response()->json([
                'message' => 'Menu updated successfully',
                'menu' => $menu
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Menu failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getMenuItems(Request $request)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $restaurantItems = RestaurantItem::where('restaurant_id', $restaurant->id)->get();
            $restaurantItems = $restaurantItems->map(function ($item) {
                $item->image = url('storage/'.$item->image);
                return $item;
            });
            return response()->json([
                'message' => 'Restaurant Menu Items',
                'restaurant' => $restaurant,
                'items' => $restaurantItems
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Menu Items failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addMenuItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_menu_id' => 'required|exists:restaurant_menus,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_percentage' => 'nullable|integer|min:0|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max_size',
            'is_available' => 'required|in:0,1',
            'is_featured' => 'required|in:0,1',
            'preparation_time' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $restaurantItem = new RestaurantItem();
            $restaurantItem->restaurant_id = $restaurant->id;
            $restaurantItem->restaurant_menu_id = $request->input('restaurant_menu_id');
            $restaurantItem->name = $request->input('name');
            $restaurantItem->description = $request->input('description');
            $restaurantItem->price = $request->input('price');
            $restaurantItem->discount_percentage = $request->input('discount_percentage', 0);
            if ($request->input('discount_percentage', 0) > 0) {
                $discountAmount = ($request->input('price') * $request->input('discount_percentage', 0)) / 100;
                $restaurantItem->discount_price = round($request->input('price') - $discountAmount, 2);
            } else {
                $restaurantItem->discount_price = $request->input('price');
            }
            $restaurantItem->is_available = $request->input('is_available');
            $restaurantItem->is_featured = $request->input('is_featured');
            $restaurantItem->preparation_time = $request->input('preparation_time');

            // Handle image upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('uploads/restaurant-items', 'public');
                $restaurantItem->image = $path;
            }

            $restaurantItem->save();

            $restaurantItem->image = url('storage/'.$restaurantItem->image);
            return response()->json([
                'message' => 'Menu item added successfully',
                'item' => $restaurantItem
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Add Menu Item failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateMenuItem(Request $request, $item_id)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_menu_id' => 'required|exists:restaurant_menus,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_percentage' => 'nullable|integer|min:0|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max_size',
            'is_available' => 'required|in:0,1',
            'is_featured' => 'required|in:0,1',
            'preparation_time' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $restaurantItem = RestaurantItem::where('restaurant_id', $restaurant->id)->where('id', $item_id)->first();

            if (!$restaurantItem) {
                return response()->json([
                    'message' => 'Menu item not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $restaurantItem->restaurant_menu_id = $request->input('restaurant_menu_id');
            $restaurantItem->name = $request->input('name');
            $restaurantItem->description = $request->input('description');
            $restaurantItem->price = $request->input('price');
            $restaurantItem->discount_percentage = $request->input('discount_percentage', 0);
            if ($request->input('discount_percentage', 0) > 0) {
                $discountAmount = ($request->input('price') * $request->input('discount_percentage', 0)) / 100;
                $restaurantItem->discount_price = round($request->input('price') - $discountAmount, 2);
            } else {
                $restaurantItem->discount_price = $request->input('price');
            }
            $restaurantItem->is_available = $request->input('is_available');
            $restaurantItem->is_featured = $request->input('is_featured');
            $restaurantItem->preparation_time = $request->input('preparation_time');

            // Handle image upload
            if ($request->hasFile('image')) {
                if ($restaurantItem->image) {
                    Storage::disk('public')->delete($restaurantItem->image);
                }
                $path = $request->file('image')->store('uploads/restaurant-items', 'public');
                $restaurantItem->image = $path;
            }

            $restaurantItem->save();
            $restaurantItem->image = url('storage/'.$restaurantItem->image);
            return response()->json([
                'message' => 'Menu item updated successfully',
                'item' => $restaurantItem
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Menu Item failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getOrders(Request $request)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $orders = RestaurantOrder::with('customer', 'items', 'voucherCode')->where('restaurant_id', $restaurant->id)->get();
            return response()->json([
                'message' => 'Restaurant Orders',
                'orders' => $orders
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Orders failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getOrderDetails(Request $request, $order_id)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $order = RestaurantOrder::with('customer', 'items', 'voucherCode')->where('restaurant_id', $restaurant->id)->where('id', $order_id)->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'message' => 'Restaurant Order Details',
                'order' => $order
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Order Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function toggleStatus(Request $request)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }
            $message = $restaurant->is_open == 'open' ? 'Restaurant Status Changed to Closed Successfully' : 'Restaurant Status Changed to Open Successfully';
            if ($restaurant->is_open == 'open') {
                $restaurant->is_open = 'closed';
                $restaurant->save();
            } else {
                $restaurant->is_open = 'open';
                $restaurant->save();
            }
            return response()->json([
                'message' => $message,
                'restaurant' => $restaurant,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Toggle Status failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
