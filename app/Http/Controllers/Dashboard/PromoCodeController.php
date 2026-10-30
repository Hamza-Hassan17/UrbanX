<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PromoCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view promo code');
        try {
            $promoCodes = PromoCode::get();
            return view('dashboard.promo-codes.index',compact('promoCodes'));
        } catch (\Throwable $th) {
            Log::error('PromoCode Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create promo code');
        try {
            return view('dashboard.promo-codes.create');
        } catch (\Throwable $th) {
            Log::error('PromoCode Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create promo code');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $promoCode = new PromoCode();
            $promoCode->name = $request->name;
            $promoCode->code = $request->code;
            $promoCode->discount_percentage = $request->discount_percentage;
            $promoCode->valid_from = \Carbon\Carbon::parse($request->valid_from)->startOfDay();
            $promoCode->valid_until = \Carbon\Carbon::parse($request->valid_until)->endOfDay();
            $promoCode->usage_limit_per_user = $request->usage_limit_per_user ?? 1;
            $promoCode->usage_limit = $request->usage_limit ?? 1;
            $promoCode->save();

            DB::commit();
            return redirect()->route('dashboard.promo-codes.index')->with('success', 'Promo Code Created Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Promo Code Created Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('update promo code');
        try {
            $promoCode = PromoCode::findOrFail($id);
            return view('dashboard.promo-codes.edit', compact('promoCode'));
        } catch (\Throwable $th) {
            Log::error('Promo Code Edit Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update promo code');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:promo_codes,code,'.$id,
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $promocode = PromoCode::findOrFail($id);
            $promocode->name = $request->name;
            $promocode->code = $request->code;
            $promocode->discount_percentage = $request->discount_percentage;
            $promocode->valid_from = \Carbon\Carbon::parse($request->valid_from)->startOfDay();
            $promocode->valid_until = \Carbon\Carbon::parse($request->valid_until)->endOfDay();
            $promocode->usage_limit_per_user = $request->usage_limit_per_user ?? 1;
            $promocode->usage_limit = $request->usage_limit ?? 1;
            $promocode->save();

            DB::commit();
            return redirect()->route('dashboard.promo-codes.index')->with('success', 'Promo Code Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Promo Code Created Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete promo code');
        try {
            $promoCode = PromoCode::findOrFail($id);
            $promoCode->delete();
            return redirect()->back()->with('success', 'Promo Code Deleted Successfully!');
        } catch (\Throwable $th) {
            Log::error('Promo Code Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(string $id)
    {
        $this->authorize('update promo code');
        try {
            $promoCode = PromoCode::findOrFail($id);
            $message = $promoCode->is_active == 'active' ? 'Promo Code Deactivated Successfully' : 'Promo Code Activated Successfully';
            if ($promoCode->is_active == 'active') {
                $promoCode->is_active = 'inactive';
                $promoCode->save();
            } else {
                $promoCode->is_active = 'active';
                $promoCode->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Account Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
