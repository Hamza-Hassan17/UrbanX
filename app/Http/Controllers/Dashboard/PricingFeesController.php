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

        return view('dashboard.pricing-fees.index', compact('settings', 'logs'));
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
            'platform_share_percent' => 'required|numeric|min:0|max:100',
            'rider_cash_limit' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        try {
            $setting = SystemSetting::first() ?? new SystemSetting();
            $fields = $validator->validated();

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
