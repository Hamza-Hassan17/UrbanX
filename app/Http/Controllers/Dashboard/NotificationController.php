<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\NotificationEvent;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\User\UserController;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index()
    {
        try {
            $user = User::find(Auth::user()->id);
            $notifications = Notification::where('user_id', $user->id)->orderByRaw('read_at IS NULL DESC')
            ->orderBy('created_at', 'desc')
            ->get();
            $unreadNotificationsCount = Notification::where('user_id', $user->id)->where('read_at', null)->count();
            return view('dashboard.notifications.index', compact('notifications','unreadNotificationsCount'));
        } catch (\Throwable $th) {
            Log::error("Notification Index Failed:" . $th->getMessage());
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }

    public function getNotifications()
    {
        try {
            $user = User::find(Auth::user()->id);
            $notifications = Notification::where('user_id', $user->id)->orderByRaw('read_at IS NULL DESC')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
            $unreadNotificationsCount = Notification::where('user_id', $user->id)->where('read_at', null)->count();
            // $notifications = Notification::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'unreadNotificationsCount' => $unreadNotificationsCount
            ],200);
        } catch (\Throwable $th) {
            Log::error("Notification Index Failed:" . $th->getMessage());
            return response()->json([
                'success'=> false,
                'message'=> $th->getMessage()
            ],500);
        }
    }

    public function create()
    {
        $this->authorize('create notification');
        try {
            // "Specific Users" is searched via AJAX (searchUsers()) rather than
            // preloaded here -- with hundreds of customers/drivers/etc, loading
            // every active user into one <select> doesn't scale. Only re-fetch
            // the previously selected ones here, so they're preselected if a
            // validation error sends the admin back to this form.
            $selectedUsers = collect();
            if (old('audience') === 'specific' && old('user_ids')) {
                $selectedUsers = User::whereIn('id', old('user_ids'))->get();
            }

            return view('dashboard.notifications.create', compact('selectedUsers'));
        } catch (\Throwable $th) {
            Log::error('Notification Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * AJAX search backing the "Specific Users" select2 field -- excludes
     * admin-panel staff (super-admin/admin/dispatcher/finance), since this
     * picker is for targeting customers/drivers/restaurant owners/riders,
     * not other admins. Capped at 20 results per query.
     */
    public function searchUsers(Request $request)
    {
        $this->authorize('create notification');

        $term = (string) $request->query('q', '');

        $users = User::where('is_active', 'active')
            ->where('id', '!=', auth()->id())
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', UserController::ADMIN_PANEL_ROLES);
            })
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'results' => $users->map(fn ($user) => [
                'id' => $user->id,
                'text' => $user->email ? "{$user->name} ({$user->email})" : $user->name,
            ]),
        ]);
    }

    /**
     * Audience labels shown on the Send Notification form, mapped to their
     * actual Spatie role names -- "Delivery Riders" is the 'rider' role
     * (distinct from 'driver'), "Customers" is the 'user' role.
     */
    public const AUDIENCE_ROLES = [
        'customers' => 'user',
        'drivers' => 'driver',
        'restaurant_owners' => 'restaurant',
        'delivery_riders' => 'rider',
    ];

    public function store(Request $request)
    {
        $this->authorize('create notification');

        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'audience' => 'required|in:all,specific,roles',
                'user_ids' => 'required_if:audience,specific|array',
                'user_ids.*' => 'exists:users,id',
                'roles' => 'required_if:audience,roles|array',
                'roles.*' => 'in:' . implode(',', array_keys(self::AUDIENCE_ROLES)),
                'is_popup' => 'nullable|boolean',
            ], [
                'user_ids.required_if' => 'Please select at least one user.',
                'roles.required_if' => 'Please select at least one audience.',
            ]);

            if ($request->audience === 'all') {
                $users = User::where('id', '!=', auth()->id())->get();
            } elseif ($request->audience === 'roles') {
                $roleNames = array_map(fn ($key) => self::AUDIENCE_ROLES[$key], $request->roles);
                $users = User::role($roleNames)->where('id', '!=', auth()->id())->get();
            } else {
                $users = User::whereIn('id', $request->user_ids ?? [])->get();
            }

            app('notificationService')->notifyUsers(
                $users,
                $request->title,
                $request->message,
                null,
                null,
                null,
                $request->boolean('is_popup')
            );

            return redirect()
                ->route('dashboard.notifications.create')
                ->with('success', "Notification sent to {$users->count()} user(s)");
        } catch (\Throwable $th) {
            Log::error('Notification Send Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function markAsRead($id)
    {
        try {
            $user = User::find(Auth::user()->id);
            $notification = Notification::findOrFail($id);
            $notification->read_at = now();
            $notification->save();
            return response()->json([
                'success' => true,
                'status' => 'success'
            ],200);
        } catch (\Throwable $th) {
            Log::error("Notification Mark as Read Failed:" . $th->getMessage());
            return response()->json([
                'success'=> false,
                'message'=> $th->getMessage()
            ],500);
        }
    }

    public function markAllAsRead()
    {
        try {
            $user = User::where('id', Auth::user()->id);
            $notifications = Notification::where('user_id', Auth::user()->id)->whereNull('read_at')->get();
            foreach ($notifications as $notification) {
                $notification->read_at = now();
                $notification->save();
            }
            return response()->json([
                'success' => true,
                'status' => 'success'
            ],200);
        } catch (\Throwable $th) {
            Log::error("Notification Mark All as Read Failed:" . $th->getMessage());
            return response()->json([
                'success'=> false,
                'message'=> $th->getMessage()
            ],500);
        }
    }

    public function deleteAll()
    {
        try {
            $user = User::where('id', Auth::user()->id);
            $notifications = Notification::where('user_id', Auth::user()->id)->get();
            foreach ($notifications as $notification) {
                $notification->delete();
            }
            return response()->json([
                'success' => true,
                'status' => 'success'
            ],200);
        } catch (\Throwable $th) {
            Log::error("All Notification Delete Failed:" . $th->getMessage());
            return response()->json([
                'success'=> false,
                'message'=> $th->getMessage()
            ],500);
        }
    }

    public function testNotification($id)
    {
        try {
            $user = User::find($id);
            app('notificationService')->notifyUsers([$user], 'Test Notification by ' . Helper::getCompanyName(), null, null);
            return response()->json([
                'success' => true,
                'status' => 'success'
            ],200);
        } catch (\Throwable $th) {
            return response()->json([
                'success'=> false,
                'message'=> $th->getMessage()
            ],500);
        }
    }

    public function deleteNotification($id)
    {
        try {
            $notification = Notification::findOrFail($id);
            $notification->delete();
            return response()->json([
                'success' => true,
                'status' => 'success'
            ],200);
        } catch (\Throwable $th) {
            Log::error("Notification Deletion Failed:" . $th->getMessage());
            return response()->json([
                'success'=> false,
                'message'=> $th->getMessage()
            ],500);
        }
    }

    public function notificationClickHandle($id)
    {
        try {
            $notification = Notification::findOrFail($id);
            $notification->read_at = now();
            $notification->save();
            if ($notification->table_name == 'orders' && $notification->table_id != null) {
                return redirect()->route('dashboard.orders.show', $notification->table_id);
            }else if($notification->table_name == 'lead_follow_ups' && $notification->table_id != null){
                return redirect()->route('dashboard.follow-up.show', $notification->table_id);
            }else{
                return redirect()->back();
            }
        } catch (\Throwable $th) {
            Log::error("Notification Click Handle Failed:" . $th->getMessage());
            return response()->json([
                'success'=> false,
                'message'=> $th->getMessage()
            ],500);
        }
    }
}
