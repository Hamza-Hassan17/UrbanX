<?php

namespace App\Http\Controllers\API\Frontend\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use App\Models\RestaurantItem;
use App\Models\RestaurantOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Urban Eats home screen. home() returns its sections in display order and
 * leaves out any section with too little to show; explore() is the paged
 * restaurant list with the filter bar under them.
 */
class HomeFeedController extends Controller
{
    private const SECTION_LIMIT = 10;
    private const MIN_ROW_ITEMS = 2;

    public function home(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'nullable|numeric',
            'lang' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userId = $request->user()->id;

            $orderedIds = RestaurantOrder::where('customer_id', $userId)
                ->whereIn('status', ['delivered', 'completed'])
                ->orderByDesc('id')
                ->pluck('restaurant_id')
                ->unique()
                ->values();

            $banners = HomeBanner::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'image' => asset($b->image),
                    'link_type' => $b->link_type,
                    'link_id' => $b->link_id,
                ])->values();

            $categories = RestaurantCategory::where('is_active', 'active')->orderByDesc('is_popular')->orderBy('name')->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'image' => $c->image ? asset($c->image) : null,
                ])->values();

            $orderAgain = $this->restaurantQuery($request)
                ->whereIn('restaurants.id', $orderedIds->take(self::SECTION_LIMIT))
                ->get()
                ->sortBy(fn ($r) => $orderedIds->search($r->id))
                ->values();

            $topBrands = $this->restaurantQuery($request)
                ->orderByDesc('total_orders')
                ->orderByDesc('reviews_avg_rating')
                ->limit(self::SECTION_LIMIT)
                ->get();

            $tryNew = $this->restaurantQuery($request)
                ->whereNotIn('restaurants.id', $orderedIds)
                ->orderByDesc('restaurants.created_at')
                ->limit(self::SECTION_LIMIT)
                ->get();

            $dishes = RestaurantItem::where('is_active', 'active')
                ->where('is_available', '1')
                ->where('discount_percentage', '>', 0)
                ->whereHas('restaurant', fn ($q) => $q->where('is_active', 'active')->where('is_approved', true))
                ->with('restaurant:id,name,is_open')
                ->orderByDesc('discount_percentage')
                ->limit(self::SECTION_LIMIT)
                ->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'image' => $i->image ? url('storage/' . $i->image) : null,
                    'price' => (float) $i->price,
                    'discount_price' => (float) $i->discount_price,
                    'discount_percentage' => (int) $i->discount_percentage,
                    'restaurant_id' => $i->restaurant_id,
                    'restaurant_name' => $i->restaurant->name,
                    'restaurant_is_open' => $i->restaurant->is_open === 'open',
                ])->values();

            $sections = [
                $this->section('banners', 'banner', null, $banners, 1),
                $this->section('categories', 'category', 'Categories', $categories, 1),
                $this->section('order_again', 'restaurant', 'Order again', $orderAgain->map(fn ($r) => $this->card($r))->values(), 1),
                $this->section('top_brands', 'restaurant', 'Top brands', $topBrands->map(fn ($r) => $this->card($r))->values()),
                $this->section('discounted_dishes', 'dish', 'Dishes on discount', $dishes),
                $this->section('try_something_new', 'restaurant', 'Try something new', $tryNew->map(fn ($r) => $this->card($r))->values()),
            ];

            return response()->json([
                'message' => 'Home feed',
                'sections' => array_values(array_filter($sections)),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Eats Home Feed failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function explore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'nullable|numeric',
            'lang' => 'nullable|numeric',
            'sort' => 'nullable|in:nearest,rating,popular',
            'category_id' => 'nullable|integer',
            'min_rating' => 'nullable|numeric|min:1|max:5',
            'offers' => 'nullable|boolean',
            'open_now' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        try {
            $query = $this->restaurantQuery($request)
                ->when($request->filled('category_id'), fn ($q) => $q->where('restaurant_category_id', $request->category_id))
                ->when($request->boolean('open_now'), fn ($q) => $q->where('is_open', 'open'))
                ->when($request->boolean('offers'), fn ($q) => $q->whereHas('items', fn ($i) => $this->discounted($i)))
                ->when($request->filled('min_rating'), fn ($q) => $q->whereRaw(
                    '(select avg(rating) from restaurant_reviews where restaurant_reviews.restaurant_id = restaurants.id) >= ?',
                    [(float) $request->min_rating]
                ))
                // Open restaurants always list above closed ones.
                ->orderByRaw("is_open = 'open' desc");

            $sort = $request->input('sort', 'nearest');
            if ($sort === 'nearest' && $this->hasLocation($request)) {
                $query->orderByRaw('distance_km is null')->orderBy('distance_km');
            } elseif ($sort === 'rating') {
                $query->orderByDesc('reviews_avg_rating');
            } else {
                $query->orderByDesc('total_orders');
            }

            $page = $query->orderBy('restaurants.id')->paginate((int) $request->input('per_page', 20));

            return response()->json([
                'message' => 'Restaurants',
                'restaurants' => collect($page->items())->map(fn ($r) => $this->card($r))->values(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Eats Explore failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function section(string $key, string $type, ?string $title, $items, int $min = self::MIN_ROW_ITEMS): ?array
    {
        return $items->count() >= $min
            ? ['key' => $key, 'type' => $type, 'title' => $title, 'items' => $items]
            : null;
    }

    private function hasLocation(Request $request): bool
    {
        return $request->filled('lat') && $request->filled('lang');
    }

    private function discounted($itemQuery)
    {
        return $itemQuery->where('is_active', 'active')->where('is_available', '1')->where('discount_percentage', '>', 0);
    }

    private function restaurantQuery(Request $request)
    {
        $query = Restaurant::query()
            ->select('restaurants.*')
            ->where('restaurants.is_active', 'active')
            ->where('restaurants.is_approved', true)
            ->with('category:id,name')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withCount(['orders as total_orders' => fn ($q) => $q->whereIn('status', ['delivered', 'completed'])])
            ->withCount(['items as offer_items_count' => fn ($q) => $this->discounted($q)]);

        if ($this->hasLocation($request)) {
            $query->selectRaw(
                'round(6371 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))), 2) as distance_km',
                [(float) $request->lat, (float) $request->lang, (float) $request->lat]
            );
        }

        return $query;
    }

    private function card(Restaurant $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'logo' => $r->logo ? url('storage/' . $r->logo) : null,
            'cover_image' => $r->cover_image ? url('storage/' . $r->cover_image) : null,
            'address' => $r->address,
            'category' => $r->category?->name,
            'is_open' => $r->is_open === 'open',
            'rating' => $r->reviews_avg_rating !== null ? round((float) $r->reviews_avg_rating, 1) : null,
            'reviews_count' => (int) $r->reviews_count,
            'distance_km' => $r->distance_km !== null ? (float) $r->distance_km : null,
            'has_offer' => (int) $r->offer_items_count > 0,
            'minimum_order_amount' => $r->minimum_order_amount !== null ? (float) $r->minimum_order_amount : null,
        ];
    }
}
