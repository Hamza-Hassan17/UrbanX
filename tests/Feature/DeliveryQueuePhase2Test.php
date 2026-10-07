<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryQueuePhase2Test extends TestCase
{
    use DatabaseTransactions;

    private function makeDeliveryUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'operator']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view delivery']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view custom rides']));

        $user = User::factory()->create([
            'username' => 'test_' . uniqid(),
            'phone' => '0300' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ]);
        $user->assignRole($role);
        UserWorkspace::create(['user_id' => $user->id, 'workspace' => 'delivery']);
        UserWorkspace::create(['user_id' => $user->id, 'workspace' => 'rides']);

        return $user;
    }

    public function test_delivery_index_renders(): void
    {
        $user = $this->makeDeliveryUser();
        $response = $this->actingAs($user)->get(route('dashboard.delivery.index'));
        $response->assertStatus(200);
        $response->assertSee('Orders Queue');
    }

    public function test_delivery_queue_endpoint_returns_json(): void
    {
        $user = $this->makeDeliveryUser();
        $response = $this->actingAs($user)->getJson(route('dashboard.delivery.queue'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['count', 'rides']);
    }

    public function test_rides_and_delivery_presets_dont_collide(): void
    {
        $user = $this->makeDeliveryUser();

        $this->actingAs($user)->postJson(route('dashboard.custom-rides.queue.presets'), [
            'name' => 'Shift A',
            'filters' => ['window' => '2', 'status' => 'dispatch'],
        ])->assertStatus(200);

        $this->actingAs($user)->postJson(route('dashboard.delivery.queue.presets'), [
            'name' => 'Shift A',
            'filters' => ['window' => '8', 'status' => 'booked'],
        ])->assertStatus(200);

        $ridesPreset = \App\Models\AdminQueuePreset::where('user_id', $user->id)->where('name', 'rides::Shift A')->first();
        $deliveryPreset = \App\Models\AdminQueuePreset::where('user_id', $user->id)->where('name', 'delivery::Shift A')->first();

        $this->assertEquals('2', $ridesPreset->filters['window']);
        $this->assertEquals('8', $deliveryPreset->filters['window']);
    }
}
