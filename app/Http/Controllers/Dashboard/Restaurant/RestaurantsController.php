<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use App\Models\RestaurantItem;
use App\Models\RestaurantMenu;
use App\Models\RestaurantOrder;
use App\Models\RestaurantReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class RestaurantsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view restaurant');
        try {
            $restaurants = Restaurant::get();
            return view('dashboard.restaurant.restaurants.index',compact('restaurants'));
        } catch (\Throwable $th) {
            Log::error('Restaurants Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $this->authorize('view restaurant');
        try {
            $restaurant = Restaurant::with('category', 'user','orders','menus.items', 'reviews')->findOrFail($id);
            $categories = RestaurantCategory::where('is_active', 'active')->get();
            return view('dashboard.restaurant.restaurants.show',compact('restaurant', 'categories'));
        } catch (\Throwable $th) {
            Log::error('Restaurants Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update restaurant');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'restaurant_category_id' => 'required|exists:restaurant_categories,id',
            'address' => 'required|string',
            'description' => 'required|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max_size',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max_size',
            'is_open' => 'required|in:open,closed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $restaurant = Restaurant::findOrFail($id);
            $restaurant->name = $request->name;
            $restaurant->restaurant_category_id = $request->restaurant_category_id;
            $restaurant->address = $request->address;
            $restaurant->description = $request->description;
            $restaurant->is_open = $request->is_open;
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

            DB::commit();
            return redirect()->route('dashboard.restaurants.show', $id)->with('success', 'Restaurant Details Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Restaurant Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function updateStatus(string $id)
    {
        $this->authorize('update restaurant');
        try {
            $restaurant = Restaurant::findOrFail($id);
            $message = $restaurant->is_active == 'active' ? 'Restaurant Deactivated Successfully' : 'Restaurant Activated Successfully';
            if ($restaurant->is_active == 'active') {
                $restaurant->is_active = 'inactive';
                $restaurant->save();
            } else {
                $restaurant->is_active = 'active';
                $restaurant->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Restaurant Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateRestaurantMenu(Request $request, string $id)
    {
        $this->authorize('update restaurant');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'is_active' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $menu = RestaurantMenu::findOrFail($id);
            $menu->name = $request->name;
            $menu->description = $request->description;
            $menu->is_active = $request->is_active;
            $menu->save();

            DB::commit();
            return redirect()->route('dashboard.restaurants.show', $menu->restaurant_id)->with('success', 'Restaurant Menu Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Restaurant Menu Update Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function updateRestaurantItem(Request $request, string $id)
    {
        $this->authorize('update restaurant');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'restaurant_menu_id' => 'required|exists:restaurant_menus,id',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'discount_percentage' => 'nullable|integer|min:0|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max_size',
            'preparation_time' => 'nullable|integer',
            'is_available' => 'required|in:0,1',
            'is_featured' => 'required|in:0,1',
            'is_active' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $restaurantItem = RestaurantItem::findOrFail($id);
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

            DB::commit();
            return redirect()->route('dashboard.restaurants.show', $restaurantItem->restaurant_id)->with('success', 'Restaurant Menu Item Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Restaurant Menu Item Update Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function updateRestaurantOrder(Request $request, string $id)
    {
        $this->authorize('update restaurant');
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,preparing,delivered,completed,cancelled',
            'payment_status' => 'required|in:unpaid,paid,failed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $order = RestaurantOrder::findOrFail($id);
            $order->status = $request->input('status');
            $order->payment_status = $request->input('payment_status');
            $order->save();

            DB::commit();
            return redirect()->route('dashboard.restaurants.show', $order->restaurant_id)->with('success', 'Restaurant Order Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Restaurant Order Update Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function updateRestaurantSchedule(Request $request, $id)
    {
        try {
            DB::beginTransaction();
                $restaurant = Restaurant::findOrFail($id);

                $schedule = [];

                foreach ($request->start as $day => $start) {
                    $end = $request->end[$day] ?? '';

                    if ($start && $end) {
                        $schedule[$day] = $start . ' - ' . $end;
                    }
                }

                $restaurant->weekly_schedule = json_encode($schedule);
                $restaurant->save();

            DB::commit();
            return redirect()->route('dashboard.restaurants.show', $restaurant->id)->with('success', 'Restaurant Schedule Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Restaurant Schedule Update Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function updateRestaurantReview(Request $request, $id)
    {
        $this->authorize('update restaurant');
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|between:0,5',
            'comment' => 'required|string',
            'is_active' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $review = RestaurantReview::findOrFail($id);
            $review->rating = $request->input('rating');
            $review->comment = $request->input('comment');
            $review->is_active = $request->input('is_active');
            $review->save();

            DB::commit();
            return redirect()->route('dashboard.restaurants.show', $review->restaurant_id)->with('success', 'Review Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Review Update Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }
}
