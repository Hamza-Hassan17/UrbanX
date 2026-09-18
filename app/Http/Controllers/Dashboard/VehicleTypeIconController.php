<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use App\Models\VehicleTypeIcon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Manages the pool of icon files offered on the Add/Edit Vehicle Type
 * forms (previously a hardcoded array of 6 SVGs duplicated in both blade
 * views). Restricted to super-admin only via the 'manage vehicle type
 * icons' permission, which is never granted to the regular admin role --
 * admin can still pick from the pool when creating/editing a vehicle type,
 * just not add to or remove from it.
 */
class VehicleTypeIconController extends Controller
{
    public function index()
    {
        $this->authorize('manage vehicle type icons');
        try {
            $icons = VehicleTypeIcon::latest()->get();
            return view('dashboard.vehicle-types.icons.index', compact('icons'));
        } catch (\Throwable $th) {
            Log::error('Vehicle Type Icons Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function store(Request $request)
    {
        $this->authorize('manage vehicle type icons');
        $validator = Validator::make($request->all(), [
            'icon' => 'required|mimes:svg,png,jpg,jpeg|max_size',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $file = $request->file('icon');
            $ext = $file->getClientOriginalExtension();
            $name = time() . '-' . preg_replace('/[^A-Za-z0-9\-]/', '-', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $ext;

            $file->move(public_path('icons'), $name);

            VehicleTypeIcon::create([
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'path' => 'icons/' . $name,
            ]);

            return redirect()->back()->with('success', 'Icon uploaded successfully');
        } catch (\Throwable $th) {
            Log::error('Vehicle Type Icon Upload Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function destroy(string $id)
    {
        $this->authorize('manage vehicle type icons');
        try {
            $icon = VehicleTypeIcon::findOrFail($id);

            if (VehicleType::where('icon', $icon->path)->exists()) {
                return redirect()->back()->with('error', 'This icon is in use by one or more vehicle types and cannot be deleted.');
            }

            if (File::exists(public_path($icon->path))) {
                File::delete(public_path($icon->path));
            }

            $icon->delete();
            return redirect()->back()->with('success', 'Icon deleted successfully');
        } catch (\Throwable $th) {
            Log::error('Vehicle Type Icon Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }
}
