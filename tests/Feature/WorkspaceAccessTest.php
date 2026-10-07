<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies EnsureWorkspaceAccess actually blocks a route it doesn't own --
 * sidebar hiding alone isn't security, this is. Wrapped in a DB transaction
 * so it never touches real data (no RefreshDatabase/seeding against the
 * live dev database).
 */
class WorkspaceAccessTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(array $workspaces, string $roleName = 'operator'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view custom rides']));
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view restaurant']));

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

    public function test_user_without_rides_workspace_gets_403_on_rides_route(): void
    {
        $user = $this->makeUser(['delivery', 'platform']);

        $response = $this->actingAs($user)->get(route('dashboard.custom-rides.index'));

        $response->assertForbidden();
    }

    public function test_user_with_rides_workspace_can_reach_rides_route(): void
    {
        $user = $this->makeUser(['rides', 'platform']);

        $response = $this->actingAs($user)->get(route('dashboard.custom-rides.index'));

        $response->assertStatus(200);
    }

    public function test_user_without_delivery_workspace_gets_403_on_restaurants_route(): void
    {
        $user = $this->makeUser(['rides', 'platform']);

        $response = $this->actingAs($user)->get(route('dashboard.restaurants.index'));

        $response->assertForbidden();
    }

    public function test_user_with_no_workspace_is_redirected_to_no_workspace_page(): void
    {
        $user = $this->makeUser([]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('no-workspace'));
    }

    public function test_super_admin_bypasses_workspace_restriction(): void
    {
        $user = $this->makeUser([], 'super-admin');

        $response = $this->actingAs($user)->get(route('dashboard.restaurants.index'));

        $response->assertStatus(200);
    }
}
