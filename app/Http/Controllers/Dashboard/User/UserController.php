<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Http\Controllers\Controller;
use App\Mail\UserCredentialMail;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Roles that count as "admin panel users" -- internal staff who operate
     * the dashboard, as opposed to customers/drivers/riders/restaurants who
     * only use the mobile apps. Add 'manager', 'operator', etc. here the
     * moment those roles are created; only super-admin/admin exist today.
     * Kept in sync with ReportController::ADMIN_PANEL_ROLES.
     */
    public const ADMIN_PANEL_ROLES = ['super-admin', 'admin'];

    /**
     * Display a listing of the resource.
     * This is the platform's end users only (customers, drivers, riders,
     * restaurants) -- internal staff accounts live on adminUsers() instead,
     * so the two don't get confused together in one list.
     */
    public function index()
    {
        $this->authorize('view user');
        try {
            $users  = User::with('profile')->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', self::ADMIN_PANEL_ROLES);
            })->get();
            $totalUsers = $users->count();
            $totalDeactivatedUsers = $users->where('is_active', 'inactive')->count();
            $totalActiveUsers = $users->where('is_active', 'active')->count();
            $totalUnverifiedUsers = $users->whereNull('email_verified_at')->count();
            $totalArchivedUsers = User::onlyTrashed()->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', self::ADMIN_PANEL_ROLES);
            })->count();
            $roles = Role::whereNotIn('name', self::ADMIN_PANEL_ROLES)->get();
            return view('dashboard.users.index', compact('users', 'totalUsers', 'totalDeactivatedUsers', 'totalActiveUsers', 'totalUnverifiedUsers', 'roles', 'totalArchivedUsers'));
        } catch (\Throwable $th) {
            // Handle the exception
            // throw $th;
            Log::error("User Index Failed:" . $th->getMessage());
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Display a listing of admin panel staff accounts only (super admin,
     * admin, and any manager/operator roles added later) -- kept separate
     * from index() so a customer/driver list of potentially millions never
     * gets mixed in with the handful of internal dashboard accounts.
     */
    public function adminUsers()
    {
        $this->authorize('view user');
        try {
            $users = User::with('profile')->whereHas('roles', function ($q) {
                $q->whereIn('name', self::ADMIN_PANEL_ROLES);
            })->get();
            $totalUsers = $users->count();
            $totalDeactivatedUsers = $users->where('is_active', 'inactive')->count();
            $totalActiveUsers = $users->where('is_active', 'active')->count();
            $totalUnverifiedUsers = $users->whereNull('email_verified_at')->count();
            $totalArchivedUsers = User::onlyTrashed()->whereHas('roles', function ($q) {
                $q->whereIn('name', self::ADMIN_PANEL_ROLES);
            })->count();
            $roles = Role::whereIn('name', self::ADMIN_PANEL_ROLES)->get();
            return view('dashboard.users.admin-index', compact('users', 'totalUsers', 'totalDeactivatedUsers', 'totalActiveUsers', 'totalUnverifiedUsers', 'roles', 'totalArchivedUsers'));
        } catch (\Throwable $th) {
            Log::error("Admin Panel Users Index Failed:" . $th->getMessage());
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create user');
        $validate = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'confirm-password' => 'required|same:password',
            'role' => 'required|exists:roles,name'
        ]);

        if ($validate->fails()) {
            return back()->withErrors($validate)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();

            $user = new User();
            $user->name = $request->first_name . ' ' . $request->last_name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->email_verified_at = now();
            $username = $this->generateUsername($request->first_name . ' ' . $request->last_name);

            while (User::where('username', $username)->exists()) {
                $username = $this->generateUsername($request->first_name . ' ' . $request->last_name);
            }
            $user->username = $username;
            $user->save();
            $user->syncRoles($request->role);

            $profile = new Profile();
            $profile->user_id = $user->id;
            $profile->first_name = $request->first_name;
            $profile->last_name = $request->last_name;
            $profile->save();

            try {
                Mail::to($request->email)->send(new UserCredentialMail($user->name, $request->email, $request->password));
            } catch (\Throwable $th) {
                // throw $th;
            }

            DB::commit();

            return redirect()->route('dashboard.user.index')->with('success', 'User created successfully');
        } catch (\Throwable $th) {
            // throw $th;
            DB::rollback();
            Log::error("User Index Failed:" . $th->getMessage());
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        if (!Gate::any(['update user', 'view user'])) {
            abort(403, 'Unauthorized');
        }
        try {
            $user = User::with('profile.gender',
                                'profile.language',
                                'profile.maritalStatus',
                                'profile.maritalStatus',
                                'profile.designation',
                                'profile.country')->findOrFail($id);
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->profile->first_name,
                    'last_name' => $user->profile->last_name ?? null,
                    'email' => $user->email ?? null,
                    'username' => $user->username ?? null,
                    'role' => $user->getRoleNames()->first(), // ✅ Ensure role is included
                    'full_name' => $user->name,
                    'is_active' => $user->is_active,
                    'profile_image' => $user->profile->profile_image ?? null,
                    'dob' => $user->profile->dob ?? null,
                    'gender' => $user->profile->gender->name ?? null,
                    'language' => $user->profile->language->name ?? null,
                    'marital_status' => $user->profile->maritalStatus->name ?? null,
                    'designation' => $user->profile->designation->name ?? null,
                    'country' => $user->profile->country->name ?? null,
                    'phone_number' => $user->profile->phone_number,
                    'bio' => $user->profile->bio ?? null,
                    'facebook_url' => $user->profile->facebook_url ?? null,
                    'skype_url' => $user->profile->skype_url ?? null,
                    'linkedin_url' => $user->profile->linkedin_url ?? null,
                    'instagram_url' => $user->profile->instagram_url ?? null,
                    'github_url' => $user->profile->github_url ?? null,
                ],
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            Log::error("User Edit Failed:" . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Something went wrong! Please try again later"
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update user');
        $validate = Validator::make($request->all(), [
            'edit_first_name' => 'required|string|max:255',
            'edit_last_name' => 'required|string|max:255',
            'edit_role' => 'required|exists:roles,name'
        ]);

        if ($validate->fails()) {
            return back()->withErrors($validate)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            $user->name = $request->edit_first_name . ' ' . $request->edit_last_name;
            $user->save();

            $user->syncRoles($request->edit_role);

            $profile = Profile::where('user_id', $user->id)->firstOrFail();
            $profile->first_name = $request->edit_first_name;
            $profile->last_name = $request->edit_last_name;
            $profile->save();

            DB::commit();

            return redirect()->route('dashboard.user.index')->with('success', 'User updated successfully');
        } catch (\Throwable $th) {
            // throw $th;
            DB::rollback();
            Log::error("User Update Failed:" . $th->getMessage());
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete user');
        try {
            $user = User::findOrFail($id);
            $user->delete();
            return redirect()->back()->with('success', 'Account Deleted Successfully');
        } catch (\Throwable $th) {
            Log::error('Account Deletion Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
    /**
     * Update status of the specified resource from storage.
     */
    public function updateStatus(string $id)
    {
        $this->authorize('update user');
        try {
            $user = User::findOrFail($id);
            $message = $user->is_active == 'active' ? 'Account Deactivated Successfully' : 'Account Activated Successfully';
            if ($user->is_active == 'active') {
                $user->is_active = 'inactive';
                $user->save();
            } else {
                $user->is_active = 'active';
                $user->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Account Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
    public function generateUsername($name)
    {
        $name = strtolower(str_replace(' ', '', $name));
        $username = $name . rand(1000, 9999);
        return $username;
    }
}
