<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions
        Permission::firstOrCreate(['name' => 'view role']);
        Permission::firstOrCreate(['name' => 'create role']);
        Permission::firstOrCreate(['name' => 'update role']);
        Permission::firstOrCreate(['name' => 'delete role']);

        Permission::firstOrCreate(['name' => 'view permission']);
        Permission::firstOrCreate(['name' => 'create permission']);
        Permission::firstOrCreate(['name' => 'update permission']);
        Permission::firstOrCreate(['name' => 'delete permission']);

        Permission::firstOrCreate(['name' => 'view user']);
        Permission::firstOrCreate(['name' => 'create user']);
        Permission::firstOrCreate(['name' => 'update user']);
        Permission::firstOrCreate(['name' => 'delete user']);

        Permission::firstOrCreate(['name' => 'view archived user']);
        Permission::firstOrCreate(['name' => 'create archived user']);
        Permission::firstOrCreate(['name' => 'update archived user']);
        Permission::firstOrCreate(['name' => 'delete archived user']);

        Permission::firstOrCreate(['name' => 'view setting']);
        Permission::firstOrCreate(['name' => 'create setting']);
        Permission::firstOrCreate(['name' => 'update setting']);
        Permission::firstOrCreate(['name' => 'delete setting']);

        Permission::firstOrCreate(['name' => 'view driver']);
        Permission::firstOrCreate(['name' => 'create driver']);
        Permission::firstOrCreate(['name' => 'update driver']);
        Permission::firstOrCreate(['name' => 'delete driver']);

        Permission::firstOrCreate(['name' => 'view notification']);
        Permission::firstOrCreate(['name' => 'create notification']);
        Permission::firstOrCreate(['name' => 'update notification']);
        Permission::firstOrCreate(['name' => 'delete notification']);

        Permission::firstOrCreate(['name' => 'view promo code']);
        Permission::firstOrCreate(['name' => 'create promo code']);
        Permission::firstOrCreate(['name' => 'update promo code']);
        Permission::firstOrCreate(['name' => 'delete promo code']);

        Permission::firstOrCreate(['name' => 'view vehicle type']);
        Permission::firstOrCreate(['name' => 'create vehicle type']);
        Permission::firstOrCreate(['name' => 'update vehicle type']);
        Permission::firstOrCreate(['name' => 'delete vehicle type']);

        Permission::firstOrCreate(['name' => 'view chauffeur vehicle']);
        Permission::firstOrCreate(['name' => 'create chauffeur vehicle']);
        Permission::firstOrCreate(['name' => 'update chauffeur vehicle']);
        Permission::firstOrCreate(['name' => 'delete chauffeur vehicle']);

        Permission::firstOrCreate(['name' => 'view chauffeur booking']);
        Permission::firstOrCreate(['name' => 'create chauffeur booking']);
        Permission::firstOrCreate(['name' => 'update chauffeur booking']);
        Permission::firstOrCreate(['name' => 'delete chauffeur booking']);

        Permission::firstOrCreate(['name' => 'view complain']);
        Permission::firstOrCreate(['name' => 'create complain']);
        Permission::firstOrCreate(['name' => 'update complain']);
        Permission::firstOrCreate(['name' => 'delete complain']);

        Permission::firstOrCreate(['name' => 'view boost hour']);
        Permission::firstOrCreate(['name' => 'create boost hour']);
        Permission::firstOrCreate(['name' => 'update boost hour']);
        Permission::firstOrCreate(['name' => 'delete boost hour']);

        Permission::firstOrCreate(['name' => 'view custom rides']);
        Permission::firstOrCreate(['name' => 'create custom rides']);
        Permission::firstOrCreate(['name' => 'update custom rides']);
        Permission::firstOrCreate(['name' => 'delete custom rides']);

        Permission::firstOrCreate(['name' => 'view announcement']);
        Permission::firstOrCreate(['name' => 'create announcement']);
        Permission::firstOrCreate(['name' => 'update announcement']);
        Permission::firstOrCreate(['name' => 'delete announcement']);

        Permission::firstOrCreate(['name' => 'view ride']);
        Permission::firstOrCreate(['name' => 'create ride']);
        Permission::firstOrCreate(['name' => 'update ride']);
        Permission::firstOrCreate(['name' => 'delete ride']);

        Permission::firstOrCreate(['name' => 'view restaurant category']);
        Permission::firstOrCreate(['name' => 'create restaurant category']);
        Permission::firstOrCreate(['name' => 'update restaurant category']);
        Permission::firstOrCreate(['name' => 'delete restaurant category']);

        Permission::firstOrCreate(['name' => 'view restaurant']);
        Permission::firstOrCreate(['name' => 'create restaurant']);
        Permission::firstOrCreate(['name' => 'update restaurant']);
        Permission::firstOrCreate(['name' => 'delete restaurant']);

        Permission::firstOrCreate(['name' => 'view restaurant voucher']);
        Permission::firstOrCreate(['name' => 'create restaurant voucher']);
        Permission::firstOrCreate(['name' => 'update restaurant voucher']);
        Permission::firstOrCreate(['name' => 'delete restaurant voucher']);

        // RBAC rollout (dispatcher/finance) -- see UrbanX_RBAC_ClaudeCode_Brief.md.
        // 'assign ride' fixes a real gap: CustomRideController::requestCustomRide()
        // and the dispatch-queue "Assign Driver" action had no permission check at
        // all before this. The rest are scaffolding for features not built yet
        // (multi-city live tracking, anomaly alerts, mid-ride reassignment, final
        // payment edit, payroll exports) -- defined now per the brief's matrix so
        // the permission layer is ready when those screens land; unenforced until
        // then since there's no controller to check them yet.
        Permission::firstOrCreate(['name' => 'assign ride']);
        Permission::firstOrCreate(['name' => 'view report']);
        Permission::firstOrCreate(['name' => 'view live tracking']);
        Permission::firstOrCreate(['name' => 'view anomaly alert']);
        Permission::firstOrCreate(['name' => 'reassign ride']);
        Permission::firstOrCreate(['name' => 'edit ride payment']);
        Permission::firstOrCreate(['name' => 'export payroll']);

        // Create Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']); //as super-admin
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $driverRole = Role::firstOrCreate(['name' => 'driver']);
        $driverRole = Role::firstOrCreate(['name' => 'restaurant']);
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $riderRole = Role::firstOrCreate(['name' => 'rider']);
        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);
        $financeRole = Role::firstOrCreate(['name' => 'finance']);

        // give all permissions to super-admin role.
        $allPermissionNames = Permission::pluck('name')->toArray();

        $superAdminRole->givePermissionTo($allPermissionNames);

        // give permissions to admin role.
        // NOTE: additive only -- the RBAC matrix in the brief marks Admin as ∅
        // (no access) on Roles & permissions management, but Admin already had
        // 'view role'/'view permission' in this live app before this rollout.
        // Per the brief ("extend Admin's existing permission set... don't
        // recreate it") this only adds what Admin is missing; it does not revoke
        // access Admin already had. Flagging this discrepancy rather than
        // silently revoking it -- confirm if 'view role'/'view permission' should
        // actually come off Admin.
        $adminRole->givePermissionTo(['view role']);
        $adminRole->givePermissionTo(['view permission']);
        $adminRole->givePermissionTo(['create user', 'view user', 'update user']);
        // Users/drivers/restaurants CRUD = Full for Admin (driver/restaurant/
        // archived-user permissions didn't exist on Admin at all before this).
        $adminRole->givePermissionTo(['delete user']);
        $adminRole->givePermissionTo(['view driver', 'create driver', 'update driver', 'delete driver']);
        $adminRole->givePermissionTo(['view restaurant', 'create restaurant', 'update restaurant', 'delete restaurant']);
        $adminRole->givePermissionTo(['view archived user', 'create archived user', 'update archived user', 'delete archived user']);
        // Pricing, promo codes, boost hours = Full for Admin.
        $adminRole->givePermissionTo(['view promo code', 'create promo code', 'update promo code', 'delete promo code']);
        $adminRole->givePermissionTo(['view boost hour', 'create boost hour', 'update boost hour', 'delete boost hour']);
        $adminRole->givePermissionTo(['view vehicle type', 'create vehicle type', 'update vehicle type', 'delete vehicle type']);
        // Manual ride assignment / dispatch queue = View for Admin (can see the
        // queue, cannot assign -- that needs 'assign ride', which Admin does not get).
        $adminRole->givePermissionTo(['view custom rides']);
        // Multi-city live tracking = View for Admin. Found missing while building
        // the Live Ops tracking feature -- the matrix always intended this, it
        // just never got granted when the permission was first created.
        $adminRole->givePermissionTo(['view live tracking']);
        // Operator/driver job-count reporting = View for Admin.
        $adminRole->givePermissionTo(['view report']);
        // Complaints & reviews, Announcements = Full for Admin.
        $adminRole->givePermissionTo(['view complain', 'create complain', 'update complain', 'delete complain']);
        $adminRole->givePermissionTo(['view announcement', 'create announcement', 'update announcement', 'delete announcement']);

        // give permissions to dispatcher role.
        // Users/drivers/restaurants = View only.
        $dispatcherRole->givePermissionTo(['view user', 'view driver', 'view restaurant', 'view archived user']);
        // Manual ride assignment / dispatch queue = Full.
        $dispatcherRole->givePermissionTo(['view custom rides', 'assign ride']);
        // Multi-city live tracking, anomaly alerts = Full (view-only actions;
        // there's no CRUD shape for a live map or an alert feed).
        $dispatcherRole->givePermissionTo(['view live tracking', 'view anomaly alert']);
        // Ride reassignment (status/driver) = Full.
        $dispatcherRole->givePermissionTo(['view ride', 'update ride', 'reassign ride']);
        // Operator/driver job-count reporting = Own (scoped to the dispatcher's
        // own activity row -- enforced in ReportController, not via a separate
        // permission).
        $dispatcherRole->givePermissionTo(['view report']);

        // give permissions to finance role.
        // Ride reassignment (status/driver) = View only.
        $financeRole->givePermissionTo(['view ride']);
        // Ride reassignment (final payment edit) = Full.
        $financeRole->givePermissionTo(['edit ride payment']);
        // Operator/driver job-count reporting = Full.
        $financeRole->givePermissionTo(['view report']);
        // Payroll exports (PDF/Excel, bulk-send) = Full.
        $financeRole->givePermissionTo(['export payroll']);


        // Create User and assign Role to it.

        $superAdminUser = User::firstOrCreate([
                    'email' => 'superadmin@gmail.com',
                ], [
                    'name' => 'Super Admin',
                    'email' => 'superadmin@gmail.com',
                    'phone' => '123456789',
                    'username' => 'superadmin',
                    'password' => Hash::make ('superadmin@gmail.com'),
                    'email_verified_at' => now(),
                ]);

        $superAdminUser->assignRole($superAdminRole);

        $superAdminProfile = $superAdminUser->profile()->firstOrCreate([
            'user_id' => $superAdminUser->id,
        ], [
            'user_id' => $superAdminUser->id,
            'first_name' => $superAdminUser->name,
        ]);

        $adminUser = User::firstOrCreate([
                            'email' => 'admin@gmail.com'
                        ], [
                            'name' => 'Admin',
                            'username' => 'admin',
                            'phone' => '0000000000',
                            'email' => 'admin@gmail.com',
                            'password' => Hash::make ('admin@gmail.com'),
                            'email_verified_at' => now(),
                        ]);

        $adminUser->assignRole($adminRole);

        $adminUserProfile = $adminUser->profile()->firstOrCreate([
            'user_id' => $adminUser->id,
        ], [
            'user_id' => $adminUser->id,
            'first_name' => $adminUser->name,
        ]);

        $driverUser = User::firstOrCreate([
                    'email' => 'driver@gmail.com',
                ], [
                    'name' => 'Driver',
                    'email' => 'driver@gmail.com',
                    'phone' => '1234567890',
                    'username' => 'driver',
                    'password' => Hash::make ('driver@gmail.com'),
                    'email_verified_at' => now(),
                ]);

        $driverUser->assignRole($driverRole);

        $driverProfile = $driverUser->profile()->firstOrCreate([
            'user_id' => $driverUser->id,
        ], [
            'user_id' => $driverUser->id,
            'first_name' => $driverUser->name,
        ]);

        $driverCnic = $driverUser->driverCnic()->firstOrCreate([
            'driver_id' => $driverUser->id,
        ], [
            'driver_id' => $driverUser->id,
            'name' => 'John Doe',
            'cnic_number' => '1234567890987654321',
            'issue_date' => now()->subYears(5),
            'front_picture' => 'path/to/front_picture.jpg',
            'back_picture' => 'path/to/back_picture.jpg',
        ]);

        $driverLicense = $driverUser->driverLicense()->firstOrCreate([
            'driver_id' => $driverUser->id,
        ], [
            'driver_id' => $driverUser->id,
            'name' => 'John Doe',
            'license_number' => '1234567890987654321',
            'address' => 'Karachi, Pakistan',
            'front_picture' => 'path/to/front_picture.jpg',
            'back_picture' => 'path/to/back_picture.jpg',
        ]);

        $driverVehicle = $driverUser->driverVehicle()->firstOrCreate([
            'driver_id' => $driverUser->id,
        ], [
            'driver_id' => $driverUser->id,
            'vehicle_type_id' => 1,
            'vehicle_name' => 'Toyota Prius',
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Prius',
            'vehicle_color' => 'White',
            'vehicle_year' => '2020',
            'vehicle_plate_number' => 'ABC-1234',
            'vehicle_images' => 'path/to/vehicle_image.jpg',
        ]);

        $dispatcherUser = User::firstOrCreate([
                    'email' => 'dispatcher@gmail.com',
                ], [
                    'name' => 'Dispatcher',
                    'email' => 'dispatcher@gmail.com',
                    'phone' => '1234567891',
                    'username' => 'dispatcher',
                    'password' => Hash::make('dispatcher@gmail.com'),
                    'email_verified_at' => now(),
                ]);

        $dispatcherUser->assignRole($dispatcherRole);

        $dispatcherProfile = $dispatcherUser->profile()->firstOrCreate([
            'user_id' => $dispatcherUser->id,
        ], [
            'user_id' => $dispatcherUser->id,
            'first_name' => $dispatcherUser->name,
        ]);

        $financeUser = User::firstOrCreate([
                    'email' => 'finance@gmail.com',
                ], [
                    'name' => 'Finance',
                    'email' => 'finance@gmail.com',
                    'phone' => '1234567892',
                    'username' => 'finance',
                    'password' => Hash::make('finance@gmail.com'),
                    'email_verified_at' => now(),
                ]);

        $financeUser->assignRole($financeRole);

        $financeProfile = $financeUser->profile()->firstOrCreate([
            'user_id' => $financeUser->id,
        ], [
            'user_id' => $financeUser->id,
            'first_name' => $financeUser->name,
        ]);
    }
}
