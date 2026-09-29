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
