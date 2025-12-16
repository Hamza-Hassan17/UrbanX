<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function getUserProfile(Request $request)
    {
        try {
            $user = $request->user();
            $profile = $user->profile;
            $profileData = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'date_of_birth' => $profile->dob,
                'profile_image' => url($profile->profile_image),
                'gender' => $profile->gender,
            ];

            return response()->json([
                'profile' => $profileData,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Profile Data failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateUserProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // 'email' => 'required|email|max:255|unique:users,email,' . $request->user()->id,
            // 'phone' => 'required|string|max:20|unique:users,phone,' . $request->user()->id,
            'date_of_birth' => 'nullable|date',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max_size',
            'gender' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();
            $user->name = $request->input('name');
            // $user->email = $request->input('email');
            // $user->phone = $request->input('phone');
            $user->save();

            $profile = $user->profile;

            if (!$profile) {
                $profile = new Profile();
                $profile->user_id = $user->id;
            }
            $profile->dob = $request->input('date_of_birth');
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


            return response()->json([
                'message' => 'Profile updated successfully',
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'date_of_birth' => $profile->dob,
                    'profile_image' => url($profile->profile_image),
                    'gender' => $profile->gender,
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Profile failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateUserPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            if (!password_verify($request->input('current_password'), $user->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $user->password = Hash::make($request->input('new_password'));
            $user->save();

            return response()->json([
                'message' => 'Password updated successfully'
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Update Password failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
