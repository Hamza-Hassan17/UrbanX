<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use App\Models\Restaurant;
use App\Models\RestaurantCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class HomeBannerController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('view restaurant category');

        $banners = HomeBanner::orderBy('sort_order')->orderBy('id')->get();
        $restaurants = Restaurant::where('is_active', 'active')->orderBy('name')->get(['id', 'name']);
        $categories = RestaurantCategory::where('is_active', 'active')->orderBy('name')->get(['id', 'name']);

        return view('dashboard.restaurant.home-banners.index', compact('banners', 'restaurants', 'categories'));
    }

    public function store(Request $request)
    {
        $this->authorize('update restaurant category');

        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg|max_size',
            'link_type' => 'required|in:none,restaurant,category',
            'restaurant_id' => 'required_if:link_type,restaurant|nullable|exists:restaurants,id',
            'category_id' => 'required_if:link_type,category|nullable|exists:restaurant_categories,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('image');
        $path = 'uploads/restaurants/banners';
        $name = time() . 'banner.' . $file->getClientOriginalExtension();
        $file->move(public_path($path), $name);

        HomeBanner::create([
            'title' => $request->title,
            'image' => $path . '/' . $name,
            'link_type' => $request->link_type,
            'link_id' => match ($request->link_type) {
                'restaurant' => $request->restaurant_id,
                'category' => $request->category_id,
                default => null,
            },
            'sort_order' => (int) $request->sort_order,
        ]);

        return redirect()->back()->with('success', 'Banner added.');
    }

    public function toggle(HomeBanner $homeBanner)
    {
        $this->authorize('update restaurant category');

        $homeBanner->update(['is_active' => !$homeBanner->is_active]);

        return redirect()->back()->with('success', 'Banner updated.');
    }

    public function destroy(HomeBanner $homeBanner)
    {
        $this->authorize('update restaurant category');

        if (File::exists(public_path($homeBanner->image))) {
            File::delete(public_path($homeBanner->image));
        }
        $homeBanner->delete();

        return redirect()->back()->with('success', 'Banner deleted.');
    }
}
