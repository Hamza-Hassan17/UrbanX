<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardPhase3Test extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(array $workspaces, bool $withRevenue = false): User
    {
        $role = Role::firstOrCreate(['name' => 'operator']);
        if ($withRevenue) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => 'export payroll']));
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

    public function test_rides_workspace_dashboard_shows_ride_kpis_not_platform_content(): void
    {
        $user = $this->makeUser(['rides']);
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Rides Today');
        $response->assertDontSee('Top Cities');
    }

    public function test_delivery_workspace_dashboard_shows_order_kpis(): void
    {
        $user = $this->makeUser(['delivery']);
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Orders Today');
        $response->assertSee('Food Orders');
        $response->assertDontSee('Top Cities');
    }

    public function test_platform_workspace_dashboard_unchanged(): void
    {
        $user = $this->makeUser(['platform']);
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Top Cities');
        $response->assertDontSee('Rides Today');
    }

    public function test_operator_without_export_payroll_does_not_see_revenue_on_rides_dashboard(): void
    {
        $user = $this->makeUser(['rides'], withRevenue: false);
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Revenue Today');
    }

    public function test_finance_role_sees_revenue_on_rides_dashboard(): void
    {
        $user = $this->makeUser(['rides'], withRevenue: true);
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Revenue Today');
    }
}
