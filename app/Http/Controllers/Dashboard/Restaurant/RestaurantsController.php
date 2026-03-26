<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            return view('dashboard.restaurant.restaurants.show',compact('restaurant'));
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
        //
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
}
