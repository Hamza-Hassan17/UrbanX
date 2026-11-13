<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use App\Models\RestaurantItem;
use App\Models\RestaurantMenu;
use App\Models\RestaurantOrder;
use App\Models\RestaurantReview;
use App\Models\Ride;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RestaurantController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase->getDatabase();
    }

    public function getHomeData(Request $request)
    {
        try {
            $user = $request->user();

            $restaurant = Restaurant::where('user_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $reports = \App\Services\RestaurantReportService::class;

            // All time: delivered orders and what the restaurant earned from
            // them (food sales minus commission, SST, tax and its own
            // discounts) -- not what customers paid, which also includes the
            // rider's delivery fee and tips.
            $allTime = $reports::totals($restaurant->id, \Illuminate\Support\Carbon::create(2000), now());

            // Last 30 days vs the 30 before, for the trend arrows.
            $last30 = $reports::totals($restaurant->id, now()->subDays(30), now());
            $prev30 = $reports::totals($restaurant->id, now()->subDays(60), now()->subDays(30)->subSecond());

            $pending_orders = RestaurantOrder::where('restaurant_id', $restaurant->id)
                                ->where('status', 'pending')
                                ->count();

            $ratings = $reports::ratings($restaurant->id);

            return response()->json([
                'message' => 'Restaurant Home Data',
                'user' => $user,
                'restaurant' => $restaurant,
                'stats' => [
                    'total_orders' => $allTime['orders'],            // delivered orders
                    'pending_orders' => $pending_orders,
                    'total_revenue' => $allTime['net_payable'],      // restaurant's earnings
                    'total_food_sales' => $allTime['food_sales'],
                    'cancelled_orders' => RestaurantOrder::where('restaurant_id', $restaurant->id)->whereIn('status', ['cancelled', 'rejected'])->count(),
                    // null = nothing to compare with yet (show no arrow)
                    'orders_change_percent' => $reports::change($last30['orders'], $prev30['orders']),
                    'revenue_change_percent' => $reports::change($last30['net_payable'], $prev30['net_payable']),
                    'rating' => $ratings['average'],
                    'reviews_count' => $ratings['count'],
                ]

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
            // Batch 3 Part 3 -- optional; empty/0 clears the minimum.
            'minimum_order_amount' => 'nullable|numeric|min:0|max:1000000',
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

            // Only touched when sent, so older Partner builds that don't know
            // the field never wipe an existing minimum.
            if ($request->exists('minimum_order_amount')) {
                $minimum = $request->input('minimum_order_amount');
                $restaurant->minimum_order_amount = ($minimum === null || $minimum === '' || (float) $minimum <= 0) ? null : $minimum;
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
                    'minimum_order_amount' => $restaurant->minimum_order_amount,
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
            $restaurantItems = RestaurantItem::with('optionGroups.options')->where('restaurant_id', $restaurant->id)->get();
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

            // The restaurant always sees the rider and where they are.
            $orderRider = \App\Services\FoodOrderFlowService::rider($order);
            $order->setAttribute('rider', $orderRider ? [
                'id' => $orderRider->id,
                'name' => $orderRider->name,
                'phone' => $orderRider->phone,
                // For the restaurant's map: where the rider is now.
                'latitude' => $orderRider->lat !== null ? (float) $orderRider->lat : null,
                'longitude' => $orderRider->lang !== null ? (float) $orderRider->lang : null,
                'location_updated_at' => $orderRider->last_location_at,
            ] : null);
            $order->setAttribute('rider_status', match ($order->ride_id ? Ride::whereKey($order->ride_id)->value('status') : null) {
                'accepted' => 'assigned',
                'en_route' => 'on_the_way_to_restaurant',
                'arrived' => 'arrived_at_restaurant',
                'started' => 'delivering',
                'completed' => 'delivered',
                default => $orderRider ? 'assigned' : 'finding_rider',
            });

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

    public function acceptOrder(Request $request, $order_id)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $order = RestaurantOrder::where('id', $order_id)
                ->where('restaurant_id', $restaurant->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($order->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending orders can be accepted'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $order->status = 'accepted';
            $order->save();

            // Riders are offered the job from this moment, so their 10-minute
            // window starts at acceptance, not when the customer ordered.
            Ride::where('id', $order->ride_id)->where('status', 'requested')->update(['requested_at' => now()]);

            \App\Services\FoodOrderFlowService::notifyCustomer($order, 'Order accepted', "{$restaurant->name} accepted your order.");

            // Notify customer-facing listeners. A broadcast failure must never
            // fail an order that already saved successfully.
            try {
                broadcast(new \App\Events\RestaurantOrderUpdated($order));
            } catch (\Throwable $e) {
                Log::error('RestaurantOrderUpdated broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            // Drivers discover new/available rides via getLatestRides() polling
            // MySQL directly (Driver\RideController), not via this broadcast --
            // this is purely a "current state" push for the customer watching
            // their own ride, matching what the old Firebase write did in
            // practice despite its comment.
            $ride = Ride::find($order->ride_id);

            if ($ride) {
                try {
                    broadcast(new \App\Events\RideStatusUpdated($ride));
                } catch (\Throwable $e) {
                    Log::error('RideStatusUpdated broadcast failed', ['ride_id' => $ride->id, 'error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'message' => 'Order accepted successfully',
                'order'   => $order,
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('API Accept Order failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rejectOrder(Request $request, $order_id)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $order = RestaurantOrder::where('id', $order_id)
                ->where('restaurant_id', $restaurant->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($order->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending orders can be rejected'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $order->status = 'rejected';
            $order->save();

            // Cancel the ride so it never surfaces to riders
            if ($order->ride_id) {
                Ride::where('id', $order->ride_id)->update(['status' => 'cancelled']);
            }

            // Notify customer-facing listeners
            try {
                broadcast(new \App\Events\RestaurantOrderUpdated($order));
            } catch (\Throwable $e) {
                Log::error('RestaurantOrderUpdated broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            return response()->json([
                'message' => 'Order rejected successfully',
                'order'   => $order,
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('API Reject Order failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Batch 1 Part 6 -- restaurant can cancel after accepting (unlike
     * rejectOrder(), which only covers pending orders, before acceptance).
     * Releases any assigned rider and notifies them, same as the admin
     * cancel path -- see RestaurantOrderCancellationService.
     */
    public function cancelOrder(Request $request, $order_id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
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

            $order = RestaurantOrder::where('id', $order_id)
                ->where('restaurant_id', $restaurant->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            if (in_array($order->status, ['delivered', 'completed', 'cancelled', 'rejected'], true)) {
                return response()->json([
                    'message' => 'This order is already finalized and cannot be cancelled.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($order->status === 'pending') {
                return response()->json([
                    'message' => 'Use reject for an order that has not been accepted yet.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Review follow-up -- restaurant cancellation window closes at
            // pickup. A rider already has the food in hand past that
            // point, so only an admin can unwind it from there.
            if (in_array($order->status, ['picked_up', 'on_the_way'], true)) {
                return response()->json([
                    'message' => 'This order has already been picked up -- only an admin can cancel it now.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            \App\Services\RestaurantOrderCancellationService::cancel($order, 'restaurant', $request->reason);

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => $order,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Restaurant Cancel Order failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateOrderStatus(Request $request, $order_id)
    {
        $validator = Validator::make($request->all(), [
            // handed_over = the restaurant gave the food to the rider.
            'status' => 'required|in:preparing,ready_for_pickup,handed_over',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
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

            $order = RestaurantOrder::where('id', $order_id)
                ->where('restaurant_id', $restaurant->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!in_array($order->status, \App\Services\FoodOrderFlowService::BEFORE_PICKUP_STATUSES, true)) {
                return response()->json([
                    'message' => 'This order has already been picked up or closed.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($request->status === 'handed_over') {
                if (!\App\Services\FoodOrderFlowService::rider($order)) {
                    return response()->json([
                        'message' => 'No rider has taken this order yet.'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                \App\Services\FoodOrderFlowService::pickedUp($order, 'restaurant');

                return response()->json([
                    'message' => 'Order handed over to the rider.',
                    'order'   => $order->fresh(),
                ], Response::HTTP_OK);
            }

            $order->status = $request->status;
            $order->save();

            try {
                broadcast(new \App\Events\RestaurantOrderUpdated($order));
            } catch (\Throwable $e) {
                Log::error('RestaurantOrderUpdated broadcast failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            if ($order->status === 'preparing') {
                \App\Services\FoodOrderFlowService::notifyCustomer($order, 'Preparing your food', "{$restaurant->name} is preparing your order.");
                \App\Services\FoodOrderFlowService::notifyRider($order, 'Food is being prepared', "Order #{$order->order_number} is being prepared at {$restaurant->name}.");
            }

            // Push to the assigned rider (works even when live updates are down).
            if ($order->status === 'ready_for_pickup') {
                $rider = $order->ride_id
                    ? (Ride::find($order->ride_id)?->driver
                        ?? \App\Models\RideOffer::where('ride_id', $order->ride_id)->where('status', 'accepted')->latest('id')->first()?->driver)
                    : null;
                if ($rider) {
                    try {
                        app('notificationService')->notifyUsers(
                            [$rider],
                            'Order ready for pickup',
                            "Order #{$order->order_number} is ready at {$restaurant->name}. Please pick it up.",
                            'rides',
                            $order->ride_id,
                            'delivery_details'
                        );
                    } catch (\Throwable $e) {
                        Log::error('Ready-for-pickup rider notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            return response()->json([
                'message' => 'Order status updated.',
                'order'   => $order,
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('API Update Order Status failed', ['error' => $th->getMessage()]);
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

    /**
     * Batch 2 Part 4 -- restaurant registration & approval. One combined
     * submit/update-documents call (unlike the driver side's per-document
     * endpoints) since a restaurant has fewer, more varied document
     * types with no reason to split them. Every field is optional on each
     * call so the owner can fill the form over several visits; actual
     * submission for review only happens once every required document is
     * present (same all-or-nothing gate submitVerification() uses on the
     * driver side).
     */
    public function submitVerificationDocuments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'owner_cnic_front' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'owner_cnic_back' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'ntn_number' => 'nullable|string|max:255',
            'food_authority_license' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'bank_account_title' => 'nullable|string|max:255',
            'bank_iban' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'storefront_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
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
                    'message' => 'Restaurant profile not found -- complete your shop details first.'
                ], Response::HTTP_NOT_FOUND);
            }

            $verification = \App\Models\RestaurantVerification::firstOrNew(['restaurant_id' => $restaurant->id]);
            $verification->restaurant_id = $restaurant->id;

            foreach (['owner_cnic_front', 'owner_cnic_back', 'food_authority_license', 'logo', 'storefront_photo'] as $fileField) {
                if ($request->hasFile($fileField)) {
                    if ($verification->{$fileField}) {
                        Storage::disk('public')->delete($verification->{$fileField});
                    }
                    $verification->{$fileField} = $request->file($fileField)->store('uploads/restaurant-verifications', 'public');
                }
            }

            if ($request->filled('ntn_number')) {
                $verification->ntn_number = $request->input('ntn_number');
            }

            // Review follow-up -- an already-approved restaurant changing
            // its bank details doesn't get to redirect its own payouts on
            // the spot. The new value sits as pending_* until an admin
            // approves it; bank_account_title/bank_iban (what payouts and
            // the Restaurant Payable report actually read) stay as they
            // were. A restaurant that was never approved, or whose own
            // bank fields were never set yet, just saves normally --
            // there's nothing legitimate to protect before a first
            // approval exists.
            $alreadyApproved = $restaurant->is_approved && $verification->status === 'approved';

            foreach (['bank_account_title' => 'pending_bank_account_title', 'bank_iban' => 'pending_bank_iban'] as $liveField => $pendingField) {
                if (!$request->filled($liveField)) {
                    continue;
                }

                $newValue = $request->input($liveField);

                if ($alreadyApproved && $verification->{$liveField} !== null && $newValue !== $verification->{$liveField}) {
                    $verification->{$pendingField} = $newValue;
                } else {
                    $verification->{$liveField} = $newValue;
                }
            }

            $missing = [];
            foreach (['owner_cnic_front', 'owner_cnic_back', 'ntn_number', 'food_authority_license', 'bank_account_title', 'bank_iban', 'logo'] as $required) {
                if (!$verification->{$required}) {
                    $missing[] = $required;
                }
            }

            if (empty($missing)) {
                $verification->status = 'submitted';
                $verification->submitted_at = now();
                $verification->rejection_reason = null;
                $verification->reviewed_by = null;
                $verification->reviewed_at = null;
            }

            $verification->save();

            if ($verification->status === 'submitted') {
                $admins = User::role(['super admin', 'admin', 'operator', 'dispatcher'])->get();
                app('notificationService')->notifyUsers(
                    $admins,
                    'New Restaurant Verification Request',
                    "{$restaurant->name} submitted documents for verification.",
                    'restaurant_verifications',
                    $verification->id,
                    'pending_restaurant_verifications'
                );
            }

            return response()->json([
                'message' => $verification->status === 'submitted'
                    ? 'Verification submitted successfully.'
                    : 'Documents saved. Still missing: ' . implode(', ', $missing),
                'status' => $verification->status,
                'missing' => $verification->status === 'submitted' ? [] : $missing,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Submit Restaurant Verification failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getVerificationStatus(Request $request)
    {
        try {
            $user = $request->user();
            $restaurant = Restaurant::where('user_id', $user->id)->first();

            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant profile not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $verification = $restaurant->verification;

            return response()->json([
                'status' => $verification->status ?? 'not_submitted',
                'rejection_reason' => $verification->rejection_reason ?? null,
                'submitted_at' => $verification?->submitted_at?->toIso8601String(),
                'reviewed_at' => $verification?->reviewed_at?->toIso8601String(),
                'is_approved' => (bool) $restaurant->is_approved,
                'pending_bank_change' => (bool) $verification?->hasPendingBankChange(),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Restaurant Verification Status failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
