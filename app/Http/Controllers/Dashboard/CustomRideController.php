<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\User;
use App\Models\RideOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

class CustomRideController extends Controller
{
    public function index()
    {
        $this->authorize('view custom rides');
        try {
            $drivers = User::with('driverVehicle')
            ->role('driver')
            ->whereHas('driverVehicle')
            ->get()
            ->map(function ($driver) {
                return [
                    'id'     => $driver->id,
                    'name'   => $driver->name,
                    'phone'   => $driver->phone,
                    'lat'    => $driver->lat ? (float) $driver->lat : null,
                    'lng'    => $driver->lang ? (float) $driver->lang : null,
                    'status' => $driver->driver_status, // busy | available
                    'icon'   => $driver->driver_status === 'available'
                                    ? 'taxiIcon'
                                    : 'taxiIconBusy',
                    'vehicle'  => $driver->driverVehicle ? $driver->driverVehicle->vehicle_name.' '.$driver->driverVehicle->vehicle_make.' '.$driver->driverVehicle->vehicle_year : 'N/A',
                    'vehicle_type_id' => $driver->driverVehicle ? $driver->driverVehicle->vehicle_type_id : null,
                    'city'   => 'Karachi',
                ];
            });

            $driver = User::role('driver')
                ->where('driver_status', 'available') // only available drivers
                ->whereHas('driverVehicle')          // must have a vehicle
                ->inRandomOrder()                    // randomize
                ->with('driverVehicle')              // eager load vehicle info
                ->first();

            $rides = Ride::with(['driver'])
                ->latest()
                ->take(4)
                ->get();

            $activeRidesCount = Ride::whereIn('status', ['requested', 'accepted', 'en_route', 'arrived', 'started'])->count();
            return view('dashboard.custom-rides.index', compact('drivers', 'rides', 'activeRidesCount', 'driver'));
        } catch (\Throwable $th) {
            Log::error('Custom Rides Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }



    public function requestCustomRide(Request $request)
    {
        Log::info('Request Custom Ride', ['request' => $request->all()]);
        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'driver_id' => 'required|exists:users,id',
            'driver_id_input' => 'nullable|exists:users,id',
            'promo_code_id' => 'nullable|exists:promo_codes,id',
            'pickup_latitude' => 'required|string',
            'pickup_longitude' => 'required|string',
            'dropoff_latitude' => 'required|string',
            'dropoff_longitude' => 'required|string',
            'distance_km' => 'required|string',
            'duration_minutes' => 'nullable|string',
            'subtotal' => 'required|string',
            'discount_amount' => 'required|string',
            'total_fare' => 'required|string',
            'passenger_name' => 'required|string|max:255',
            'passenger_phone' => 'required|string|max:255',
            'passenger_email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Request Custom Ride Validation Failed', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
        try {
            DB::beginTransaction();
            $driver = User::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'message' => 'Selected driver is not valid.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check existing user by phone or email
            $existingUser = User::where('phone', $request->passenger_phone)
                ->orWhere('email', $request->passenger_email)
                ->first();

            if ($existingUser) {

                // Phone belongs to someone else
                if (
                    $existingUser->phone === $request->passenger_phone &&
                    $existingUser->email !== $request->passenger_email &&
                    User::where('email', $request->passenger_email)->exists()
                ) {
                    return response()->json([
                        'message' => 'This email is already registered with another phone number.'
                    ], Response::HTTP_BAD_REQUEST);
                }

                // Email belongs to someone else
                if (
                    $existingUser->email === $request->passenger_email &&
                    $existingUser->phone !== $request->passenger_phone &&
                    User::where('phone', $request->passenger_phone)->exists()
                ) {
                    return response()->json([
                        'message' => 'This phone number is already registered with another email.'
                    ], Response::HTTP_BAD_REQUEST);
                }

                // Same user (phone or email matched)
                $passenger = $existingUser;

            } else {

                $passenger = new User();
                $passenger->name = $request->passenger_name;
                $passenger->phone = $request->passenger_phone;
                $passenger->email = $request->passenger_email;
                $passenger->password = Hash::make($request->passenger_phone);

                // Generate unique username
                $username = $this->generateUsername($request->passenger_name);
                while (User::where('username', $username)->exists()) {
                    $username = $this->generateUsername($request->passenger_name);
                }

                $passenger->username = $username;
                $passenger->save();
            }

            $passenger->syncRoles('user');

            if($request->driver_id_input){
                $ride = new Ride();
                $ride->passenger_id = $passenger->id;
                $ride->driver_id = $request->driver_id_input;
                $ride->vehicle_type_id = $request->vehicle_type_id;
                $ride->promo_code_id = $request->promo_code_id;
                $ride->pickup_latitude = $request->pickup_latitude;
                $ride->pickup_longitude = $request->pickup_longitude;
                $ride->dropoff_latitude = $request->dropoff_latitude;
                $ride->dropoff_longitude = $request->dropoff_longitude;
                $ride->distance_km = $request->distance_km;
                $ride->duration_minutes = $request->duration_minutes;
                $ride->subtotal = $request->subtotal;
                $ride->discount_amount = $request->discount_amount;
                $ride->total_fare = $request->total_fare;
                $ride->requested_at = now();
                $ride->accepted_at = now();
                $ride->status = 'accepted';
                $ride->save();

                $rideOffer = new RideOffer();
                $rideOffer->ride_id = $ride->id;
                $rideOffer->driver_id = $request->driver_id_input;
                $rideOffer->proposed_price = $request->total_fare;
                $rideOffer->eta_minutes = $request->duration_minutes;
                $rideOffer->note = 'Auto Accepted by Admin';
                $rideOffer->offered_at = now();
                $rideOffer->accepted_at = now();
                $rideOffer->status = 'accepted';
                $rideOffer->save();
            }else {
                $ride = new Ride();
                $ride->passenger_id = $passenger->id;
                $ride->driver_id = null; // No driver assigned yet
                $ride->vehicle_type_id = $request->vehicle_type_id;
                $ride->promo_code_id = $request->promo_code_id;
                $ride->pickup_latitude = $request->pickup_latitude;
                $ride->pickup_longitude = $request->pickup_longitude;
                $ride->dropoff_latitude = $request->dropoff_latitude;
                $ride->dropoff_longitude = $request->dropoff_longitude;
                $ride->distance_km = $request->distance_km;
                $ride->duration_minutes = $request->duration_minutes;
                $ride->subtotal = $request->subtotal;
                $ride->discount_amount = $request->discount_amount;
                $ride->total_fare = $request->total_fare;
                $ride->requested_at = now();
                $ride->status = 'requested';
                $ride->save();
            }


            DB::commit();
            return response()->json([
                'ride_id' => $ride->id,
                'message' => 'Custom Ride requested successfully!',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('API Store Custom Ride failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    private function generateUsername($name)
    {
        $name = strtolower(str_replace(' ', '', $name));
        return $name . rand(1000, 9999);
    }
}
