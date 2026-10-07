<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomRidesQueuePhase2Test extends TestCase
{
    use DatabaseTransactions;

    private function makeRidesUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'operator']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view custom rides']));

        $user = User::factory()->create([
            'username' => 'test_' . uniqid(),
            'phone' => '0300' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ]);
        $user->assignRole($role);
        UserWorkspace::create(['user_id' => $user->id, 'workspace' => 'rides']);

        return $user;
    }

    public function test_custom_rides_index_renders(): void
    {
        $user = $this->makeRidesUser();
        $response = $this->actingAs($user)->get(route('dashboard.custom-rides.index'));
        $response->assertStatus(200);
        $response->assertSee('Ride Queue');
    }

    public function test_custom_rides_queue_endpoint_returns_json(): void
    {
        $user = $this->makeRidesUser();
        $response = $this->actingAs($user)->getJson(route('dashboard.custom-rides.queue'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['count', 'rides']);
    }
}
