<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\CustomerPreference;
use App\Models\Restaurant;
use App\Models\RestaurantCart;
use App\Models\RestaurantFavourite;
use App\Models\RestaurantOrder;
use App\Models\VoucherCode;
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
                'minimum_order_amount' => $restaurant?->minimum_order_amount !== null ? (float) $restaurant->minimum_order_amount : null,
                'items_count' => (int) $cart->cartItems->sum('quantity'),
                'items' => $cart->cartItems->map(fn ($line) => [
                    'cart_item_id' => $line->id,
                    'name' => $line->restaurantItem?->name,
                    'image' => $line->restaurantItem?->image ? url('storage/' . $line->restaurantItem->image) : null,
                    'quantity' => (int) $line->quantity,
                ])->values(),
            ];
        })->sortByDesc('is_available')->values();

        return response()->json([
            'message' => 'All carts',
            'deliver_to' => $this->addressQuery($user->id)->first(),
            'carts' => $cards,
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
