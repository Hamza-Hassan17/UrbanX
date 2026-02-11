<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * User Login Attempt for API
     */
    public function login_attempt(Request $request)
    {
        // Validate the input
        $rules = [
            'phone' => 'required|max:50',
            'fcm_token' => 'nullable|string',
            'lat' => 'nullable|string|max:255',
            'lang' => 'nullable|string|max:255',
        ];

        // If Captcha is enabled, validate captcha response
        if (config('captcha.version') !== 'no_captcha') {
            $rules['g-recaptcha-response'] = 'required|captcha';
        } else {
            $rules['g-recaptcha-response'] = 'nullable';
        }

        // Validate the request
        $validate = Validator::make($request->all(), $rules);

        if ($validate->fails()) {
            return response()->json([
                'message' => 'Validation Error!',
                'errors' => $validate->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $userfind = User::where('phone', $request->phone)->first();

            if ($userfind) {
                $otp = '1234';
                $userfind->phone_otp = $otp;
                $userfind->otp_expires_at = Carbon::now()->addMinutes(10);
                $userfind->lat = $request->lat ?? null;
                $userfind->lang = $request->lang ?? null;
                $userfind->save();

                $token = $userfind->createToken($userfind->name, ['auth_token'])->plainTextToken;

                if($request->fcm_token){
                    // 🔥 Delete existing same FCM token (if assigned to another user)
                    UserDevice::where('fcm_token', $request->fcm_token)
                        ->where('user_id', '!=', $userfind->id)
                        ->delete();

                    UserDevice::updateOrCreate(
                        [
                            'user_id' => $userfind->id,
                        ],
                        [
                            'fcm_token' => $request->fcm_token,
                        ]
                    );
                }
                return response()->json([
                    'message' => 'Login successfully!',
                    'user' => $userfind->only(['id', 'name', 'email']),
                    'token' => $token
                ], Response::HTTP_OK);
            } else {
                return response()->json([
                    'message' => 'User not found.'
                ], Response::HTTP_UNAUTHORIZED);
            }
        } catch (\Throwable $th) {
            Log::error("Failed to Login: " . $th->getMessage());

            return response()->json([
                'message' => 'Something went wrong! Please try again later.',
                'error' => $th->getMessage(),  // <-- TEMPORARY
                'line' => $th->getLine(),      // <-- TEMPORARY
                'file' => $th->getFile(),      // <-- TEMPORARY
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verify_otp(Request $request)
    {
        Log::info($request);
        $request->validate([
            'otp' => 'required',
        ]);

        // ✅ Get user directly from authenticated token
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized user'], 401);
        }

        if ($user->phone_otp !== $request->otp) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP expired'], 400);
        }

        $user->phone_verified_at = Carbon::now();
        $user->phone_otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json(['message' => 'Phone verified successfully!'], 200);
    }

    public function resend_otp(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized user'], 401);
        }

        // Check if previous OTP is still valid
        if ($user->otp_expires_at && Carbon::now()->lessThan($user->otp_expires_at)) {
            return response()->json([
                'message' => 'OTP is still valid. Please use the existing one.',
                'expires_at' => $user->otp_expires_at->toDateTimeString()
            ], 400);
        }

        $otp = '1234';

        $user->phone_otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        return response()->json([
            'message' => 'New OTP has been sent successfully!',
            'expires_at' => $user->otp_expires_at->toDateTimeString()
        ], 200);
    }

}
