<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomRideController extends Controller
{
    public function index()
    {
        $this->authorize('view custom rides');
        try {
            return view('dashboard.custom-rides.index');
        } catch (\Throwable $th) {
            Log::error('Custom Rides Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
