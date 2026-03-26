<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\RestaurantCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view restaurant category');
        try {
            $categories = RestaurantCategory::get();
            return view('dashboard.restaurant.category.index',compact('categories'));
        } catch (\Throwable $th) {
            Log::error('Restaurant Category Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create restaurant category');
        try {
            return view('dashboard.restaurant.category.create');
        } catch (\Throwable $th) {
            Log::error('Restaurant Category Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create restaurant category');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max_size',
            'is_popular' => 'nullable|in:on',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $category = new RestaurantCategory();
            $category->name = $request->name;
            if ($request->hasFile('image')) {
                $Image = $request->file('image');
                $Image_ext = $Image->getClientOriginalExtension();
                $Image_name = time() . 'image.' . $Image_ext;

                $Image_path = 'uploads/restaurants/categories';
                $Image->move(public_path($Image_path), $Image_name);
                $category->image = $Image_path . "/" . $Image_name;
            }
            $category->is_popular = $request->is_popular ? '1' : '0';
            $category->save();

            DB::commit();
            return redirect()->route('dashboard.restaurant-categories.index')->with('success', 'Restaurant Category Created Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Restaurant Category Created Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->authorize('update restaurant category');
        try {
            $category = RestaurantCategory::findOrFail($id);
            return view('dashboard.restaurant.category.edit', compact('category'));
        } catch (\Throwable $th) {
            Log::error('Restaurant Category Edit Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update restaurant category');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max_size',
            'is_popular' => 'nullable|in:on',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $category = RestaurantCategory::findOrFail($id);
            $category->name = $request->name;
            if ($request->hasFile('image')) {
                if (isset($category->image) && File::exists(public_path($category->image))) {
                    File::delete(public_path($category->image));
                }
                $Image = $request->file('image');
                $Image_ext = $Image->getClientOriginalExtension();
                $Image_name = time() . 'image.' . $Image_ext;

                $Image_path = 'uploads/restaurants/categories';
                $Image->move(public_path($Image_path), $Image_name);
                $category->image = $Image_path . "/" . $Image_name;
            }
            $category->is_popular = $request->is_popular ? '1' : '0';
            $category->save();

            DB::commit();
            return redirect()->route('dashboard.restaurant-categories.index')->with('success', 'Restaurant Category Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Restaurant Category Created Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete restaurant category');
        try {
            $category = RestaurantCategory::findOrFail($id);
            if (isset($category->image) && File::exists(public_path($category->image))) {
                File::delete(public_path($category->image));
            }
            $category->delete();
            return redirect()->back()->with('success', 'Restaurant Category Deleted Successfully!');
        } catch (\Throwable $th) {
            Log::error('Restaurant Category Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(string $id)
    {
        $this->authorize('update restaurant category');
        try {
            $category = RestaurantCategory::findOrFail($id);
            $message = $category->is_active == 'active' ? 'Restaurant Category Deactivated Successfully' : 'Restaurant Category Activated Successfully';
            if ($category->is_active == 'active') {
                $category->is_active = 'inactive';
                $category->save();
            } else {
                $category->is_active = 'active';
                $category->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Restaurant Category Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
