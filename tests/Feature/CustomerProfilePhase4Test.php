<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerProfilePhase4Test extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(array $workspaces, array $permissions = ['view user']): User
    {
        $role = Role::firstOrCreate(['name' => 'operator']);
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

    public function test_customer_profile_renders_with_all_tabs(): void
    {
        $admin = $this->makeUser(['platform']);
        $customer = User::factory()->create([
            'username' => 'cust_' . uniqid(),
            'phone' => '0301' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Rides');
        $response->assertSee('Rentals');
        $response->assertSee('Food & Parcel Orders');
        $response->assertSee('Wallet');
        $response->assertSee('Complaints');
    }

    public function test_complaints_list_scoped_to_rides_workspace_excludes_null_service(): void
    {
        $user = $this->makeUser(['rides'], ['view complain']);

        \App\Models\Complain::create([
            'user_id' => $user->id,
            'name' => 'Null Service Complaint',
            'subject' => 'Untagged',
            'complain_text' => 'test',
            'status' => 'pending',
            'service' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.complains.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Null Service Complaint');
    }

    public function test_complaints_list_platform_shows_null_service_complaint(): void
    {
        $user = $this->makeUser(['platform'], ['view complain']);

        \App\Models\Complain::create([
            'user_id' => $user->id,
            'name' => 'Null Service Complaint',
            'subject' => 'Untagged',
            'complain_text' => 'test',
            'status' => 'pending',
            'service' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.complains.index'));

        $response->assertStatus(200);
        $response->assertSee('Null Service Complaint');
    }
}
