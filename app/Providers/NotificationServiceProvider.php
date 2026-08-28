<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\UserDevice;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('notificationService', function ($app) {
            return new class($app->make(FirebaseService::class)) {
                public function __construct(protected FirebaseService $firebase)
                {
                }

                public function notifyUsers($users, $title, $message, $tableName = null, $tableId = null, $page = null)
                {
                    foreach ($users as $user) {
                        $notification = Notification::create([
                            'user_id' => $user->id,
                            'title' => $title,
                            'message' => $message,
                            'table_name' => $tableName,
                            'table_id' => $tableId,
                            'page' => $page,
                        ]);

                        // Get FCM token
                        $userDevice = UserDevice::where('user_id', $user->id)->first();
                        if (!$userDevice || !$userDevice->fcm_token) {
                            continue;
                        }

                        $data = array_filter([
                            'notification_id' => (string) $notification->id,
                            'table_name' => $tableName,
                            'table_id' => $tableId !== null ? (string) $tableId : null,
                            'page' => $page,
                        ]);

                        try {
                            $cloudMessage = CloudMessage::withTarget('token', $userDevice->fcm_token)
                                ->withNotification(FirebaseNotification::create($title, (string) $message))
                                ->withData($data);

                            $this->firebase->getMessaging()->send($cloudMessage);
                        } catch (\Throwable $th) {
                            Log::warning("FCM send failed for user {$user->id}: " . $th->getMessage());
                        }
                    }
                }
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
