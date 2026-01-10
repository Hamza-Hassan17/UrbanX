<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BoostHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BoostPriceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view boost hour');
        try {
            $boostHours = BoostHour::get();
            return view('dashboard.boost-hours.index',compact('boostHours'));
        } catch (\Throwable $th) {
            Log::error('Boost Hour Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create boost hour');
        try {
            return view('dashboard.boost-hours.create');
        } catch (\Throwable $th) {
            Log::error('Boost Hour Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create boost hour');
        $validator = Validator::make($request->all(), [
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
            'multiplier' => ['required', 'numeric', 'min:1'],
        ], [
            'start.date_format' => 'Start time must be in HH:MM format.',
            'end.date_format' => 'End time must be in HH:MM format.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $boostHours = new BoostHour();
            $boostHours->start = $request->start;
            $boostHours->end = $request->end;
            $boostHours->multiplier = $request->multiplier;
            $boostHours->save();

            DB::commit();
            return redirect()->route('dashboard.boost-hours.index')->with('success', 'Boost Hour Created Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Boost Hour Created Failed', ['error' => $th->getMessage()]);
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
        $this->authorize('update boost hour');
        try {
            $boostHour = BoostHour::findOrFail($id);
            return view('dashboard.boost-hours.edit', compact('boostHour'));
        } catch (\Throwable $th) {
            Log::error('Boost Hour Edit Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update boost hour');
        $validator = Validator::make($request->all(), [
            'start' => 'required|time',
            'end' => 'required|time|after:start',
            'multiplier' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $boostHour = BoostHour::findOrFail($id);
            $boostHour->start = $request->start;
            $boostHour->end = $request->end;
            $boostHour->multiplier = $request->multiplier;
            $boostHour->save();

            DB::commit();
            return redirect()->route('dashboard.boost-hours.index')->with('success', 'Boost Hour Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Boost Hour Updated Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete boost hour');
        try {
            $boostHour = BoostHour::findOrFail($id);
            $boostHour->delete();
            return redirect()->back()->with('success', 'Boost Hour Deleted Successfully!');
        } catch (\Throwable $th) {
            Log::error('Boost Hour Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
