<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Batch 1 Part 1 -- Pricing & Fees. Super-admin only ('manage pricing fees'
 * permission). Lives alongside Finance (Tax & Commission already covers
 * driver_commission_percent/sst_percent/sst_ride_fare_percent on the same
 * system_settings row) rather than duplicating those fields here.
 */
class PricingFeesController extends Controller
{
    public function index()
    {
        $this->authorize('manage pricing fees');

        $settings = SystemSetting::first() ?? new SystemSetting();

        $logs = AdminActivityLog::where('subject_type', SystemSetting::class)
            ->with('admin')
            ->latest('created_at')
            ->limit(50)
            ->get();

        // Taxi vehicle types that can be picked to carry parcels.
        $taxiVehicleTypes = \App\Models\VehicleType::where('is_delivery', '0')->where('is_active', 'active')->orderBy('name')->get(['id', 'name']);

        return view('dashboard.pricing-fees.index', compact('settings', 'logs', 'taxiVehicleTypes'));
    }

    public function update(Request $request)
    {
        $this->authorize('manage pricing fees');

        $validator = Validator::make($request->all(), [
            'food_first_km_fee' => 'required|numeric|min:0',
            'food_per_km_fee' => 'required|numeric|min:0',
            'food_max_distance_km' => 'required|integer|min:1',
            'parcel_first_km_fee' => 'required|numeric|min:0',
            'parcel_per_km_fee' => 'required|numeric|min:0',
            'parcel_max_distance_km' => 'required|integer|min:1',
            'restaurant_commission_percent' => 'required|numeric|min:0|max:100',
            'platform_share_amount' => 'required|numeric|min:0',
            'rider_cash_limit' => 'required|numeric|min:0',
            'sst_ride_fare_method' => 'required|in:inclusive,exclusive',
            // Batch 2 Part 1 -- Dispatch settings.
            'offer_acceptance_timeout_seconds' => 'required|integer|min:5',
            'max_reoffer_attempts' => 'required|integer|min:1',
            'stale_unassigned_minutes' => 'required|integer|min:1',
            'stale_restaurant_accept_minutes' => 'required|integer|min:1',
            'stale_ready_for_pickup_minutes' => 'required|integer|min:1',
            'taxi_outstanding_limit' => 'required|numeric|min:0',
            'auto_offline_minutes' => 'required|integer|min:1|max:1440',
            'no_driver_handling' => 'required|in:0,1',
            'parcel_vehicle_type_id' => ['nullable', \Illuminate\Validation\Rule::exists('vehicle_types', 'id')->where('is_delivery', '0')],
            'allow_legacy_direct_accept' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        try {
            $setting = SystemSetting::first() ?? new SystemSetting();
            $fields = $validator->validated();
            // A checkbox omits itself from the request entirely when
            // unchecked, so validated() would never carry a "false" --
            // read it explicitly instead of relying on the field being present.
            $fields['allow_legacy_direct_accept'] = $request->boolean('allow_legacy_direct_accept');
            $fields['no_driver_handling'] = $request->input('no_driver_handling') === '1';
            $fields['parcel_vehicle_type_id'] = $request->input('parcel_vehicle_type_id') ?: null;

            $old = $setting->only(array_keys($fields));

            $setting->fill($fields);
            $setting->save();

            AdminActivityLog::record(
                'Updated Pricing & Fees settings',
                $old,
                $fields,
                SystemSetting::class,
                $setting->id
            );

            return redirect()->back()->with('success', 'Pricing & Fees settings updated successfully');
        } catch (\Throwable $th) {
            Log::error('Pricing & Fees settings update failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }
}
