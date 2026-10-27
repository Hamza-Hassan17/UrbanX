<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\Ride;
use App\Models\TermsAndCondition;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * request-ride calculates the taxi fare on the server and ignores the
 * app-sent subtotal/total_fare.
 */
class RideFareServerSideTest extends TestCase
{
    use DatabaseTransactions;

    private VehicleType $taxi;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'log']);
        $this->app->instance('notificationService', new class {
            public function __call($name, $args) {}
        });

        DB::table('boost_hours')->delete(); // rolled back after each test

        $this->taxi = new VehicleType();
        $this->taxi->forceFill([
            'name' => 'Fare Test ' . uniqid(), 'is_delivery' => '0', 'is_active' => 'active',
            'first_km_price' => 200, 'other_km_price' => 75,
        ])->save();

        $this->customer = User::factory()->create(['username' => 'fare_' . uniqid(), 'phone' => '0300' . random_int(1000000, 9999999), 'is_active' => 'active']);
        $this->customer->forceFill(['terms_accepted_version_id' => TermsAndCondition::current()?->id])->save();
        $this->customer->assignRole(Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']));
    }

    private function request(array $overrides = [])
    {
        Sanctum::actingAs($this->customer, ['*']);

        return $this->postJson('/api/customer/request-ride', array_merge([
            'vehicle_type_id' => $this->taxi->id,
            'pickup_latitude' => '24.8600', 'pickup_longitude' => '67.0000',
            'dropoff_latitude' => '25.2000', 'dropoff_longitude' => '67.3000',
            'distance_km' => '48.3', 'duration_minutes' => '124',
            'subtotal' => '2860', 'discount_amount' => '0', 'total_fare' => '2860',
            'stops' => [],
        ], $overrides));
    }

    public function test_app_fare_is_ignored_and_server_fare_is_saved(): void
    {
        $response = $this->request()->assertOk();

        // 200 + 47.3 x 75 = 3747.50, not the app's 2860.
        $response->assertJsonPath('total_fare', 3747.5)->assertJsonPath('subtotal', 3747.5)->assertJsonPath('is_boost', false);
        $ride = Ride::find($response->json('ride_id'));
        $this->assertEquals(3747.5, (float) $ride->total_fare);
        $this->assertEquals(3747.5, (float) $ride->subtotal);
        $this->assertEquals(48.3, (float) $ride->distance_km);
    }

    public function test_active_boost_hour_multiplies_the_fare(): void
    {
        DB::table('boost_hours')->insert(['start' => '00:00:00', 'end' => '23:59:59', 'multiplier' => 1.4, 'created_at' => now(), 'updated_at' => now()]);

        $this->request()->assertOk()
            ->assertJsonPath('total_fare', 5246.5)
            ->assertJsonPath('is_boost', true);
    }

    public function test_valid_promo_discounts_and_invalid_one_is_dropped(): void
    {
        $promo = new PromoCode();
        $promo->forceFill([
            'name' => 'Ten Off', 'code' => 'TEN' . uniqid(), 'discount_percentage' => 10,
            'usage_limit' => 100, 'usage_limit_per_user' => 5, 'is_active' => 'active',
            'valid_from' => now()->subDay(), 'valid_until' => now()->addDay(),
        ])->save();

        $this->request(['promo_code_id' => $promo->id])->assertOk()
            ->assertJsonPath('discount_amount', 374.75)
            ->assertJsonPath('total_fare', 3372.75);

        $promo->forceFill(['valid_until' => now()->subMinute()])->save();
        $ride = Ride::find($this->request(['promo_code_id' => $promo->id])->assertOk()->json('ride_id'));
        $this->assertNull($ride->promo_code_id);
        $this->assertEquals(3747.5, (float) $ride->total_fare);
    }

    public function test_missing_distance_falls_back_to_straight_line(): void
    {
        $response = $this->request(['distance_km' => null])->assertOk();
        $this->assertGreaterThan(40, $response->json('distance_km'));
        $this->assertGreaterThan(3000, $response->json('total_fare'));
    }
}
