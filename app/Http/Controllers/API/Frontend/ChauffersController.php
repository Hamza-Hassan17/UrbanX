<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ChauffersController extends Controller
{
    public function getHomeData(Request $request)
    {
        try {
            $user = $request->user();

            $featuredCarBrands = CarBrand::where('is_featured', '1')->where('is_active', 'active')->get();

            $featuredVehicles = Vehicle::where('is_active', 'active')->where('is_featured', '1')
                ->get();

            return response()->json([
                'featured_car_brands' => $featuredCarBrands,
                'featured_vehicles' => $featuredVehicles,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Chauffeurs Home failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
