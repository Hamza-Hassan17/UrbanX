<?php

namespace Tests\Feature;

use App\Models\Complain;
use App\Models\DriverVehicle;
use App\Models\Ride;
use App\Models\RiderCashLedger;
use App\Models\SystemSetting;
use App\Models\TermsAndCondition;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\VehicleType;
use App\Services\PerformanceReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Parcels move from delivery riders (Delivery app) to taxi drivers of the
 * "Parcel vehicle type" (Driver app), managed in the Rides workspace.
 */
class ParcelToDriversTest extends TestCase
{
    use DatabaseTransactions;

    public array $pushes = [];
    private VehicleType $carType;
    private VehicleType $bikeType;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'log']);

        $test = $this;
        $this->app->instance('notificationService', new class($test) {
            public function __construct(private $test) {}
            public function notifyUsers($users, $title, ...$rest) { $this->test->pushes[] = $title; }
            public function __call($name, $args) {}
        });

        $this->carType = $this->vehicleType(false);
        $this->bikeType = $this->vehicleType(true);
        SystemSetting::query()->update(['parcel_vehicle_type_id' => null, 'no_driver_handling' => false, 'driver_commission_percent' => 10]);
    }

    private function useParcelType(?VehicleType $type): void
    {
        SystemSetting::query()->update(['parcel_vehicle_type_id' => $type?->id]);
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'username' => 'pc_' . uniqid(),
            'phone' => '0300' . random_int(1000000, 9999999),
            'is_active' => 'active',
        ], $attributes));
        $user->forceFill(['terms_accepted_version_id' => TermsAndCondition::current()?->id])->save();
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        (new \App\Models\Profile())->forceFill(['user_id' => $user->id, 'phone_number' => $user->phone, 'city' => 'Karachi'])->save();

        return $user;
    }

    private function makeAdmin(array $workspaces, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'pc_role_' . uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = $this->makeUser($role->name);
        foreach ($workspaces as $workspace) {
            UserWorkspace::create(['user_id' => $user->id, 'workspace' => $workspace]);
        }

        return $user;
    }

    private function vehicleType(bool $delivery): VehicleType
    {
        $type = new VehicleType();
        $type->forceFill(['name' => 'PC ' . ($delivery ? 'Bike' : 'Car') . ' ' . uniqid(), 'is_delivery' => $delivery ? '1' : '0', 'is_active' => 'active'])->save();

        return $type;
    }

    private function makeDriver(VehicleType $type, string $lat = '24.8600', string $lng = '67.0000'): User
    {
        $driver = $this->makeUser('driver', ['lat' => $lat, 'lang' => $lng]);
        $driver->forceFill(['driver_status' => 'available'])->save();
        (new DriverVehicle())->forceFill(['driver_id' => $driver->id, 'vehicle_type_id' => $type->id, 'vehicle_name' => 'Test'])->save();

        return $driver->fresh();
    }

    private function bookParcel(User $customer)
    {
        Sanctum::actingAs($customer, ['*']);

        return $this->postJson('/api/customer/book-parcel', [
            'pickup_latitude' => 24.8600, 'pickup_longitude' => 67.0000,
            'dropoff_latitude' => 24.8700, 'dropoff_longitude' => 67.0100,
            'sender_name' => 'Ali', 'sender_phone' => '03001112222',
            'receiver_name' => 'Sara', 'receiver_phone' => '03003334444',
            'package_type' => 'Documents', 'package_size' => 'small',
            'delivery_fee_paid_by' => 'receiver', 'payment_method' => 'cod',
        ]);
    }

    // ---------------------------------------------------------------- booking

    public function test_without_setting_parcels_keep_going_to_delivery_riders(): void
    {
        $response = $this->bookParcel($this->makeUser('user'))->assertOk();
        $ride = Ride::find($response->json('ride_id'));

        $this->assertSame('delivery', $ride->ride_type);
        $this->assertSame('delivery', $response->json('ride_type'));
        $this->assertSame('1', (string) VehicleType::find($ride->vehicle_type_id)->is_delivery);
    }

    public function test_with_setting_parcel_goes_to_parcel_vehicle_type(): void
    {
        $this->useParcelType($this->carType);

        $response = $this->bookParcel($this->makeUser('user'))->assertOk()->assertJsonPath('ride_type', 'parcel');
        $ride = Ride::find($response->json('ride_id'));

        $this->assertSame('parcel', $ride->ride_type);
        $this->assertSame($this->carType->id, $ride->vehicle_type_id);
        $this->assertNotNull($ride->delivery_code);
    }

    public function test_a_delivery_vehicle_type_is_never_used_as_parcel_type(): void
    {
        SystemSetting::query()->update(['parcel_vehicle_type_id' => $this->bikeType->id]);
        $this->assertNull(\App\Services\DispatchSettingsService::parcelVehicleTypeId());

        $admin = $this->makeAdmin(['platform'], ['manage pricing fees']);
        $this->actingAs($admin)->get(route('dashboard.pricing-fees.index'))->assertOk()->assertSee('Parcel vehicle type');
    }

    // --------------------------------------------------------------- dispatch

    public function test_taxi_driver_of_parcel_type_gets_the_parcel_and_rider_does_not(): void
    {
        $this->useParcelType($this->carType);
        $rideId = $this->bookParcel($this->makeUser('user'))->assertOk()->json('ride_id');

        $rider = $this->makeDriver($this->bikeType);
        Sanctum::actingAs($rider, ['*']);
        $riderRides = collect($this->getJson('/api/driver/get-rides')->assertOk()->json('rides'));
        $this->assertNull($riderRides->firstWhere('id', $rideId));

        $driver = $this->makeDriver($this->carType);
        Sanctum::actingAs($driver, ['*']);
        $this->getJson('/api/driver/get-rides')->assertOk()->assertJsonPath('rides.0.id', $rideId);
    }

    public function test_offer_and_no_driver_messages_say_parcel(): void
    {
        $this->useParcelType($this->carType);
        $ride = Ride::find($this->bookParcel($this->makeUser('user'))->assertOk()->json('ride_id'));
        $driver = $this->makeDriver($this->carType);

        \App\Services\OfferDispatchService::createOffer($ride, $driver);
        $this->assertContains('New Job Available', $this->pushes);

        SystemSetting::query()->update(['no_driver_handling' => true]);
        $this->assertTrue(\App\Services\NoDriverFoundService::appliesTo($ride->fresh()));
    }

    // ------------------------------------------------------------------ money

    public function test_completed_cod_parcel_settles_like_a_taxi_ride(): void
    {
        $this->useParcelType($this->carType);
        $customer = $this->makeUser('user');
        $ride = Ride::find($this->bookParcel($customer)->assertOk()->json('ride_id'));
        $driver = $this->makeDriver($this->carType);
        $ride->forceFill(['driver_id' => $driver->id, 'status' => 'started', 'accepted_at' => now(), 'started_at' => now()])->save();

        Sanctum::actingAs($driver, ['*']);
        $this->postJson('/api/driver/update-ride-status', [
            'ride_id' => $ride->id, 'status' => 'completed',
            'delivery_code' => $ride->delivery_code, 'cash_collected_amount' => (float) $ride->total_fare,
        ])->assertOk();

        $ride->refresh();
        $this->assertSame('completed', $ride->status);
        $this->assertSame('collected', $ride->payment_status);
        // Taxi commission %, not the delivery flat share.
        $this->assertEquals(10, $ride->fare_breakdown['commission_rate']);
        $this->assertNull($ride->fare_breakdown['commission_flat_pkr'] ?? null);

        // No cash-in-hand entry; the commission+SST is owed on the outstanding balance.
        $this->assertFalse(RiderCashLedger::where('rider_id', $driver->id)->where('entry_type', 'collected')->exists());
        $owed = RiderCashLedger::where('rider_id', $driver->id)->where('entry_type', 'owed')->first();
        $this->assertNotNull($owed);
        $this->assertEqualsWithDelta($ride->fare_breakdown['commission_amount'] + $ride->fare_breakdown['sst_amount'], (float) $owed->amount, 0.01);

        // Earnings: shows as a parcel row; ride history includes it.
        $rows = collect($this->getJson('/api/driver/earnings?type=parcel')->assertOk()->json('rows'));
        $this->assertSame('parcel', $rows->firstWhere('id', $ride->id)['type']);
        $history = collect($this->getJson('/api/driver/ride-history')->assertOk()->json('rides') ?? $this->getJson('/api/driver/ride-history')->json('ride_history'));
        $this->assertNotEmpty($history);
    }

    public function test_sender_pays_parcel_cash_at_pickup_is_not_cash_in_hand(): void
    {
        $this->useParcelType($this->carType);
        $ride = Ride::find($this->bookParcel($this->makeUser('user'))->assertOk()->json('ride_id'));
        $driver = $this->makeDriver($this->carType);
        $ride->forceFill(['driver_id' => $driver->id, 'status' => 'accepted', 'delivery_fee_paid_by' => 'sender'])->save();

        Sanctum::actingAs($driver, ['*']);
        $this->postJson('/api/driver/confirm-parcel-cash-collected', ['ride_id' => $ride->id, 'amount' => (float) $ride->total_fare])
            ->assertOk();

        $this->assertSame('collected', $ride->fresh()->payment_status);
        $this->assertFalse(RiderCashLedger::where('rider_id', $driver->id)->exists());
    }

    public function test_legacy_delivery_parcel_still_uses_cash_in_hand(): void
    {
        $ride = Ride::find($this->bookParcel($this->makeUser('user'))->assertOk()->json('ride_id')); // no setting -> legacy
        $rider = $this->makeDriver($this->bikeType);
        $ride->forceFill(['driver_id' => $rider->id, 'status' => 'accepted'])->save();

        Sanctum::actingAs($rider, ['*']);
        $this->postJson('/api/driver/confirm-parcel-cash-collected', ['ride_id' => $ride->id, 'amount' => (float) $ride->total_fare])
            ->assertOk();

        $this->assertTrue(RiderCashLedger::where('rider_id', $rider->id)->where('entry_type', 'collected')->exists());
    }

    // ------------------------------------------------------------ admin panel

    public function test_parcel_assignment_page_books_assigns_and_reassigns_with_taxi_drivers(): void
    {
        $admin = $this->makeAdmin(['rides'], ['view custom rides', 'assign ride', 'reassign ride']);
        $driverA = $this->makeDriver($this->carType);
        $driverB = $this->makeDriver($this->carType, '24.8610', '67.0010');
        $rider = $this->makeDriver($this->bikeType);
        $payload = [
            'pickup_latitude' => 24.86, 'pickup_longitude' => 67.00, 'dropoff_latitude' => 24.87, 'dropoff_longitude' => 67.01,
            'sender_name' => 'Sana', 'sender_phone' => '0301' . random_int(1000000, 9999999),
            'receiver_name' => 'Omar', 'receiver_phone' => '03019999999',
            'package_type' => 'Documents', 'package_size' => 'small', 'delivery_fee_paid_by' => 'sender',
        ];

        // Not configured yet: page warns, booking refused.
        $this->actingAs($admin)->get(route('dashboard.parcel-assignment.index'))->assertOk()->assertSee('Choose the Parcel vehicle type');
        $this->actingAs($admin)->postJson(route('dashboard.parcel-assignment.parcel'), $payload)
            ->assertStatus(422)->assertJsonPath('message', 'Choose the Parcel vehicle type in Pricing & Fees first.');

        $this->useParcelType($this->carType);
        $this->actingAs($admin)->get(route('dashboard.parcel-assignment.index'))
            ->assertOk()->assertSee('Manual Parcel Assignment')->assertSee($this->carType->name);

        $stats = $this->actingAs($admin)->getJson(route('dashboard.parcel-assignment.stats'))->assertOk()->json();
        $riderIds = collect($stats['riders'])->pluck('id');
        $this->assertTrue($riderIds->contains($driverA->id));
        $this->assertFalse($riderIds->contains($rider->id));

        $this->actingAs($admin)->postJson(route('dashboard.parcel-assignment.parcel'), $payload + ['rider_id' => $rider->id])
            ->assertStatus(422)->assertJsonPath('message', 'Selected driver does not drive the parcel vehicle type.');

        $rideId = $this->actingAs($admin)->postJson(route('dashboard.parcel-assignment.parcel'), $payload + ['rider_id' => $driverA->id])
            ->assertOk()->json('ride_id');
        $parcel = Ride::find($rideId);
        $this->assertSame('parcel', $parcel->ride_type);
        $this->assertSame($driverA->id, $parcel->driver_id);

        $rows = collect($this->actingAs($admin)->getJson(route('dashboard.parcel-assignment.queue', ['window' => 'all']))->assertOk()->json('rides'));
        $this->assertTrue($rows->firstWhere('id', $rideId)['reassignable']);

        $this->actingAs($admin)->postJson(route('dashboard.parcel-assignment.reassign', $rideId), ['driver_id' => $rider->id])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('dashboard.parcel-assignment.reassign', $rideId), ['driver_id' => $driverB->id])->assertOk();
        $this->assertSame($driverB->id, $parcel->fresh()->driver_id);

        // Delivery-only staff can't open the parcel page.
        $deliveryOnly = $this->makeAdmin(['delivery'], ['view delivery', 'assign ride']);
        $this->actingAs($deliveryOnly)->get(route('dashboard.parcel-assignment.index'))->assertForbidden();
        // Their delivery page is food mode (no parcel form shown).
        $this->actingAs($deliveryOnly)->get(route('dashboard.delivery-assignment.index'))->assertOk()->assertSee('Manual Delivery Assignment');
    }

    public function test_rides_workspace_shows_parcels_and_delivery_workspace_does_not(): void
    {
        $this->useParcelType($this->carType);
        $customer = $this->makeUser('user');
        $rideId = $this->bookParcel($customer)->assertOk()->json('ride_id');

        $admin = $this->makeAdmin(['rides', 'delivery'], ['view custom rides', 'view delivery', 'view complain', 'view live tracking']);

        $ridesQueue = collect($this->actingAs($admin)->getJson(route('dashboard.custom-rides.queue', ['window' => 'all']))->assertOk()->json('rides'));
        $row = $ridesQueue->firstWhere('id', $rideId);
        $this->assertSame('parcel', $row['type_key']);
        $this->assertSame('Ali', $row['sender_name']);

        $onlyTaxi = collect($this->actingAs($admin)->getJson(route('dashboard.custom-rides.queue', ['window' => 'all', 'type' => 'ride']))->json('rides'));
        $this->assertNull($onlyTaxi->firstWhere('id', $rideId));

        $deliveryQueue = collect($this->actingAs($admin)->getJson(route('dashboard.delivery.queue', ['window' => 'all']))->json('rides'));
        $this->assertNull($deliveryQueue->firstWhere('id', $rideId));

        $this->actingAs($admin)->get(route('dashboard.custom-rides.index'))->assertOk()->assertSee('Parcel');

        // Parcel complaints show in Rides, not Delivery.
        Complain::create(['user_id' => $customer->id, 'name' => 'PC Parcel Complaint', 'subject' => 's', 'complain_text' => 't', 'service' => 'parcel', 'ride_id' => $rideId]);
        $this->actingAs($admin)->withSession(['workspace' => 'rides'])->get(route('dashboard.complains.index'))->assertOk()->assertSee('PC Parcel Complaint');
        $this->actingAs($admin)->withSession(['workspace' => 'delivery'])->get(route('dashboard.complains.index'))->assertOk()->assertDontSee('PC Parcel Complaint');

        // Active parcel appears on the Rides live map.
        $driver = $this->makeDriver($this->carType);
        Ride::whereKey($rideId)->update(['driver_id' => $driver->id, 'status' => 'started']);
        $active = collect($this->actingAs($admin)->getJson(route('dashboard.custom-rides.live-tracking'))->assertOk()->json('activeRides'));
        $this->assertSame('parcel', $active->firstWhere('ride_id', $rideId)['ride_type']);
    }

    public function test_driver_performance_counts_parcels(): void
    {
        $this->useParcelType($this->carType);
        $driver = $this->makeDriver($this->carType);
        $ride = Ride::find($this->bookParcel($this->makeUser('user'))->assertOk()->json('ride_id'));
        $ride->forceFill(['driver_id' => $driver->id, 'status' => 'completed', 'accepted_at' => now()->subMinutes(20), 'started_at' => now()->subMinutes(15), 'completed_at' => now()])->save();

        $row = PerformanceReportService::build('ride', now()->subDay(), now()->addDay())->firstWhere('driver_id', $driver->id);
        $this->assertSame(1, $row['completed_jobs']);
    }
}
