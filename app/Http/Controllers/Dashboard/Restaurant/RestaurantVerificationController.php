<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Batch 2 Part 4 -- restaurant registration & approval. Same
 * 'view restaurant' / 'update restaurant' permissions the rest of the
 * Restaurants area already uses (no new permission invented, matching
 * how driver verification reuses 'view driver'/'update driver').
 */
class RestaurantVerificationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view restaurant');

        $status = $request->get('status', 'submitted');

        $verifications = RestaurantVerification::with('restaurant', 'reviewer')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('submitted_at')
            ->paginate(30);

        return view('dashboard.restaurant.verifications.index', [
            'verifications' => $verifications,
            'status' => $status,
            'pageTitle' => __('Restaurant Verifications'),
        ]);
    }

    public function show(RestaurantVerification $verification)
    {
        $this->authorize('view restaurant');

        return view('dashboard.restaurant.verifications.show', [
            'verification' => $verification->load('restaurant', 'reviewer'),
        ]);
    }

    public function approve(RestaurantVerification $verification)
    {
        $this->authorize('update restaurant');

        try {
            $old = $verification->only(['status', 'rejection_reason']);

            $verification->status = 'approved';
            $verification->rejection_reason = null;
            $verification->reviewed_by = auth()->id();
            $verification->reviewed_at = now();
            $verification->save();

            $restaurant = $verification->restaurant;
            $restaurant->is_approved = true;
            $restaurant->save();

            AdminActivityLog::record(
                'Approved restaurant verification',
                $old,
                ['status' => 'approved'],
                RestaurantVerification::class,
                $verification->id
            );

            $this->notifyOwner($restaurant, 'Restaurant Approved', 'Your restaurant has been approved and is now live to customers.');

            return redirect()->back()->with('success', 'Restaurant verification approved.');
        } catch (\Throwable $th) {
            Log::error('Restaurant Verification Approve Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function reject(Request $request, RestaurantVerification $verification)
    {
        $this->authorize('update restaurant');

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $old = $verification->only(['status', 'rejection_reason']);

            $verification->status = 'rejected';
            $verification->rejection_reason = $request->rejection_reason;
            $verification->reviewed_by = auth()->id();
            $verification->reviewed_at = now();
            $verification->save();

            $restaurant = $verification->restaurant;
            $restaurant->is_approved = false;
            $restaurant->save();

            AdminActivityLog::record(
                'Rejected restaurant verification',
                $old,
                ['status' => 'rejected', 'rejection_reason' => $request->rejection_reason],
                RestaurantVerification::class,
                $verification->id
            );

            $this->notifyOwner($restaurant, 'Restaurant Verification Rejected', "Your restaurant verification was rejected: {$request->rejection_reason}. Please resubmit your documents.");

            return redirect()->back()->with('success', 'Restaurant verification rejected.');
        } catch (\Throwable $th) {
            Log::error('Restaurant Verification Reject Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    private function notifyOwner(Restaurant $restaurant, string $title, string $message): void
    {
        if (!$restaurant->user) {
            return;
        }

        try {
            app('notificationService')->notifyUsers(
                [$restaurant->user],
                $title,
                $message,
                'restaurant_verifications',
                $restaurant->id,
                'verification_status'
            );
        } catch (\Throwable $e) {
            Log::error('Restaurant verification decision notification failed', ['restaurant_id' => $restaurant->id, 'error' => $e->getMessage()]);
        }
    }
}
