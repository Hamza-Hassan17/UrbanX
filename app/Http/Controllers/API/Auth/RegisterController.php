<?php

namespace App\Http\Controllers\Api\Auth;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    protected SmsService $sms;

    public function __construct(SmsService $sms)
    {
        $this->sms = $sms;
    }

    public function register_attempt(Request $request)
    {
        $rules = [
            'email' => 'required|string|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'role' => 'required|in:user,driver,restaurant,rider',
            'lat' => 'nullable|string|max:255',
            'lang' => 'nullable|string|max:255',
        ];

        if (config('captcha.version') !== 'no_captcha') {
            $rules['g-recaptcha-response'] = 'required|captcha';
        } else {
            $rules['g-recaptcha-response'] = 'nullable';
        }

        $validate = Validator::make($request->all(), $rules);
        if ($validate->fails()) {
            return response()->json([
                'message' => 'Validation Error!',
                'errors' => $validate->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            $dummyPassword = '12345678';

            $user = new User();

            // 📧 Extract name from email
            $email = $request->email;
            $namePart = strstr($email, '@', true);
            $namePart = str_replace(['.', '_', '-'], ' ', $namePart);
            $namePart = ucwords($namePart);

            $user->name = $namePart;
            $user->email = $email;
            $user->phone = $request->phone;
            $user->email_verified_at = Carbon::now();
            $user->password = Hash::make($dummyPassword);
            $user->lat = $request->lat ?? null;
            $user->lang = $request->lang ?? null;

            // ✅ Generate username from email name part
            $username = $this->generateUsername($namePart);
            while (User::where('username', $username)->exists()) {
                $username = $this->generateUsername($namePart);
            }
            $user->username = $username;
            $user->save();

            $user->syncRoles($request->role);

            $profile = new Profile();
            $profile->user_id = $user->id;
            $profile->first_name = $user->name;
            $profile->phone_number = $request->phone;
            $profile->save();

            // Temporarily hardcoded while Vonage SMS delivery is being set up.
            // See SmsService::sendOtp() to re-enable real OTP sending.
            $otp = '1234';
            $user->phone_otp = $otp;
            $user->otp_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            $token = $user->createToken($user->name, ['auth_token'])->plainTextToken;

            Auth::attempt(['email' => $request->email, 'password' => $dummyPassword]);

            // if (Auth::check()) {
            //     VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            //         return (new MailMessage)
            //             ->subject('Verify Email Address')
            //             ->line('Click the button below to verify your email address.')
            //             ->action('Verify Email Address', $url);
            //     });
            // }

            app('notificationService')->notifyUsers([$user], 'Welcome to ' . Helper::getCompanyName(), 'Start exploring today and enjoy your journey');
            // $user->sendEmailVerificationNotification();

            DB::commit();

            return response()->json([
                'message' => 'Your account has been created successfully.',
                'user' => $user->only(['id', 'name', 'email', 'username']),
                'token' => $token,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            DB::rollback();
            Log::error('User registration failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong! Please try again later',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function generateUsername($name)
    {
        $name = strtolower(str_replace(' ', '', $name));
        return $name . rand(1000, 9999);
    }
}
