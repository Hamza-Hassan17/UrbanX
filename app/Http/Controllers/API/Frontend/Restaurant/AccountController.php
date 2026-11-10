<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\CustomerPreference;
use App\Models\Restaurant;
use App\Models\RestaurantCart;
use App\Models\RestaurantCartItem;
use App\Models\RestaurantItem;
use App\Models\RestaurantFavourite;
use App\Models\RestaurantOrder;
use App\Models\VoucherCode;
use App\Services\MenuOptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Urban Eats customer screens: All carts, Account, Addresses, Settings.
 */
class AccountController extends Controller
{
    private const LANGUAGES = ['en', 'ur'];

    public function account(Request $request)
    {
        $user = $request->user();
        $image = $user->profile?->profile_image;

        return response()->json([
            'message' => 'Account',
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_image' => $image ? url($image) : null,
                'orders_count' => RestaurantOrder::where('customer_id', $user->id)->count(),
                'favourites_count' => RestaurantFavourite::where('user_id', $user->id)->count(),
                'addresses_count' => CustomerAddress::where('user_id', $user->id)->count(),
                'carts_count' => RestaurantCart::where('customer_id', $user->id)->count(),
                'vouchers_count' => VoucherCode::where('is_active', 'active')->where('expires_at', '>', now())->count(),
                'default_address' => $this->addressQuery($user->id)->first(),
            ],
        ], Response::HTTP_OK);
    }

    /** One card per restaurant; available carts list above unavailable ones. */
    public function carts(Request $request)
    {
        $user = $request->user();
        $carts = RestaurantCart::with('cartItems.restaurantItem')->where('customer_id', $user->id)->latest('updated_at')->get();
        $carts->each(fn ($cart) => MenuOptionService::refreshCart($cart));
        $restaurants = Restaurant::whereIn('id', $carts->pluck('restaurant_id'))->get()->keyBy('id');

        $cards = $carts->map(function ($cart) use ($restaurants) {
            $restaurant = $restaurants->get($cart->restaurant_id);
            $available = $restaurant
                && $restaurant->is_active === 'active'
                && $restaurant->is_approved
                && $restaurant->is_open === 'open';

            return [
                'cart_id' => $cart->id,
                'restaurant_id' => $cart->restaurant_id,
                'restaurant_name' => $restaurant?->name,
                'restaurant_logo' => $restaurant?->logo ? url('storage/' . $restaurant->logo) : null,
                'is_available' => $available,
                'unavailable_label' => $available ? null : 'Store unavailable',
                'subtotal' => (float) $cart->subtotal,
                'voucher_notice' => $cart->voucher_notice,
                'minimum_order_amount' => $restaurant?->minimum_order_amount !== null ? (float) $restaurant->minimum_order_amount : null,
                'items_count' => (int) $cart->cartItems->sum('quantity'),
                'items' => $cart->cartItems->map(fn ($line) => [
                    'cart_item_id' => $line->id,
                    'name' => $line->restaurantItem?->name,
                    'image' => $line->restaurantItem?->image ? url('storage/' . $line->restaurantItem->image) : null,
                    'quantity' => (int) $line->quantity,
                    'unavailable_reason' => $line->unavailable_reason,
                ])->values(),
            ];
        })->sortByDesc('is_available')->values();

        return response()->json([
            'message' => 'All carts',
            'deliver_to' => $this->addressQuery($user->id)->first(),
            'carts' => $cards,
        ], Response::HTTP_OK);
    }

    /** Set a cart line's quantity (the - / + buttons). 0 removes the line. */
    public function updateCartItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_item_id' => 'required|integer',
            'quantity' => 'required|integer|min:0|max:100',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $line = RestaurantCartItem::with('cart')->find($request->cart_item_id);
        if (!$line || (int) $line->cart?->customer_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Cart item not found'], Response::HTTP_NOT_FOUND);
        }

        $cart = $line->cart;
        if ((int) $request->quantity === 0) {
            $line->delete();
        } else {
            $line->update([
                'quantity' => (int) $request->quantity,
                'total_price' => round((float) $line->price_per_item * (int) $request->quantity, 2),
            ]);
        }

        if ($cart->cartItems()->count() === 0) {
            $cart->delete();
            return response()->json(['message' => 'Cart is now empty', 'cart' => null], Response::HTTP_OK);
        }

        $cart->touch(); // edited now: moves to the top of All carts
        $cart = RestaurantCart::with('cartItems.restaurantItem')->find($cart->id);
        MenuOptionService::refreshCart($cart);

        return response()->json(['message' => 'Cart updated', 'cart' => $cart], Response::HTTP_OK);
    }

    /**
     * "Popular with your order": the restaurant's best sellers (last 90
     * days) that aren't already in the cart, topped up with its other
     * available dishes while it has few orders.
     */
    public function popularItems(Request $request, $restaurant_id)
    {
        $inCart = $request->filled('cart_id')
            ? RestaurantCartItem::whereHas('cart', fn ($q) => $q->where('id', $request->cart_id)->where('customer_id', $request->user()->id))
                ->pluck('restaurant_item_id')
            : collect();

        $available = fn ($q) => $q->where('restaurant_id', $restaurant_id)
            ->where('is_active', 'active')
            ->where('is_available', '1')
            ->whereNotIn('id', $inCart);

        $bestSellerIds = DB::table('restaurant_order_items')
            ->join('restaurant_orders', 'restaurant_orders.id', '=', 'restaurant_order_items.order_id')
            ->where('restaurant_orders.restaurant_id', $restaurant_id)
            ->whereIn('restaurant_orders.status', ['delivered', 'completed'])
            ->where('restaurant_orders.created_at', '>=', now()->subDays(90))
            ->groupBy('restaurant_order_items.restaurant_item_id')
            ->orderByRaw('SUM(restaurant_order_items.quantity) DESC')
            ->limit(30)
            ->pluck('restaurant_order_items.restaurant_item_id');

        $items = $available(RestaurantItem::query())->whereIn('id', $bestSellerIds)->get()
            ->sortBy(fn ($i) => $bestSellerIds->search($i->id))
            ->values();

        if ($items->count() < 10) {
            $items = $items->concat(
                $available(RestaurantItem::query())->whereNotIn('id', $items->pluck('id'))
                    ->orderByDesc('discount_percentage')->orderByDesc('id')
                    ->limit(10 - $items->count())->get()
            );
        }

        return response()->json([
            'message' => 'Popular with your order',
            'items' => $items->take(10)->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'image' => $i->image ? url('storage/' . $i->image) : null,
                'price' => (float) $i->price,
                'discount_price' => $i->discount_price !== null ? (float) $i->discount_price : null,
                'discount_percentage' => (int) $i->discount_percentage,
                'has_options' => $i->optionGroups()->exists(),
            ])->values(),
        ], Response::HTTP_OK);
    }

    public function deleteCart(Request $request, $cart_id)
    {
        $cart = RestaurantCart::where('id', $cart_id)->where('customer_id', $request->user()->id)->first();
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], Response::HTTP_NOT_FOUND);
        }

        DB::transaction(function () use ($cart) {
            $cart->cartItems()->delete();
            $cart->delete();
        });

        return response()->json(['message' => 'Cart removed'], Response::HTTP_OK);
    }

    public function addresses(Request $request)
    {
        return response()->json([
            'message' => 'Addresses',
            'addresses' => $this->addressQuery($request->user()->id)->get(),
        ], Response::HTTP_OK);
    }

    public function storeAddress(Request $request)
    {
        $validator = $this->addressValidator($request);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $userId = $request->user()->id;
        $address = DB::transaction(function () use ($request, $userId, $validator) {
            $makeDefault = $request->boolean('is_default') || !CustomerAddress::where('user_id', $userId)->exists();
            if ($makeDefault) {
                CustomerAddress::where('user_id', $userId)->update(['is_default' => false]);
            }
            return CustomerAddress::create($validator->safe()->except('is_default') + ['user_id' => $userId, 'is_default' => $makeDefault]);
        });

        return response()->json(['message' => 'Address saved', 'address' => $address], Response::HTTP_CREATED);
    }

    public function updateAddress(Request $request, $id)
    {
        $address = $this->ownedAddress($request, $id);
        if (!$address) {
            return $this->addressNotFound();
        }

        $validator = $this->addressValidator($request);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        DB::transaction(function () use ($request, $address, $validator) {
            $address->fill($validator->safe()->except('is_default'));
            if ($request->boolean('is_default')) {
                CustomerAddress::where('user_id', $address->user_id)->update(['is_default' => false]);
                $address->is_default = true;
            }
            $address->save();
        });

        return response()->json(['message' => 'Address updated', 'address' => $address->fresh()], Response::HTTP_OK);
    }

    public function setDefaultAddress(Request $request, $id)
    {
        $address = $this->ownedAddress($request, $id);
        if (!$address) {
            return $this->addressNotFound();
        }

        DB::transaction(function () use ($address) {
            CustomerAddress::where('user_id', $address->user_id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json(['message' => 'Default address updated', 'address' => $address->fresh()], Response::HTTP_OK);
    }

    public function deleteAddress(Request $request, $id)
    {
        $address = $this->ownedAddress($request, $id);
        if (!$address) {
            return $this->addressNotFound();
        }

        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                CustomerAddress::where('user_id', $address->user_id)->latest('id')->first()?->update(['is_default' => true]);
            }
        });

        return response()->json(['message' => 'Address deleted'], Response::HTTP_OK);
    }

    public function settings(Request $request)
    {
        return response()->json([
            'message' => 'Settings',
            'settings' => $this->settingsPayload($this->preference($request->user()->id)),
            'languages' => self::LANGUAGES,
        ], Response::HTTP_OK);
    }

    /** Partial updates: only the fields sent are changed. */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language' => 'sometimes|in:' . implode(',', self::LANGUAGES),
            'push_offers' => 'sometimes|boolean',
            'email_offers' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $preference = $this->preference($request->user()->id);
        $preference->update($validator->validated());

        return response()->json([
            'message' => 'Settings updated',
            'settings' => $this->settingsPayload($preference->fresh()),
        ], Response::HTTP_OK);
    }

    private function preference(int $userId): CustomerPreference
    {
        return CustomerPreference::firstOrCreate(['user_id' => $userId]);
    }

    private function settingsPayload(CustomerPreference $preference): array
    {
        return [
            'language' => $preference->language ?? 'en',
            'push_offers' => $preference->push_offers ?? true,
            'email_offers' => $preference->email_offers ?? false,
        ];
    }

    private function addressQuery(int $userId)
    {
        return CustomerAddress::where('user_id', $userId)->orderByDesc('is_default')->orderByDesc('id');
    }

    private function ownedAddress(Request $request, $id): ?CustomerAddress
    {
        return CustomerAddress::where('id', $id)->where('user_id', $request->user()->id)->first();
    }

    private function addressNotFound()
    {
        return response()->json(['message' => 'Address not found'], Response::HTTP_NOT_FOUND);
    }

    private function addressValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'label' => 'required|string|max:50',
            'address' => 'required|string|max:500',
            'details' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_default' => 'sometimes|boolean',
        ]);
    }

    private function validationError($validator)
    {
        return response()->json([
            'message' => 'Validation Error',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
