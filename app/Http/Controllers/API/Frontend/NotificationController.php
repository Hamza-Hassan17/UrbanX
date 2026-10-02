<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function getUserNotifications(Request $request)
    {
        try {
            $user = $request->user();

            // Fetch counts in fewer queries
            $notifications = Notification::where('user_id', $user->id)->latest()->get();

            // Popup notifications (e.g. "Ride Cancelled") are meant to be
            // shown once -- mark them read the moment they're delivered to
            // the app so the next fetch won't surface the same popup again.
            // read_at is still null on the objects below, so this fetch's
            // response correctly tells the app to show it this one time.
            $popupIdsToAcknowledge = $notifications
                ->where('is_popup', true)
                ->whereNull('read_at')
                ->pluck('id');

            if ($popupIdsToAcknowledge->isNotEmpty()) {
                Notification::whereIn('id', $popupIdsToAcknowledge)->update(['read_at' => now()]);
            }

            $data = $notifications->map(function ($notification) use ($user) {
                return [
                    'user_id' => $notification->user_id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'created_at' => Carbon::parse($notification->created_at)->diffForHumans(),
                    'read_at' => $notification->read_at,
                    'page' => $notification->page,
                    'is_popup' => (bool) $notification->is_popup,
                ];
            });

            return response()->json([
                'notifications' => $data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Notifications failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
