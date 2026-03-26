<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChauffeursVehicle extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view chauffeur vehicle');
        try {
            $vehicles = Vehicle::with('carBrand')->get();
            return view('dashboard.chauffeurs.vehicles.index', compact('vehicles'));
        } catch (\Throwable $th) {
            Log::error('Vehicles Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create chauffeur vehicle');
        try {
            $carBrands = CarBrand::where('is_active', 'active')->get();
            return view('dashboard.chauffeurs.vehicles.create', compact('carBrands'));
        } catch (\Throwable $th) {
            Log::error('Vehicles Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create chauffeur vehicle');
        $validator = Validator::make($request->all(), [
            'car_brand_id' => 'required|exists:car_brands,id',
            'title' => 'required|string|max:255',
            'car_model' => 'required|string|max:255',
            'car_fuel_type' => 'required|string|max:255',
            'year' => 'required|string|max:255',
            'price_per_hour' => 'required|numeric',
            'price_per_day' => 'required|numeric',
            'price_per_week' => 'required|numeric',
            'price_per_month' => 'required|numeric',
            'transmission' => 'required|string|max:255',
            'color' => 'nullable|string|max:255',
            'seats' => 'required|integer',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_featured' => 'nullable|in:on',
            'main_image' => 'required|image|mimes:jpeg,png,jpg|max_size',
        ]);

        if ($validator->fails()) {
            Log::error('vehicle Store Validation Failed', ['error' => $validator->errors()->all()]);
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $vehicle = new Vehicle();
            $vehicle->car_brand_id = $request->car_brand_id;
            $vehicle->title = $request->title;
            $vehicle->car_model = $request->car_model;
            $vehicle->car_fuel_type = $request->car_fuel_type;
            $vehicle->year = $request->year;
            $vehicle->price_per_hour = $request->price_per_hour;
            $vehicle->price_per_day = $request->price_per_day;
            $vehicle->price_per_week = $request->price_per_week;
            $vehicle->price_per_month = $request->price_per_month;
            $vehicle->transmission = $request->transmission;
            $vehicle->color = $request->color;
            $vehicle->seats = $request->seats;
            $vehicle->address = $request->address;
            $vehicle->description = $request->description;
            $vehicle->is_featured = $request->is_featured ? '1' : '0';

            if ($request->hasFile('main_image')) {
                $Image = $request->file('main_image');
                $Image_ext = $Image->getClientOriginalExtension();
                $Image_name = time() . 'main_image.' . $Image_ext;

                $Image_path = 'uploads/vehicles';
                $Image->move(public_path($Image_path), $Image_name);
                $vehicle->main_image = $Image_path . "/" . $Image_name;
            }

            $vehicle->save();

            DB::commit();
            return redirect()->route('dashboard.chauffeur-vehicles.index')->with('success', 'Chauffeur Vehicle added Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('vehicle Store Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->withInput($request->all())->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $this->authorize('view chauffeur vehicle');
        try {
            $vehicle = Vehicle::with('carBrand')->findOrFail($id);
            return view('dashboard.chauffeurs.vehicles.show', compact('vehicle'));
        } catch (\Throwable $th) {
            Log::error('Vehicles Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->authorize('update chauffeur vehicle');
        try {
            $vehicle = Vehicle::findOrFail($id);
            $carBrands = CarBrand::where('is_active', 'active')->get();
            return view('dashboard.chauffeurs.vehicles.edit', compact('vehicle', 'carBrands'));
        } catch (\Throwable $th) {
            Log::error('Vehicles Edit Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update chauffeur vehicle');
        $validator = Validator::make($request->all(), [
            'car_brand_id' => 'required|exists:car_brands,id',
            'title' => 'required|string|max:255',
            'car_model' => 'required|string|max:255',
            'car_fuel_type' => 'required|string|max:255',
            'year' => 'required|string|max:255',
            'price_per_hour' => 'required|numeric',
            'price_per_day' => 'required|numeric',
            'price_per_week' => 'required|numeric',
            'price_per_month' => 'required|numeric',
            'transmission' => 'required|string|max:255',
            'color' => 'nullable|string|max:255',
            'seats' => 'required|integer',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_featured' => 'nullable|in:on',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg|max_size',
        ]);
        if ($validator->fails()) {
            Log::error('vehicle Update Validation Failed', ['error' => $validator->errors()->all()]);
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }
        try {
            DB::beginTransaction();
            $vehicle = Vehicle::findOrFail($id);
            $vehicle->car_brand_id = $request->car_brand_id;
            $vehicle->title = $request->title;
            $vehicle->car_model = $request->car_model;
            $vehicle->car_fuel_type = $request->car_fuel_type;
            $vehicle->year = $request->year;
            $vehicle->price_per_hour = $request->price_per_hour;
            $vehicle->price_per_day = $request->price_per_day;
            $vehicle->price_per_week = $request->price_per_week;
            $vehicle->price_per_month = $request->price_per_month;
            $vehicle->transmission = $request->transmission;
            $vehicle->color = $request->color;
            $vehicle->seats = $request->seats;
            $vehicle->address = $request->address;
            $vehicle->description = $request->description;
            $vehicle->is_featured = $request->is_featured ? '1' : '0';

            if ($request->hasFile('main_image')) {
                if (isset($vehicle->main_image) && File::exists(public_path($vehicle->main_image))) {
                    File::delete(public_path($vehicle->main_image));
                }
                $Image = $request->file('main_image');
                $Image_ext = $Image->getClientOriginalExtension();
                $Image_name = time() . 'main_image.' . $Image_ext;

                $Image_path = 'uploads/vehicles';
                $Image->move(public_path($Image_path), $Image_name);
                $vehicle->main_image = $Image_path . "/" . $Image_name;
            }

            $vehicle->save();

            DB::commit();
            return redirect()->route('dashboard.chauffeur-vehicles.index')->with('success', 'Chauffeur Vehicle Updated Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('vehicle Update Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->withInput($request->all())->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete chauffeur vehicle');
        try {
            $vehicle = Vehicle::findOrFail($id);
            if (isset($vehicle->main_image) && File::exists(public_path($vehicle->main_image))) {
                File::delete(public_path($vehicle->main_image));
            }
            $vehicle->delete();
            return redirect()->route('dashboard.chauffeur-vehicles.index')->with('success', 'Chauffeur Vehicle Deleted Successfully');
        } catch (\Throwable $th) {
            Log::error('vehicle Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(string $id)
    {
        $this->authorize('update chauffeur vehicle');
        try {
            $vehicle = Vehicle::findOrFail($id);
            $message = $vehicle->is_active == 'active' ? 'Vehicle Deactivated Successfully' : 'Vehicle Activated Successfully';
            if ($vehicle->is_active == 'active') {
                $vehicle->is_active = 'inactive';
                $vehicle->save();
            } else {
                $vehicle->is_active = 'active';
                $vehicle->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Vehicle Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
