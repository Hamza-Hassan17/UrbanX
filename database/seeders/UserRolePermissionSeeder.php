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
        Permission::create(['name' => 'view role']);
        Permission::create(['name' => 'create role']);
        Permission::create(['name' => 'update role']);
        Permission::create(['name' => 'delete role']);

        Permission::create(['name' => 'view permission']);
        Permission::create(['name' => 'create permission']);
        Permission::create(['name' => 'update permission']);
        Permission::create(['name' => 'delete permission']);

        Permission::create(['name' => 'view user']);
        Permission::create(['name' => 'create user']);
        Permission::create(['name' => 'update user']);
        Permission::create(['name' => 'delete user']);

        Permission::create(['name' => 'view archived user']);
        Permission::create(['name' => 'create archived user']);
        Permission::create(['name' => 'update archived user']);
        Permission::create(['name' => 'delete archived user']);

        Permission::create(['name' => 'view setting']);
        Permission::create(['name' => 'create setting']);
        Permission::create(['name' => 'update setting']);
        Permission::create(['name' => 'delete setting']);

        Permission::create(['name' => 'view driver']);
        Permission::create(['name' => 'create driver']);
        Permission::create(['name' => 'update driver']);
        Permission::create(['name' => 'delete driver']);

        Permission::create(['name' => 'view notification']);
        Permission::create(['name' => 'create notification']);
        Permission::create(['name' => 'update notification']);
        Permission::create(['name' => 'delete notification']);

        Permission::create(['name' => 'view promo code']);
        Permission::create(['name' => 'create promo code']);
        Permission::create(['name' => 'update promo code']);
        Permission::create(['name' => 'delete promo code']);

        Permission::create(['name' => 'view vehicle type']);
        Permission::create(['name' => 'create vehicle type']);
        Permission::create(['name' => 'update vehicle type']);
        Permission::create(['name' => 'delete vehicle type']);

        Permission::create(['name' => 'view chauffeur vehicle']);
        Permission::create(['name' => 'create chauffeur vehicle']);
        Permission::create(['name' => 'update chauffeur vehicle']);
        Permission::create(['name' => 'delete chauffeur vehicle']);

        Permission::create(['name' => 'view chauffeur booking']);
        Permission::create(['name' => 'create chauffeur booking']);
        Permission::create(['name' => 'update chauffeur booking']);
        Permission::create(['name' => 'delete chauffeur booking']);

        Permission::create(['name' => 'view complain']);
        Permission::create(['name' => 'create complain']);
        Permission::create(['name' => 'update complain']);
        Permission::create(['name' => 'delete complain']);

        Permission::create(['name' => 'view boost hour']);
        Permission::create(['name' => 'create boost hour']);
        Permission::create(['name' => 'update boost hour']);
        Permission::create(['name' => 'delete boost hour']);

        Permission::create(['name' => 'view custom rides']);
        Permission::create(['name' => 'create custom rides']);
        Permission::create(['name' => 'update custom rides']);
        Permission::create(['name' => 'delete custom rides']);

        Permission::create(['name' => 'view announcement']);
        Permission::create(['name' => 'create announcement']);
        Permission::create(['name' => 'update announcement']);
        Permission::create(['name' => 'delete announcement']);

        Permission::create(['name' => 'view ride']);
        Permission::create(['name' => 'create ride']);
        Permission::create(['name' => 'update ride']);
        Permission::create(['name' => 'delete ride']);

        // Create Roles
        $superAdminRole = Role::create(['name' => 'super-admin']); //as super-admin
        $adminRole = Role::create(['name' => 'admin']);
        $driverRole = Role::create(['name' => 'driver']);
        $userRole = Role::create(['name' => 'user']);

        // give all permissions to super-admin role.
        $allPermissionNames = Permission::pluck('name')->toArray();

        $superAdminRole->givePermissionTo($allPermissionNames);

        // give permissions to admin role.
        $adminRole->givePermissionTo(['view role']);
        $adminRole->givePermissionTo(['view permission']);
        $adminRole->givePermissionTo(['create user', 'view user', 'update user']);


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

    }
}
