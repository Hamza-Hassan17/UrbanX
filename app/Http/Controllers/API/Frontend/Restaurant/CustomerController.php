<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantCart;
use App\Models\RestaurantCartItem;
use App\Models\RestaurantCategory;
use App\Models\RestaurantItem;
use App\Models\RestaurantMenu;
use App\Models\RestaurantOrder;
use App\Models\VoucherCode;
use App\Models\RestaurantFavourite;
use App\Models\RestaurantReview;
use App\Models\VehicleType;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\FirebaseService;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
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

            $popularRestaurantCategories = RestaurantCategory::where('is_active', 'active')->where('is_popular', '1')->limit(5)->get();
            $popularRestaurantCategories = $popularRestaurantCategories->map(function ($item) {
                $item->image = $item->image ? url('storage/' . $item->image) : null;
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
                $item->logo = $item->logo ? url('storage/' . $item->logo) : null;
                $item->cover_image = $item->cover_image ? url('storage/' . $item->cover_image) : null;
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

    public function getRestaurants(Request $request, $category = null)
    {
        try {
            $query = Restaurant::where('is_active', 'active');

            if ($category) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('id', $category);
                });
            }

            $restaurants = $query->get()->map(function ($item) {
                $item->logo = $item->logo ? url('storage/' . $item->logo) : null;
                $item->cover_image = $item->cover_image ? url('storage/' . $item->cover_image) : null;
                return $item;
            });

            return response()->json([
                'message' => 'Restaurants List',
                'restaurants' => $restaurants,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Restaurants failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function searchRestaurants(Request $request)
    {
        try {
            $searchTerm = $request->search;

            $restaurants = Restaurant::where('is_active', 'active')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', "%$searchTerm%")
                        ->orWhere('description', 'like', "%$searchTerm%");
                })
                ->get()
                ->map(function ($item) {
                    $item->logo = $item->logo ? url('storage/' . $item->logo) : null;
                    $item->cover_image = $item->cover_image ? url('storage/' . $item->cover_image) : null;
                    return $item;
                });

            return response()->json([
                'message' => 'Search Results',
                'restaurants' => $restaurants,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Search Restaurants failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getRestaurantDetails(Request $request, $restaurant_id)
    {
        try {
            $restaurant = Restaurant::with('category')->where('id', $restaurant_id)->where('is_active', 'active')->first();

            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found!'
                ], Response::HTTP_NOT_FOUND);
            }

            $restaurant->logo = $restaurant->logo ? url('storage/' . $restaurant->logo) : null;
            $restaurant->cover_image = $restaurant->cover_image ? url('storage/' . $restaurant->cover_image) : null;
            $restaurant->is_favourite = RestaurantFavourite::where('user_id', $request->user()->id)
                ->where('restaurant_id', $restaurant_id)
                ->exists();

            $restaurantMenus = RestaurantMenu::where('restaurant_id', $restaurant_id)->where('is_active', 'active')->get();

            return response()->json([
                'message' => 'Restaurant Details',
                'restaurant' => $restaurant,
                'menus' => $restaurantMenus,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Restaurant Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getMenuItems(Request $request, $menu_id)
    {
        try {
            $menuItems = RestaurantItem::where('restaurant_menu_id', $menu_id)->where('is_active', 'active')->get();

            $menuItems = $menuItems->map(function ($item) {
                $item->image = $item->image ? url('storage/' . $item->image) : null;
                return $item;
            });

            return response()->json([
                'message' => 'Menu Items',
                'menu_items' => $menuItems,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Menu Items failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getMenuItemDetails(Request $request, $item_id)
    {
        try {
            $menuItem = RestaurantItem::where('id', $item_id)->where('is_active', 'active')->first();

            if (!$menuItem) {
                return response()->json([
                    'message' => 'Menu Item not found!'
                ], Response::HTTP_NOT_FOUND);
            }

            $menuItem->image = $menuItem->image ? url('storage/' . $menuItem->image) : null;

            return response()->json([
                'message' => 'Menu Item Details',
                'menu_item' => $menuItem,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Menu Item Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addToCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'restaurant_id' => 'required|exists:restaurants,id',
                'menu_item_id'  => 'required|exists:restaurant_items,id',
                'quantity'      => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation Error',
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $userId = $request->user()->id;

            // Get valid item
            $item = RestaurantItem::where('id', $request->menu_item_id)
                ->where('restaurant_id', $request->restaurant_id)
                ->where('is_available', '1')
                ->where('is_active', 'active')
                ->first();

            if (!$item) {
                return response()->json([
                    'message' => 'Item not available'
                ], Response::HTTP_BAD_REQUEST);
            }

            $price = $item->discount_price ?? $item->price;

            $restaurantCart = RestaurantCart::firstOrCreate(
                [
                    'restaurant_id' => $request->restaurant_id,
                    'customer_id'   => $userId,
                ],
                [
                    'subtotal'      => 0,
                    'discount'      => 0,
                    'tax'           => 0,
                    'platform_fee'  => 0,
                    'delivery_fee'  => 0,
                    'total_price'   => 0,
                ]
            );

            // Check existing cart item
            $cartItem = $restaurantCart->cartItems()
                ->where('restaurant_item_id', $item->id)
                ->first();

            if ($cartItem) {
                $cartItem->increment('quantity', $request->quantity);
                $cartItem->update([
                    'total_price' => $cartItem->quantity * $price,
                ]);
            } else {
                $restaurantCart->cartItems()->create([
                    'restaurant_item_id' => $item->id,
                    'quantity'           => $request->quantity,
                    'price_per_item'     => $price,
                    'total_price'        => $request->quantity * $price,
                ]);
            }

            // Recalculate subtotal
            $restaurantCart->subtotal = $restaurantCart->cartItems()->sum('total_price');

            $restaurantCart->total_price =
                $restaurantCart->subtotal
                - $restaurantCart->discount
                + $restaurantCart->tax
                + $restaurantCart->platform_fee
                + $restaurantCart->delivery_fee;

            $restaurantCart->save();

            return response()->json([
                'message' => 'Item added to cart successfully!',
                'cart_id' => $restaurantCart->id,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Add to Cart failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getCarts(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $carts = RestaurantCart::with('cartItems.restaurantItem')
                ->where('customer_id', $userId)
                ->get();

            // Format item images
            $carts->each(function ($cart) {
                $cart->cartItems->each(function ($cartItem) {
                    if ($cartItem->restaurantItem) {
                        $cartItem->restaurantItem->image = url('storage/' . $cartItem->restaurantItem->image);
                    }
                });
            });

            return response()->json([
                'message' => 'All Carts',
                'carts' => $carts,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Cart failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteCartItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cart_item_id' => 'required|exists:restaurant_cart_items,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation Error',
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $cartItem = RestaurantCartItem::find($request->cart_item_id);

            if (!$cartItem) {
                return response()->json([
                    'message' => 'Cart item not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $cart = $cartItem->cart;

            $cartItem->delete();

            // Recalculate subtotal
            $cart->subtotal = $cart->cartItems()->sum('total_price');

            $cart->total_price =
                $cart->subtotal
                - $cart->discount
                + $cart->tax
                + $cart->platform_fee
                + $cart->delivery_fee;

            $cart->save();

            if ($cart->cartItems()->count() == 0) {
                $cart->delete();
            }

            return response()->json([
                'message' => 'Cart item deleted successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Delete Cart Item failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getCartDetails(Request $request, $cart_id)
    {
        try {
            $userId = $request->user()->id;

            $cart = RestaurantCart::with('cartItems.restaurantItem')
                ->where('id', $cart_id)
                ->where('customer_id', $userId)
                ->first();

            if (!$cart) {
                return response()->json([
                    'message' => 'Cart not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Format item images
            $cart->cartItems->each(function ($cartItem) {
                if ($cartItem->restaurantItem) {
                    $cartItem->restaurantItem->image = url('storage/' . $cartItem->restaurantItem->image);
                }
            });

            return response()->json([
                'message' => 'Cart Details',
                'cart' => $cart,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Cart Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getVouchers(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $vouchers = VoucherCode::where('is_active', 'active')
                ->where('expires_at', '>', now())
                ->get();

            return response()->json([
                'message' => 'Available Vouchers',
                'vouchers' => $vouchers,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Vouchers failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function applyVoucher(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cart_id' => 'required|exists:restaurant_carts,id',
                'code'    => 'required|exists:voucher_codes,code',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation Error',
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::beginTransaction();

            $cart = RestaurantCart::find($request->cart_id);

            if (!$cart) {
                return response()->json([
                    'message' => 'Cart not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $voucher = VoucherCode::where('code', $request->code)
                ->where('is_active', 'active')
                ->where('expires_at', '>', now())
                ->first();

            if (!$voucher) {
                return response()->json([
                    'message' => 'Invalid voucher code'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($cart->voucher_code_id) {
                return response()->json([
                    'message' => 'Voucher already applied to this cart'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($voucher->expires_at < now()) {
                return response()->json([
                    'message' => 'Voucher code has expired'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($voucher->minimum_purchase && $cart->subtotal < $voucher->minimum_purchase) {
                return response()->json([
                    'message' => 'Cart total does not meet the minimum purchase requirement for this voucher'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($voucher->per_user_limit > 0) {
                $usedCount = RestaurantOrder::where('customer_id', $request->user()->id)
                    ->where('voucher_code_id', $voucher->id)
                    ->count();

                if ($usedCount >= $voucher->per_user_limit) {
                    return response()->json([
                        'message' => 'You have already used this voucher the maximum number of times allowed'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            if($voucher->discount_type == 'percentage') {
                $cart->discount = ($voucher->discount_amount / 100) * $cart->subtotal;
            } else {
                $cart->discount = $voucher->discount_amount;
            }

            $cart->voucher_code_id = $voucher->id;

            // Recalculate total price
            $cart->total_price =
                $cart->subtotal
                - $cart->discount
                + $cart->tax
                + $cart->platform_fee
                + $cart->delivery_fee;

            $cart->save();

            DB::commit();
            return response()->json([
                'message' => 'Voucher applied successfully!',
                'new_total_price' => $cart->total_price,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('API Apply Voucher failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function calculateDeliveryFare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'distance_km' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $vehicleType = VehicleType::where('is_delivery', '1')->first();
            $distance = $request->distance_km;

            /* 🔥 Get current boost hour */
            $now = now()->format('H:i');
            $busyHour = DB::table('boost_hours')
                ->where('start', '<=', $now)
                ->where('end', '>=', $now)
                ->first();

            $multiplier = $busyHour ? (float) $busyHour->multiplier : 1.0;
            $isBoost = $multiplier > 1;

            // Default prices
            $firstKmPrice = 150.00;
            $otherKmPrice = 45.00;

            if ($vehicleType) {
                $firstKmPrice = $vehicleType->first_km_price ?? $firstKmPrice;
                $otherKmPrice = $vehicleType->other_km_price ?? $otherKmPrice;
            }

            // Fare calculation
            $totalFare = $firstKmPrice;

            if ($distance > 1) {
                $totalFare += ($distance - 1) * $otherKmPrice;
            }

            $boostedFare = $totalFare * $multiplier;

            return response()->json([
                'total_fare'       => round($totalFare, 2),
                'is_boost'         => $isBoost,
                'boost_multiplier' => $multiplier,
                'boosted_fare'     => round($boostedFare, 2),
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('API Calculate Shipping Fare failed', ['error' => $th->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function placeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|exists:restaurant_carts,id',
            'payment_method' => 'required|in:cod,card,cash,jazzcash,easypaisa',
            'delivery_lat' => 'required',
            'delivery_lang' => 'required',
            'rider_note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        try {
            $cart = RestaurantCart::with('cartItems')->find($request->cart_id);
            if (!$cart) {
                return response()->json([
                    'message' => 'Cart not found'
                ], Response::HTTP_NOT_FOUND);
            }

            DB::beginTransaction();

            $vehicleType = VehicleType::where('is_delivery', '1')->first();

            $restaurant = Restaurant::find($cart->restaurant_id);

            $order = new RestaurantOrder();
            $order->restaurant_id = $cart->restaurant_id;
            $order->customer_id = $request->user()->id;
            $order->voucher_code_id = $cart->voucher_code_id;
            $order->order_number = 'ORD-' . strtoupper(uniqid());
            $order->subtotal = $cart->subtotal;
            $order->discount = $cart->discount;
            $order->tax = $cart->tax;
            $order->platform_fee = $cart->platform_fee;
            $order->delivery_fee = $cart->delivery_fee;
            $order->total_price = $cart->total_price;
            $order->delivery_lat = $request->delivery_lat;
            $order->delivery_lang = $request->delivery_lang;
            $order->rider_note = $request->rider_note;
            $order->payment_method = $request->payment_method;
            $order->payment_status = $request->payment_method == 'cod' ? 'unpaid' : 'paid';
            $order->status = 'pending';
            $order->save();

            foreach ($cart->cartItems as $cartItem) {
                $order->items()->create([
                    'restaurant_item_id' => $cartItem->restaurant_item_id,
                    'quantity' => $cartItem->quantity,
                    'price_per_item' => $cartItem->price_per_item,
                    'total_price' => $cartItem->total_price,
                    'notes' => null,
                ]);
            }

            $ride = new Ride();
            $ride->passenger_id = $request->user()->id;
            $ride->vehicle_type_id = $vehicleType->id;
            $ride->pickup_latitude = $restaurant->latitude;
            $ride->pickup_longitude = $restaurant->longitude;
            $ride->dropoff_latitude = $request->delivery_lat;
            $ride->dropoff_longitude = $request->delivery_lang;
            $ride->distance_km = $request->distance_km;
            $ride->duration_minutes = $request->duration_minutes;
            $ride->subtotal = $request->subtotal;
            $ride->total_fare = $request->total_fare;
            $ride->requested_at = now();
            $ride->status = 'requested';
            $ride->ride_type = 'delivery';
            $ride->save();

            $order->ride_id = $ride->id;
            $order->save();

            // Clear the cart after placing the order
            $cart->delete();

            DB::commit();

            // Notify restaurant app in real time
            $this->firebase
                ->getReference('restaurant_orders/' . $order->id)
                ->set([
                    'order_id'      => $order->id,
                    'order_number'  => $order->order_number,
                    'status'        => 'pending',
                    'restaurant_id' => $order->restaurant_id,
                    'customer_id'   => $order->customer_id,
                    'total_price'   => (float) $order->total_price,
                    'updated_at'    => now()->toDateTimeString(),
                ]);

            return response()->json([
                'message' => 'Order placed successfully!',
                'order' => $order->load('items.restaurantItem'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('API Place Order failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getOrders(Request $request)
    {
        try {
            $orders = RestaurantOrder::with('items.restaurantItem')
                ->where('customer_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->get();

            // Format item images
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    if ($item->restaurantItem && $item->restaurantItem->image) {
                        $item->image = url('storage/' . $item->restaurantItem->image);
                    }
                }
            }

            return response()->json([
                'message' => 'Your Orders',
                'orders' => $orders,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Orders failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getOrderDetails(Request $request, $order_id)
    {
        try {
            $order = RestaurantOrder::with('items.restaurantItem')
                ->where('id', $order_id)
                ->where('customer_id', $request->user()->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Format item images
            foreach ($order->items as $item) {
                if ($item->restaurantItem && $item->restaurantItem->image) {
                    $item->image = url('storage/' . $item->restaurantItem->image);
                }
            }

            return response()->json([
                'message' => 'Order Details',
                'order' => $order,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Order Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function postReview(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'restaurant_id' => 'required|exists:restaurants,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            $review = new RestaurantReview();
            $review->restaurant_id = $request->restaurant_id;
            $review->customer_id = $request->user()->id;
            $review->rating = $request->rating;
            $review->comment = $request->comment;
            $review->save();

            return response()->json([
                'message' => 'Review posted successfully!',
                'review' => $review,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Post Review failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getReviews(Request $request, $restaurant_id)
    {
        try {
            $reviews = RestaurantReview::with('customer')
                ->where('restaurant_id', $restaurant_id)
                ->where('is_active', 'active')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'message' => 'Restaurant Reviews',
                'reviews' => $reviews,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Reviews failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function toggleFavourite(Request $request, $restaurant_id)
    {
        try {
            $user = $request->user();

            $restaurant = Restaurant::find($restaurant_id);
            if (!$restaurant) {
                return response()->json([
                    'message' => 'Restaurant not found.'
                ], Response::HTTP_NOT_FOUND);
            }

            $existing = RestaurantFavourite::where('user_id', $user->id)
                ->where('restaurant_id', $restaurant_id)
                ->first();

            if ($existing) {
                $existing->delete();
                return response()->json([
                    'message'       => 'Removed from favourites.',
                    'is_favourite'  => false,
                ], Response::HTTP_OK);
            }

            RestaurantFavourite::create([
                'user_id'       => $user->id,
                'restaurant_id' => $restaurant_id,
            ]);

            return response()->json([
                'message'       => 'Added to favourites.',
                'is_favourite'  => true,
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('toggleFavourite failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getFavourites(Request $request)
    {
        try {
            $user = $request->user();

            $favourites = RestaurantFavourite::where('user_id', $user->id)
                ->with('restaurant')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($fav) {
                    $r = $fav->restaurant;
                    if (!$r) return null;
                    $r->logo        = $r->logo        ? url('storage/' . $r->logo)        : null;
                    $r->cover_image = $r->cover_image ? url('storage/' . $r->cover_image) : null;
                    return $r;
                })
                ->filter()
                ->values();

            return response()->json([
                'message'    => 'Favourite Restaurants',
                'favourites' => $favourites,
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error('getFavourites failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
