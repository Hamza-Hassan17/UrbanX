<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VehicleTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view vehicle type');
        try {
            $vehicleTypes = VehicleType::get();
            return view('dashboard.vehicle-types.index',compact('vehicleTypes'));
        } catch (\Throwable $th) {
            Log::error('Vehicle Type Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create vehicle type');
        try {
            return view('dashboard.vehicle-types.create');
        } catch (\Throwable $th) {
            Log::error('Vehicle Type Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create vehicle type');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'icon' => 'required|string|max:255',
            'base_fare' => 'nullable|numeric|min:0',
            'first_km_price' => 'nullable|numeric|min:0',
            'other_km_price' => 'nullable|numeric|min:0',
            'seats' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $vehicleType = new VehicleType();
            $vehicleType->name = $request->name;
            $vehicleType->icon = 'icons/'.$request->icon;
            $vehicleType->base_fare = $request->base_fare;
            $vehicleType->first_km_price = $request->first_km_price;
            $vehicleType->other_km_price = $request->other_km_price;
            $vehicleType->seats = $request->seats;
            $vehicleType->save();

            DB::commit();
            return redirect()->route('dashboard.vehicle-types.index')->with('success', 'Vehicle Type Created Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('vehicle type Created Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('update vehicle type');
        try {
            $vehicleType = VehicleType::findOrFail($id);
            return view('dashboard.vehicle-types.edit', compact('vehicleType'));
        } catch (\Throwable $th) {
            Log::error('vehicle type Edit Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update vehicle type');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'icon' => 'required|string|max:255',
            'base_fare' => 'nullable|numeric|min:0',
            'first_km_price' => 'nullable|numeric|min:0',
            'other_km_price' => 'nullable|numeric|min:0',
            'seats' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $vehicleType = VehicleType::findOrFail($id);
            $vehicleType->name = $request->name;
            $vehicleType->icon = 'icons/'.$request->icon;
            $vehicleType->base_fare = $request->base_fare;
            $vehicleType->first_km_price = $request->first_km_price;
            $vehicleType->other_km_price = $request->other_km_price;
            $vehicleType->seats = $request->seats;
            $vehicleType->save();

            DB::commit();
            return redirect()->route('dashboard.vehicle-types.index')->with('success', 'Vehicle Type Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('vehicle type Created Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete vehicle type');
        try {
            $vehicleType = VehicleType::findOrFail($id);
            $vehicleType->delete();
            return redirect()->back()->with('success', 'Vehicle Type Deleted Successfully!');
        } catch (\Throwable $th) {
            Log::error('vehicle type Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(string $id)
    {
        $this->authorize('update vehicle type');
        try {
            $vehicleType = VehicleType::findOrFail($id);
            $message = $vehicleType->is_active == 'active' ? 'Vehicle Type Deactivated Successfully' : 'Vehicle Type Activated Successfully';
            if ($vehicleType->is_active == 'active') {
                $vehicleType->is_active = 'inactive';
                $vehicleType->save();
            } else {
                $vehicleType->is_active = 'active';
                $vehicleType->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Vehicle Type Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
