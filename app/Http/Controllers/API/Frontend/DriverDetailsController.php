<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Models\DriverCnic;
use App\Models\DriverLicense;
use App\Models\DriverVehicle;
use App\Models\Profile;
use App\Models\VehicleType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DriverDetailsController extends Controller
{
    public function getVehicleDetails(Request $request)
    {
        try {
            $user = $request->user();

            $driverVehicle = DriverVehicle::with('vehicleType')->where('driver_id', $user->id)->first();

            // Get only id and name of active vehicle types
            $vehicleTypes = VehicleType::where('is_active', 'active')
                ->select('id', 'name')
                ->get();

            $data = null;

            if ($driverVehicle) {
                $images = [];

                if (!empty($driverVehicle->vehicle_images)) {
                    $decodedImages = json_decode($driverVehicle->vehicle_images, true);

                    if (is_array($decodedImages)) {
                        foreach ($decodedImages as $img) {
                            // Correct way for storage images
                            $images[] = Storage::url($img);
                        }
                    }
                }

                // Prepare response data
                $data = [
                    'vehicle_type' => $driverVehicle->vehicleType->name,
                    'vehicle_type_id' => $driverVehicle->vehicle_type_id,
                    'vehicle_name' => $driverVehicle->vehicle_name,
                    'vehicle_make' => $driverVehicle->vehicle_make,
                    'vehicle_model' => $driverVehicle->vehicle_model,
                    'vehicle_color' => $driverVehicle->vehicle_color,
                    'vehicle_year' => $driverVehicle->vehicle_year,
                    'vehicle_plate_number' => $driverVehicle->vehicle_plate_number,
                    'vehicle_images' => $images,
                ];
            }


            return response()->json([
                'vehicle' => $data,
                'vehicle_types' => $vehicleTypes,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Vehicle Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateVehicleDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'vehicle_name' => 'required|string',
            'vehicle_make' => 'nullable|string',
            'vehicle_model' => 'nullable|string',
            'vehicle_color' => 'nullable|string',
            'vehicle_year' => 'nullable|string',
            'vehicle_plate_number' => 'nullable|string',
            'vehicle_images' => 'nullable|array',
            'vehicle_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max_size',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $driverVehicle = DriverVehicle::where('driver_id', $user->id)->first();

            if (!$driverVehicle) {
                $driverVehicle = new DriverVehicle();
                $driverVehicle->driver_id = $user->id;
            }

            $driverVehicle->vehicle_type_id = $request->input('vehicle_type_id');
            $driverVehicle->vehicle_name = $request->input('vehicle_name');
            $driverVehicle->vehicle_make = $request->input('vehicle_make');
            $driverVehicle->vehicle_model = $request->input('vehicle_model');
            $driverVehicle->vehicle_color = $request->input('vehicle_color');
            $driverVehicle->vehicle_year = $request->input('vehicle_year');
            $driverVehicle->vehicle_plate_number = $request->input('vehicle_plate_number');

            // Keep old images if exist
            $images = [];
            if (!empty($driverVehicle->vehicle_images)) {
                $images = json_decode($driverVehicle->vehicle_images, true);
            }

            // Upload and append new images
            if ($request->hasFile('vehicle_images')) {
                foreach ($request->file('vehicle_images') as $image) {
                    $path = $image->store('uploads/vehicle-images', 'public');
                    $images[] = $path;
                }
            }

            $driverVehicle->vehicle_images = json_encode($images);
            $driverVehicle->save();

            return response()->json([
                'message' => 'Vehicle details updated successfully',
                'vehicle' => [
                    'vehicle_type' => $driverVehicle->vehicleType->name,
                    'vehicle_type_id' => $driverVehicle->vehicle_type_id,
                    'vehicle_name' => $driverVehicle->vehicle_name,
                    'vehicle_make' => $driverVehicle->vehicle_make,
                    'vehicle_model' => $driverVehicle->vehicle_model,
                    'vehicle_color' => $driverVehicle->vehicle_color,
                    'vehicle_year' => $driverVehicle->vehicle_year,
                    'vehicle_plate_number' => $driverVehicle->vehicle_plate_number,
                    'vehicle_images' => $images,
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Vehicle Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getLicenseDetails(Request $request)
    {
        try {
            $user = $request->user();

            $driverLicense = DriverLicense::where('driver_id', $user->id)->first();

            $data = null;

            if ($driverLicense) {
                // Prepare response data
                $data = [
                    'name' => $driverLicense->name,
                    'license_number' => $driverLicense->license_number,
                    'address' => $driverLicense->address,
                    'front_picture' => Storage::url($driverLicense->front_picture),
                    'back_picture' => Storage::url($driverLicense->back_picture),
                ];
            }

            return response()->json([
                'license' => $data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API License Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateLicenseDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'license_number' => 'required|string',
            'address' => 'required|string',
            'front_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max_size',
            'back_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max_size',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $driverLicense = DriverLicense::where('driver_id', $user->id)->first();

            if (!$driverLicense) {
                $driverLicense = new DriverLicense();
                $driverLicense->driver_id = $user->id;
            }

            $driverLicense->name = $request->input('name');
            $driverLicense->license_number = $request->input('license_number');
            $driverLicense->address = $request->input('address');

            if ($request->hasFile('front_picture')) {
                $path = $request->file('front_picture')->store('uploads/license-images', 'public');
                $driverLicense->front_picture = $path;
            }

            if ($request->hasFile('back_picture')) {
                $path = $request->file('back_picture')->store('uploads/license-images', 'public');
                $driverLicense->back_picture = $path;
            }

            $driverLicense->save();

            return response()->json([
                'message' => 'License details updated successfully',
                'license' => [
                    'name' => $driverLicense->name,
                    'license_number' => $driverLicense->license_number,
                    'address' => $driverLicense->address,
                    'front_picture' => url($driverLicense->front_picture),
                    'back_picture' => url($driverLicense->back_picture),
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update License Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getPersonalInformation(Request $request)
    {
        try {
            $user = $request->user();
            $profile = Profile::where('user_id', $user->id)->first();

            $data = null;
            if ($profile) {
                // Prepare response data
                $data = [
                    'first_name' => $profile->first_name,
                    'last_name' => $profile->last_name,
                    'dob' => $profile->dob,
                    'gender' => $profile->gender,
                    'profile_image' => url($profile->profile_image),
                ];
            }

            return response()->json([
                'personal_information' => $data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Personal Information failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updatePersonalInformation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max_size',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $profile = Profile::where('user_id', $user->id)->first();

            if (!$profile) {
                $profile = new Profile();
                $profile->user_id = $user->id;
            }

            $profile->first_name = $request->input('first_name');
            $profile->last_name = $request->input('last_name');
            $profile->dob = $request->input('dob') ? date('Y-m-d', strtotime($request->input('dob'))) : null;
            $profile->gender = $request->input('gender');

            if ($request->hasFile('profile_image')) {
                if (isset($profile->profile_image) && File::exists(public_path($profile->profile_image))) {
                    File::delete(public_path($profile->profile_image));
                }

                $profileImage = $request->file('profile_image');
                $profileImage_ext = $profileImage->getClientOriginalExtension();
                $profileImage_name = time() . '_profileImage.' . $profileImage_ext;

                $profileImage_path = 'uploads/profile-images';
                $profileImage->move(public_path($profileImage_path), $profileImage_name);
                $profile->profile_image = $profileImage_path . "/" . $profileImage_name;
            }

            $profile->save();

            $user = User::where('id', $user->id)->first();
            $user->name = $request->input('first_name') . ' ' . $request->input('last_name');
            $user->save();

            return response()->json([
                'message' => 'Personal information updated successfully',
                'personal_information' => [
                    'first_name' => $profile->first_name,
                    'last_name' => $profile->last_name,
                    'dob' => $profile->dob,
                    'gender' => $profile->gender,
                    'profile_image' => url($profile->profile_image),
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Personal Information failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getCNICDetails(Request $request)
    {
        try {
            $user = $request->user();

            $driverCNIC = DriverCnic::where('driver_id', $user->id)->first();

            $data = null;

            if ($driverCNIC) {
                // Prepare response data
                $data = [
                    'name' => $driverCNIC->name,
                    'cnic_number' => $driverCNIC->cnic_number,
                    'issue_date' => $driverCNIC->issue_date,
                    'front_picture' => Storage::url($driverCNIC->front_picture),
                    'back_picture' => Storage::url($driverCNIC->back_picture),
                ];
            }

            return response()->json([
                'cnic' => $data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API CNIC Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateCNICDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'cnic_number' => 'required|string',
            'issue_date' => 'required|string',
            'front_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max_size',
            'back_picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max_size',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $driverCNIC = DriverCnic::where('driver_id', $user->id)->first();

            if (!$driverCNIC) {
                $driverCNIC = new DriverCnic();
                $driverCNIC->driver_id = $user->id;
            }

            $driverCNIC->name = $request->input('name');
            $driverCNIC->cnic_number = $request->input('cnic_number');
            $driverCNIC->issue_date = $request->input('issue_date') ? date('Y-m-d', strtotime($request->input('issue_date'))) : null;

            if ($request->hasFile('front_picture')) {
                $path = $request->file('front_picture')->store('uploads/cnic-images', 'public');
                $driverCNIC->front_picture = $path;
            }

            if ($request->hasFile('back_picture')) {
                $path = $request->file('back_picture')->store('uploads/cnic-images', 'public');
                $driverCNIC->back_picture = $path;
            }

            $driverCNIC->save();

            return response()->json([
                'message' => 'CNIC details updated successfully',
                'cnic' => [
                    'name' => $driverCNIC->name,
                    'cnic_number' => $driverCNIC->cnic_number,
                    'issue_date' => $driverCNIC->issue_date,
                    'front_picture' => url($driverCNIC->front_picture),
                    'back_picture' => url($driverCNIC->back_picture),
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update CNIC Details failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateDriverStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_status' => 'required|in:busy,available',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();
            $user->driver_status = $request->driver_status;
            $user->save();

            return response()->json([
                'message' => 'Driver status updated successfully.',
                'driver_status' => $user->driver_status,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Driver Status failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
