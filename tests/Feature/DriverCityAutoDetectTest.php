<?php

namespace Tests\Feature;

use App\Models\TermsAndCondition;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DriverCityAutoDetectTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeGoogleResponse(string $city): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => "Some St, {$city}, Pakistan",
                    'address_components' => [
                        ['long_name' => $city, 'short_name' => $city, 'types' => ['locality', 'political']],
                        ['long_name' => 'Pakistan', 'short_name' => 'PK', 'types' => ['country', 'political']],
                    ],
                ]],
            ], 200),
        ]);
    }

    public function test_driver_registration_auto_fills_city_from_location(): void
    {
        config(['services.google_maps.key' => 'fake-key-for-test']);
        $this->fakeGoogleResponse('Karachi');
        TermsAndCondition::create(['content' => '<p>t</p>']);

        $response = $this->postJson('/api/register', [
            'email' => 'driver_' . uniqid() . '@example.com',
            'phone' => '0300' . random_int(1000000, 9999999),
            'role' => 'driver',
            'lat' => '24.8607',
            'lang' => '67.0011',
        ]);

        $response->assertStatus(201);

        $user = \App\Models\User::where('email', $response->json('user.email'))->first();
        $this->assertEquals('Karachi', $user->profile->city);
    }

    public function test_customer_registration_does_not_set_city(): void
    {
        config(['services.google_maps.key' => 'fake-key-for-test']);
        $this->fakeGoogleResponse('Karachi');
        TermsAndCondition::create(['content' => '<p>t</p>']);

        $response = $this->postJson('/api/register', [
            'email' => 'cust_' . uniqid() . '@example.com',
            'phone' => '0300' . random_int(1000000, 9999999),
            'role' => 'user',
            'lat' => '24.8607',
            'lang' => '67.0011',
        ]);

        $response->assertStatus(201);

        $user = \App\Models\User::where('email', $response->json('user.email'))->first();
        $this->assertNull($user->profile->city);
    }

    public function test_driver_registration_without_location_leaves_city_null(): void
    {
        TermsAndCondition::create(['content' => '<p>t</p>']);

        $response = $this->postJson('/api/register', [
            'email' => 'driver_noloc_' . uniqid() . '@example.com',
            'phone' => '0300' . random_int(1000000, 9999999),
            'role' => 'driver',
        ]);

        $response->assertStatus(201);

        $user = \App\Models\User::where('email', $response->json('user.email'))->first();
        $this->assertNull($user->profile->city);
    }
}
