<?php

namespace Tests\Feature;

use App\Models\DriverVehicle;
use App\Models\Ride;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceFollowUpTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(array $workspaces, string $roleName, array $permissions = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $user = User::factory()->create([
            'username' => 'test_' . uniqid(),
            'phone' => '0300' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ]);
        $user->assignRole($role);

        foreach ($workspaces as $workspace) {
            UserWorkspace::create(['user_id' => $user->id, 'workspace' => $workspace]);
        }

        return $user;
    }

    // -- Item 1: Platform as a real, enforced third workspace --

    public function test_rides_only_user_gets_403_on_platform_route(): void
    {
        $user = $this->makeUser(['rides'], 'operator', ['view complain']);

        $response = $this->actingAs($user)->get(route('dashboard.complains.index'));

        $response->assertForbidden();
    }

    public function test_platform_only_user_gets_403_on_rides_route(): void
    {
        $user = $this->makeUser(['platform'], 'operator', ['view custom rides']);

        $response = $this->actingAs($user)->get(route('dashboard.custom-rides.index'));

        $response->assertForbidden();
    }

    public function test_notification_inbox_is_not_platform_restricted(): void
    {
        // Everyone needs their own notification bell regardless of workspace.
        $user = $this->makeUser(['rides'], 'operator');

        $response = $this->actingAs($user)->get(route('dashboard.notifications.index'));

        $response->assertStatus(200);
    }

    // -- Item 2: Drivers split by vehicle_type.is_delivery --

    public function test_delivery_rider_appears_in_riders_list_not_taxi_drivers_list(): void
    {
        $admin = $this->makeUser(['rides', 'delivery'], 'operator', ['view driver']);

        $vehicleType = VehicleType::create(['name' => 'Test Bike Type', 'is_delivery' => '1']);
        $rider = User::factory()->create([
            'username' => 'rider_' . uniqid(),
            'phone' => '0300' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ]);
        $riderRole = Role::firstOrCreate(['name' => 'driver']);
        $rider->assignRole($riderRole);
        DriverVehicle::create([
            'driver_id' => $rider->id,
            'vehicle_type_id' => $vehicleType->id,
            'vehicle_name' => 'Test Bike',
            'vehicle_make' => 'Test',
            'vehicle_year' => '2024',
        ]);

        $taxiResponse = $this->actingAs($admin)->get(route('dashboard.drivers.index'));
        $taxiResponse->assertDontSee($rider->email);

        $deliveryResponse = $this->actingAs($admin)->get(route('dashboard.delivery-riders.index'));
        $deliveryResponse->assertSee($rider->email);
    }

    public function test_rides_only_user_gets_403_on_delivery_riders_route(): void
    {
        $user = $this->makeUser(['rides'], 'operator', ['view driver']);

        $response = $this->actingAs($user)->get(route('dashboard.delivery-riders.index'));

        $response->assertForbidden();
    }

    // -- Item 4: Save as Preset removed --

    public function test_save_as_preset_is_gone_from_rides_queue(): void
    {
        $user = $this->makeUser(['rides'], 'operator', ['view custom rides']);

        $response = $this->actingAs($user)->get(route('dashboard.custom-rides.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Save as preset');
        $response->assertDontSee('id="qf-preset"', false);
        $response->assertSee('Reset');
    }

    // -- Item 5: Admin restrictions --

    public function test_admin_gets_403_on_settings_roles_and_permissions(): void
    {
        $admin = $this->makeUser(['platform'], 'admin');

        $this->actingAs($admin)->get(route('dashboard.setting.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('dashboard.roles.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('dashboard.permissions.index'))->assertForbidden();
    }

    public function test_admin_cannot_delete_a_super_admin_account(): void
    {
        $admin = $this->makeUser(['platform'], 'admin', ['delete user']);
        $superAdmin = $this->makeUser([], 'super-admin');

        $response = $this->actingAs($admin)->delete(route('dashboard.user.destroy', $superAdmin->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id, 'deleted_at' => null]);
    }

    // -- Item 5: Finance read-only on rides/orders --

    public function test_finance_gets_403_creating_a_booking(): void
    {
        $finance = $this->makeUser(['platform'], 'finance', ['view ride']);

        $response = $this->actingAs($finance)->post(route('dashboard.custom-rides.store'), []);

        $response->assertForbidden();
    }

    public function test_finance_gets_403_updating_a_ride(): void
    {
        $finance = $this->makeUser(['platform'], 'finance', ['view ride']);
        $ride = $this->makeMinimalRide();

        $response = $this->actingAs($finance)->put(route('dashboard.rides.update', $ride->id), []);

        $response->assertForbidden();
    }

    public function test_finance_gets_403_deleting_a_ride(): void
    {
        $finance = $this->makeUser(['platform'], 'finance', ['view ride']);
        $ride = $this->makeMinimalRide();

        $response = $this->actingAs($finance)->delete(route('dashboard.rides.destroy', $ride->id));

        $response->assertForbidden();
    }

    private function makeMinimalRide(): Ride
    {
        $passenger = User::factory()->create([
            'username' => 'pax_' . uniqid(),
            'phone' => '0300' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ]);
        $vehicleType = VehicleType::create(['name' => 'Test Ride Vehicle Type']);

        return Ride::create([
            'passenger_id' => $passenger->id,
            'vehicle_type_id' => $vehicleType->id,
            'pickup_latitude' => '24.8607',
            'pickup_longitude' => '67.0011',
            'status' => 'requested',
            'ride_type' => 'ride',
        ]);
    }

    public function test_finance_workspace_is_platform_only_after_seeding(): void
    {
        $financeRole = Role::firstOrCreate(['name' => 'finance']);
        $user = User::factory()->create([
            'username' => 'fin_' . uniqid(),
            'phone' => '0300' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ]);
        $user->assignRole($financeRole);

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'UserRolePermissionSeeder', '--force' => true]);

        $this->assertEquals(['platform'], $user->fresh()->allowedWorkspaces());
    }
}
