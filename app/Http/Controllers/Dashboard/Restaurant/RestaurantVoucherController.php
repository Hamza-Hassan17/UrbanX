<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\VoucherCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class RestaurantVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view restaurant voucher');
        try {
            $vouchers = VoucherCode::get();
            return view('dashboard.restaurant.vouchers.index',compact('vouchers'));
        } catch (\Throwable $th) {
            Log::error('Voucher Code Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create restaurant voucher');
        try {
            return view('dashboard.restaurant.vouchers.create');
        } catch (\Throwable $th) {
            Log::error('Voucher Code Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create restaurant voucher');
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:voucher_codes,code',
            'discount_amount' => 'required|numeric|min:0',
            'discount_type' => 'required|in:fixed,percentage',
            'minimum_purchase' => 'required|numeric|min:0',
            'per_user_limit' => 'required|integer|min:0',
            'expires_at' => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $voucher = new VoucherCode();
            $voucher->code = $request->code;
            $voucher->discount_amount = $request->discount_amount;
            $voucher->discount_type = $request->discount_type;
            $voucher->minimum_purchase = $request->minimum_purchase;
            $voucher->per_user_limit = $request->per_user_limit;
            $voucher->expires_at = $request->expires_at;
            $voucher->description = $request->description;
            $voucher->save();

            DB::commit();
            return redirect()->route('dashboard.restaurant-vouchers.index')->with('success', 'Voucher Created Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Voucher Created Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('update restaurant voucher');
        try {
            $voucher = VoucherCode::findOrFail($id);
            return view('dashboard.restaurant.vouchers.edit', compact('voucher'));
        } catch (\Throwable $th) {
            Log::error('Voucher Edit Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update restaurant voucher');
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:voucher_codes,code,'.$id,
            'discount_amount' => 'required|numeric|min:0',
            'discount_type' => 'required|in:fixed,percentage',
            'minimum_purchase' => 'required|numeric|min:0',
            'per_user_limit' => 'required|integer|min:0',
            'expires_at' => 'required|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $voucher = VoucherCode::findOrFail($id);
            $voucher->code = $request->code;
            $voucher->discount_amount = $request->discount_amount;
            $voucher->discount_type = $request->discount_type;
            $voucher->minimum_purchase = $request->minimum_purchase;
            $voucher->per_user_limit = $request->per_user_limit;
            $voucher->expires_at = $request->expires_at;
            $voucher->description = $request->description;
            $voucher->save();

            DB::commit();
            return redirect()->route('dashboard.restaurant-vouchers.index')->with('success', 'Voucher Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Voucher Created Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete restaurant voucher');
        try {
            $voucher = VoucherCode::findOrFail($id);
            $voucher->delete();
            return redirect()->back()->with('success', 'Voucher Deleted Successfully!');
        } catch (\Throwable $th) {
            Log::error('Voucher Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(string $id)
    {
        $this->authorize('update restaurant voucher');
        try {
            $voucher = VoucherCode::findOrFail($id);
            $message = $voucher->is_active == 'active' ? 'Voucher Deactivated Successfully' : 'Voucher Activated Successfully';
            if ($voucher->is_active == 'active') {
                $voucher->is_active = 'inactive';
                $voucher->save();
            } else {
                $voucher->is_active = 'active';
                $voucher->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Voucher Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
