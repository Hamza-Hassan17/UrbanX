<?php

namespace Tests\Feature;

use App\Models\Complain;
use App\Models\DriverVehicle;
use App\Models\Restaurant;
use App\Models\RestaurantItem;
use App\Models\RestaurantItemOption;
use App\Models\RestaurantItemOptionGroup;
use App\Models\RestaurantMenu;
use App\Models\RestaurantOrder;
use App\Models\Ride;
use App\Models\RideOffer;
use App\Models\SystemSetting;
use App\Models\TermsAndCondition;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\VehicleType;
use App\Services\DriverPresenceService;
use App\Services\OfferDispatchService;
use App\Services\PerformanceReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Batch 3 -- complaints, add-ons, minimum order, online/offline,
 * performance reports, no-driver handling. Runs against the local DB
 * inside a rolled-back transaction, like the other feature tests.
 */
class Batch3Test extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, array{title: string, users: array}> */
    private array $pushes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);

        // Record pushes instead of sending them.
        $test = $this;
        $this->app->instance('notificationService', new class($test) {
            public function __construct(private $test) {}
            public function notifyUsers($users, $title, $body = null, ...$rest)
            {
                $this->test->recordPush($users, $title);
            }
            public function __call($name, $args) {}
        });

        SystemSetting::query()->update(['no_driver_handling' => false, 'max_reoffer_attempts' => 5, 'auto_offline_minutes' => 10]);
    }

    public function recordPush($users, string $title): void
    {
        $ids = collect($users)->map(fn ($u) => is_object($u) ? $u->id : $u)->all();
        $this->pushes[] = ['title' => $title, 'users' => $ids];
    }

    // ------------------------------------------------------------ helpers

    private function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'username' => 'b3_' . uniqid(),
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
        $role = Role::firstOrCreate(['name' => 'b3_role_' . uniqid(), 'guard_name' => 'web']);
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
        $type->forceFill([
            'name' => 'B3 ' . ($delivery ? 'Bike' : 'Car') . ' ' . uniqid(),
            'is_delivery' => $delivery ? '1' : '0',
            'is_active' => 'active',
        ])->save();

        return $type;
    }

    private function makeDriver(VehicleType $type, array $attributes = []): User
    {
        $driver = $this->makeUser('driver', array_merge(['lat' => '10.0000', 'lang' => '10.0000'], $attributes));
        $driver->forceFill(['driver_status' => 'available'] + array_intersect_key($attributes, array_flip(['online_status', 'last_location_at', 'online_status_changed_at'])))->save();
        (new DriverVehicle())->forceFill([
            'driver_id' => $driver->id,
            'vehicle_type_id' => $type->id,
            'vehicle_name' => 'Test Vehicle',
        ])->save();

        return $driver->fresh();
    }

    private function makeRide(VehicleType $type, User $passenger, array $attributes = []): Ride
    {
        $ride = new Ride();
        $ride->forceFill(array_merge([
            'passenger_id' => $passenger->id,
            'vehicle_type_id' => $type->id,
            'pickup_latitude' => '10.0000',
            'pickup_longitude' => '10.0000',
            'dropoff_latitude' => '10.0100',
            'dropoff_longitude' => '10.0100',
            'distance_km' => 1,
            'subtotal' => 300,
            'total_fare' => 300,
            'requested_at' => now(),
            'status' => 'requested',
            'ride_type' => $type->is_delivery === '1' ? 'delivery' : 'ride',
        ], $attributes))->save();

        return $ride->fresh();
    }

    /** Restaurant with one item: required "Size" (single) + optional "Extras" (multiple, max 2). */
    private function makeRestaurant(?float $minimum = null): array
    {
        $owner = $this->makeUser('restaurant');
        $restaurant = new Restaurant();
        $restaurant->forceFill([
            'user_id' => $owner->id,
            'name' => 'B3 Grill ' . uniqid(),
            'address' => 'Test Street',
            'latitude' => '24.8600',
            'longitude' => '67.0000',
            'is_open' => 'open',
            'is_active' => 'active',
            'is_approved' => true,
            'minimum_order_amount' => $minimum,
        ])->save();

        $menu = new RestaurantMenu();
        $menu->forceFill(['restaurant_id' => $restaurant->id, 'name' => 'Burgers', 'is_active' => 'active'])->save();

        $item = new RestaurantItem();
        $item->forceFill([
            'restaurant_id' => $restaurant->id,
            'restaurant_menu_id' => $menu->id,
            'name' => 'Zinger Burger',
            'price' => 650,
            'discount_price' => 600,
            'is_available' => '1',
            'is_active' => 'active',
        ])->save();

        $size = RestaurantItemOptionGroup::create([
            'restaurant_item_id' => $item->id, 'name' => 'Size', 'is_required' => true,
            'selection_type' => 'single', 'min_select' => 1, 'max_select' => 1, 'sort_order' => 0,
        ]);
        $regular = $size->options()->create(['name' => 'Regular', 'price_adjustment' => 0, 'is_available' => true]);
        $large = $size->options()->create(['name' => 'Large', 'price_adjustment' => 150, 'is_available' => true]);

        $extras = RestaurantItemOptionGroup::create([
            'restaurant_item_id' => $item->id, 'name' => 'Extras', 'is_required' => false,
            'selection_type' => 'multiple', 'min_select' => 0, 'max_select' => 2, 'sort_order' => 1,
        ]);
        $cheese = $extras->options()->create(['name' => 'Extra Cheese', 'price_adjustment' => 80, 'is_available' => true]);
        $jalapeno = $extras->options()->create(['name' => 'Jalapenos', 'price_adjustment' => 50, 'is_available' => true]);
        $sauce = $extras->options()->create(['name' => 'Sauce', 'price_adjustment' => 30, 'is_available' => false]);

        $plain = new RestaurantItem();
        $plain->forceFill([
            'restaurant_id' => $restaurant->id,
            'restaurant_menu_id' => $menu->id,
            'name' => 'Plain Fries',
            'price' => 200,
            'discount_price' => 200,
            'is_available' => '1',
            'is_active' => 'active',
        ])->save();

        if (!VehicleType::where('is_delivery', '1')->exists()) {
            $this->vehicleType(true);
        }

        return compact('owner', 'restaurant', 'menu', 'item', 'plain', 'size', 'regular', 'large', 'extras', 'cheese', 'jalapeno', 'sauce');
    }

    private function addToCart(User $customer, array $r, RestaurantItem $item, int $qty, array $optionIds = [])
    {
        Sanctum::actingAs($customer, ['*']);
        $payload = ['restaurant_id' => $r['restaurant']->id, 'menu_item_id' => $item->id, 'quantity' => $qty];
        if ($optionIds) {
            $payload['option_ids'] = $optionIds;
        }

        return $this->postJson('/api/restaurant/customer/add-to-cart', $payload);
    }

    private function placeOrder(User $customer, int $cartId)
    {
        Sanctum::actingAs($customer, ['*']);

        return $this->postJson('/api/restaurant/customer/place-order', [
            'cart_id' => $cartId,
            'payment_method' => 'cod',
            'delivery_lat' => '24.8650',
            'delivery_lang' => '67.0050',
        ]);
    }

    private function enableNoDriverHandling(bool $on = true): void
    {
        SystemSetting::query()->update(['no_driver_handling' => $on]);
    }

    // ------------------------------------------------- workspace fix

    public function test_delivery_only_user_can_open_shared_pages(): void
    {
        $admin = $this->makeAdmin(['delivery'], ['view live tracking', 'view ride', 'view vehicle type']);
        $parcel = $this->makeRide($this->vehicleType(true), $this->makeUser('user'));

        $this->actingAs($admin)->get(route('dashboard.live-tracking.index', ['scope' => 'delivery']))->assertOk();
        $this->actingAs($admin)->get(route('dashboard.vehicle-types.index', ['scope' => 'delivery']))->assertOk();
        $this->actingAs($admin)->get(route('dashboard.rides.show', $parcel->id))->assertOk();
        // Still Rides-only:
        $this->actingAs($admin)->get(route('dashboard.rides.index'))->assertForbidden();
    }

    // ------------------------------------------------- Part 1: complaints

    public function test_complaint_linked_to_own_order_sets_service_and_flags_order(): void
    {
        $r = $this->makeRestaurant();
        $customer = $this->makeUser('user');
        $cartId = $this->addToCart($customer, $r, $r['plain'], 2)->assertOk()->json('cart_id');
        $order = $this->placeOrder($customer, $cartId)->assertOk()->json('order');

        Sanctum::actingAs($customer, ['*']);
        $response = $this->postJson('/api/submit-complain', [
            'name' => 'Ali', 'subject' => 'Cold food', 'complain_text' => 'It was cold',
            'restaurant_order_id' => $order['id'],
            'service' => 'ride', // ignored when linked
        ])->assertOk();

        $this->assertSame('food', $response->json('complain.service'));
        $this->assertSame($order['ride_id'], $response->json('complain.ride_id'));

        $this->getJson('/api/restaurant/customer/order-details/' . $order['id'])
            ->assertOk()->assertJsonPath('order.has_complaint', true);
        $orders = collect($this->getJson('/api/restaurant/customer/orders')->assertOk()->json('orders'));
        $this->assertTrue($orders->firstWhere('id', $order['id'])['has_complaint']);
        $history = collect($this->getJson('/api/customer/get-ride-history')->assertOk()->json('ride_history'));
        $this->assertTrue($history->firstWhere('id', $order['ride_id'])['has_complaint']);
    }

    public function test_complaint_rejects_someone_elses_ride_and_both_links(): void
    {
        $type = $this->vehicleType(false);
        $ride = $this->makeRide($type, $this->makeUser('user'));
        $stranger = $this->makeUser('user');

        Sanctum::actingAs($stranger, ['*']);
        $this->postJson('/api/submit-complain', [
            'name' => 'X', 'subject' => 'Y', 'complain_text' => 'Z', 'ride_id' => $ride->id,
        ])->assertStatus(422)->assertJsonPath('message', 'This ride was not found on your account.');

        $this->postJson('/api/submit-complain', [
            'name' => 'X', 'subject' => 'Y', 'complain_text' => 'Z', 'ride_id' => $ride->id, 'restaurant_order_id' => 1,
        ])->assertStatus(400);

        // General complaint (no link) still works as before.
        $this->postJson('/api/submit-complain', ['name' => 'X', 'subject' => 'Y', 'complain_text' => 'Z'])->assertOk();
    }

    public function test_complaint_on_taxi_and_parcel_rides_get_correct_service(): void
    {
        $passenger = $this->makeUser('user');
        $taxi = $this->makeRide($this->vehicleType(false), $passenger);
        $parcel = $this->makeRide($this->vehicleType(true), $passenger);

        Sanctum::actingAs($passenger, ['*']);
        $this->postJson('/api/submit-complain', ['name' => 'A', 'subject' => 'B', 'complain_text' => 'C', 'ride_id' => $taxi->id])
            ->assertOk()->assertJsonPath('complain.service', 'ride');
        $this->postJson('/api/submit-complain', ['name' => 'A', 'subject' => 'B', 'complain_text' => 'C', 'ride_id' => $parcel->id])
            ->assertOk()->assertJsonPath('complain.service', 'parcel');
    }

    public function test_complaints_show_in_the_right_workspace_with_link(): void
    {
        $passenger = $this->makeUser('user');
        $parcel = $this->makeRide($this->vehicleType(true), $passenger);
        $food = Complain::create(['user_id' => $passenger->id, 'name' => 'B3 Parcel Complaint', 'subject' => 's', 'complain_text' => 't', 'service' => 'parcel', 'ride_id' => $parcel->id]);
        Complain::create(['user_id' => $passenger->id, 'name' => 'B3 Taxi Complaint', 'subject' => 's', 'complain_text' => 't', 'service' => 'ride']);

        $delivery = $this->makeAdmin(['delivery'], ['view complain']);
        $this->actingAs($delivery)->withSession(['workspace' => 'delivery'])->get(route('dashboard.complains.index'))
            ->assertOk()->assertSee('B3 Parcel Complaint')->assertDontSee('B3 Taxi Complaint')->assertSee('Parcel #' . $parcel->id);

        $this->actingAs($delivery)->withSession(['workspace' => 'delivery'])->get(route('dashboard.complains.show', $food->id))
            ->assertOk()->assertSee('Linked To')->assertSee(route('dashboard.rides.show', $parcel->id));

        $platform = $this->makeAdmin(['platform'], ['view complain']);
        $this->actingAs($platform)->withSession(['workspace' => 'platform'])->get(route('dashboard.complains.index'))
            ->assertOk()->assertSee('B3 Parcel Complaint')->assertSee('B3 Taxi Complaint');
        $this->actingAs($platform)->withSession(['workspace' => 'platform'])->get(route('dashboard.complains.index', ['service' => 'ride']))
            ->assertOk()->assertSee('B3 Taxi Complaint')->assertDontSee('B3 Parcel Complaint');
    }

    // ------------------------------------------------- Part 2: add-ons

    public function test_menu_response_includes_option_groups(): void
    {
        $r = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser('user'), ['*']);

        $items = collect($this->getJson('/api/restaurant/customer/menu-items/' . $r['menu']->id)->assertOk()->json('menu_items'));
        $zinger = $items->firstWhere('id', $r['item']->id);
        $this->assertCount(2, $zinger['option_groups']);
        $this->assertSame('Size', $zinger['option_groups'][0]['name']);
        $this->assertFalse(collect($zinger['option_groups'][1]['options'])->firstWhere('name', 'Sauce')['is_available']);
        $this->assertSame([], $items->firstWhere('id', $r['plain']->id)['option_groups']);
    }

    public function test_add_to_cart_enforces_group_rules_and_availability(): void
    {
        $r = $this->makeRestaurant();
        $customer = $this->makeUser('user');

        $this->addToCart($customer, $r, $r['item'], 1)
            ->assertStatus(400)->assertJsonPath('message', 'Please choose an option for "Size".');
        $this->addToCart($customer, $r, $r['item'], 1, [$r['regular']->id, $r['large']->id])
            ->assertStatus(400)->assertJsonPath('message', 'You can choose only one option for "Size".');
        $this->addToCart($customer, $r, $r['item'], 1, [$r['large']->id, $r['cheese']->id, $r['jalapeno']->id, $r['sauce']->id])
            ->assertStatus(400);
        $this->addToCart($customer, $r, $r['item'], 1, [$r['large']->id, $r['sauce']->id])
            ->assertStatus(400)->assertJsonPath('message', '"Sauce" is currently unavailable. Please choose another option.');
        $this->addToCart($customer, $r, $r['plain'], 1, [$r['large']->id])
            ->assertStatus(400)->assertJsonPath('message', 'Plain Fries has no add-ons to choose from.');

        $r['item']->forceFill(['is_available' => '0'])->save();
        $this->addToCart($customer, $r, $r['item'], 1, [$r['large']->id])
            ->assertStatus(400)->assertJsonPath('message', 'Zinger Burger is currently unavailable.');
    }

    public function test_required_and_optional_addons_are_priced_snapshotted_and_shown_to_restaurant(): void
    {
        $r = $this->makeRestaurant();
        $customer = $this->makeUser('user');

        // 600 base + 150 Large + 80 Cheese = 830 each
        $cartId = $this->addToCart($customer, $r, $r['item'], 2, [$r['large']->id, $r['cheese']->id])->assertOk()->json('cart_id');
        // Same choices merge into the same line; different choices make a new line.
        $this->addToCart($customer, $r, $r['item'], 1, [$r['cheese']->id, $r['large']->id])->assertOk();
        $this->addToCart($customer, $r, $r['item'], 1, [$r['regular']->id])->assertOk();
        // Item without add-ons behaves as before.
        $this->addToCart($customer, $r, $r['plain'], 1)->assertOk();

        $cart = $this->getJson('/api/restaurant/customer/cart-details/' . $cartId)->assertOk()->json('cart');
        $lines = collect($cart['cart_items']);
        $this->assertCount(3, $lines);
        $largeLine = $lines->first(fn ($l) => $l['options_signature'] === collect([$r['large']->id, $r['cheese']->id])->sort()->implode(','));
        $this->assertSame(3, $largeLine['quantity']);
        $this->assertEquals(830, $largeLine['price_per_item']);
        $this->assertEquals(2490 + 600 + 200, $cart['subtotal']);

        $order = $this->placeOrder($customer, $cartId)->assertOk()->json('order');
        $this->assertEquals(3290, $order['subtotal']);
        $orderLine = collect($order['items'])->firstWhere('price_per_item', '830.00');
        $this->assertSame('Zinger Burger', $orderLine['item_name']);
        $this->assertEquals(600, $orderLine['base_price']);
        $this->assertSame(['Size', 'Extras'], array_column($orderLine['selected_options'], 'group_name'));
        $this->assertSame(['Large', 'Extra Cheese'], array_column($orderLine['selected_options'], 'option_name'));

        // The delivery ride's fare is the server delivery fee, not app-sent values.
        $orderModel = RestaurantOrder::find($order['id']);
        $this->assertEquals((float) $orderModel->delivery_fee, (float) Ride::find($orderModel->ride_id)->total_fare);

        // Later menu edits never change the past order.
        $r['large']->update(['name' => 'Huge', 'price_adjustment' => 999]);

        Sanctum::actingAs($r['owner'], ['*']);
        $partnerOrder = $this->getJson('/api/restaurant/order-details/' . $order['id'])->assertOk()->json('order');
        $partnerLine = collect($partnerOrder['items'])->firstWhere('price_per_item', '830.00');
        $this->assertSame('Large', $partnerLine['selected_options'][0]['option_name']);
        $this->assertEquals(150, $partnerLine['selected_options'][0]['price_adjustment']);

        // Admin restaurant page and the delivery ride page show the chosen options.
        $admin = $this->makeAdmin(['delivery'], ['view restaurant', 'view ride']);
        $this->actingAs($admin)->get(route('dashboard.restaurants.show', $r['restaurant']->id))
            ->assertOk()->assertSee('Size: Large')->assertSee('Extras: Extra Cheese');
        $this->actingAs($admin)->get(route('dashboard.rides.show', $orderModel->ride_id))
            ->assertOk()->assertSee('Size: Large');
    }

    public function test_place_order_rejects_an_option_that_went_out_of_stock_after_carting(): void
    {
        $r = $this->makeRestaurant();
        $customer = $this->makeUser('user');
        $cartId = $this->addToCart($customer, $r, $r['item'], 1, [$r['large']->id, $r['jalapeno']->id])->assertOk()->json('cart_id');

        $r['jalapeno']->update(['is_available' => false]);

        $this->placeOrder($customer, $cartId)
            ->assertStatus(400)->assertJsonPath('message', '"Jalapenos" is currently unavailable. Please choose another option.');
        $this->assertSame(0, RestaurantOrder::where('customer_id', $customer->id)->count());
    }

    public function test_partner_can_manage_option_groups_and_toggle_availability(): void
    {
        $r = $this->makeRestaurant();
        Sanctum::actingAs($r['owner'], ['*']);

        $group = $this->postJson("/api/restaurant/menu-item/{$r['plain']->id}/option-group/add", [
            'name' => 'Dips', 'is_required' => false, 'selection_type' => 'multiple', 'max_select' => 2,
            'options' => [['name' => 'Ketchup', 'price_adjustment' => 0], ['name' => 'Mayo', 'price_adjustment' => 20]],
        ])->assertOk()->json('option_group');
        $this->assertCount(2, $group['options']);
        $this->assertSame(0, $group['min_select']);

        $this->postJson("/api/restaurant/option-group/update/{$group['id']}", [
            'name' => 'Dips', 'is_required' => true, 'selection_type' => 'multiple', 'min_select' => 3, 'max_select' => 2,
        ])->assertStatus(400)->assertJsonPath('message', 'Minimum selections cannot be more than maximum selections.');

        $option = $this->postJson("/api/restaurant/option-group/{$group['id']}/option/add", ['name' => 'Garlic', 'price_adjustment' => 30])
            ->assertOk()->json('option');
        $this->postJson("/api/restaurant/option/{$option['id']}/toggle-availability")->assertOk()->assertJsonPath('is_available', false);
        $this->postJson("/api/restaurant/menu-item/{$r['plain']->id}/toggle-availability", ['is_available' => false])
            ->assertOk()->assertJsonPath('is_available', '0');
        $this->postJson("/api/restaurant/option/delete/{$option['id']}")->assertOk();

        $listed = $this->getJson("/api/restaurant/menu-item/{$r['plain']->id}/option-groups")->assertOk()->json('option_groups');
        $this->assertCount(2, $listed[0]['options']);

        // Another owner can't touch this restaurant's items.
        $other = $this->makeRestaurant();
        Sanctum::actingAs($other['owner'], ['*']);
        $this->postJson("/api/restaurant/menu-item/{$r['plain']->id}/toggle-availability")->assertNotFound();
        $this->postJson("/api/restaurant/option-group/delete/{$group['id']}")->assertNotFound();
    }

    public function test_admin_item_options_page_manages_groups(): void
    {
        $r = $this->makeRestaurant();
        $admin = $this->makeAdmin(['delivery'], ['view restaurant', 'update restaurant']);

        $this->actingAs($admin)->get(route('dashboard.restaurants.items.options', $r['item']->id))
            ->assertOk()->assertSee('Size')->assertSee('Extra Cheese');

        $this->actingAs($admin)->post(route('dashboard.restaurants.option-groups.store', $r['item']->id), [
            'name' => 'Drink', 'is_required' => '1', 'selection_type' => 'single', 'sort_order' => 2,
        ])->assertRedirect();
        $this->assertTrue($r['item']->optionGroups()->where('name', 'Drink')->where('min_select', 1)->exists());

        $this->actingAs($admin)->post(route('dashboard.restaurants.options.toggle', $r['cheese']->id))->assertRedirect();
        $this->assertFalse($r['cheese']->fresh()->is_available);
    }

    // ------------------------------------------------- Part 3: minimum order

    public function test_order_below_minimum_is_rejected_with_shortfall(): void
    {
        $r = $this->makeRestaurant(500);
        $customer = $this->makeUser('user');
        $cartId = $this->addToCart($customer, $r, $r['plain'], 1)->assertOk()->json('cart_id'); // Rs 200... then 350 below

        $r['plain']->forceFill(['price' => 350, 'discount_price' => 350])->save();

        $this->placeOrder($customer, $cartId)
            ->assertStatus(400)
            ->assertJsonPath('message', 'Add Rs 150 more to reach the minimum order of Rs 500.')
            ->assertJsonPath('shortfall', 150);

        $this->getJson('/api/restaurant/customer/cart-details/' . $cartId)->assertOk()
            ->assertJsonPath('cart.minimum_order_amount', '500.00');

        $this->addToCart($customer, $r, $r['plain'], 1)->assertOk();
        $this->placeOrder($customer, $cartId)->assertOk();
    }

    public function test_restaurant_details_include_minimum_and_partner_update_keeps_it(): void
    {
        $r = $this->makeRestaurant(500);
        Sanctum::actingAs($this->makeUser('user'), ['*']);
        $this->getJson('/api/restaurant/customer/restaurant-details/' . $r['restaurant']->id)
            ->assertOk()->assertJsonPath('restaurant.minimum_order_amount', '500.00');

        $category = new \App\Models\RestaurantCategory();
        $category->forceFill(['name' => 'B3 Cat', 'is_active' => 'active'])->save();
        $base = ['restaurant_category_id' => $category->id, 'name' => 'B3 Grill', 'address' => 'Test Street', 'weekly_schedule' => '{}'];

        Sanctum::actingAs($r['owner'], ['*']);
        $this->postJson('/api/restaurant/shop/update', $base)->assertOk();
        $this->assertEquals(500, (float) $r['restaurant']->fresh()->minimum_order_amount, 'older Partner builds must not clear it');

        $this->postJson('/api/restaurant/shop/update', $base + ['minimum_order_amount' => 300])->assertOk()
            ->assertJsonPath('restaurant.minimum_order_amount', '300.00');
        $this->postJson('/api/restaurant/shop/update', $base + ['minimum_order_amount' => ''])->assertOk();
        $this->assertNull($r['restaurant']->fresh()->minimum_order_amount);
    }

    // ------------------------------------------------- Part 4: online/offline

    public function test_go_online_offline_and_offline_driver_gets_no_jobs(): void
    {
        $type = $this->vehicleType(false);
        $driver = $this->makeDriver($type);
        $ride = $this->makeRide($type, $this->makeUser('user'));
        Sanctum::actingAs($driver, ['*']);

        $this->getJson('/api/driver/online-status')->assertOk()
            ->assertJsonPath('online_status', 'online')->assertJsonPath('uses_online_toggle', false);

        $this->postJson('/api/driver/go-offline')->assertOk()->assertJsonPath('online_status', 'offline')->assertJsonPath('presence', 'offline');
        $this->getJson('/api/driver/get-rides')->assertOk()->assertJsonPath('rides', [])->assertJsonPath('online_status', 'offline');
        $this->postJson('/api/driver/accept-ride', ['ride_id' => $ride->id])
            ->assertForbidden()->assertJsonPath('message', 'You are offline. Go online to accept jobs.');
        $this->assertNotEquals($driver->id, OfferDispatchService::findNextEligibleDriver($ride)?->id);

        $this->postJson('/api/driver/go-online', ['latitude' => 10, 'longitude' => 10])->assertOk()
            ->assertJsonPath('online_status', 'online')->assertJsonPath('uses_online_toggle', true);
        $this->assertSame($driver->id, OfferDispatchService::findNextEligibleDriver($ride)?->id);
        $this->assertTrue(\App\Models\DriverOnlineSession::where('driver_id', $driver->id)->whereNull('ended_at')->exists());

        $this->getJson('/api/driver/get-rides')->assertOk()->assertJsonPath('rides.0.id', $ride->id);
    }

    public function test_delivery_app_toggle_and_offline_rider_cannot_accept(): void
    {
        $type = $this->vehicleType(true);
        $rider = $this->makeDriver($type);
        $parcel = $this->makeRide($type, $this->makeUser('user'));
        Sanctum::actingAs($rider, ['*']);

        $this->postJson('/api/restaurant/delivery/go-offline')->assertOk()->assertJsonPath('online_status', 'offline');
        $this->getJson('/api/restaurant/delivery/get-rides')->assertOk()->assertJsonPath('online_status', 'offline');
        $this->postJson('/api/restaurant/delivery/accept-ride', ['ride_id' => $parcel->id, 'eta_minutes' => 10])->assertForbidden();
        $this->postJson('/api/restaurant/delivery/go-online')->assertOk()->assertJsonPath('online_status', 'online');
    }

    public function test_legacy_driver_keeps_offers_and_auto_offline_only_hits_toggle_users(): void
    {
        $type = $this->vehicleType(false);
        $legacy = $this->makeDriver($type, ['last_location_at' => now()->subHours(3)]);
        $toggle = $this->makeDriver($type, ['online_status' => 'online', 'last_location_at' => now()->subMinutes(30), 'online_status_changed_at' => now()->subHour()]);
        $fresh = $this->makeDriver($type, ['online_status' => 'online', 'last_location_at' => now()->subMinutes(2)]);
        $ride = $this->makeRide($type, $this->makeUser('user'));

        $this->assertNull($legacy->online_status);
        $this->assertContains(OfferDispatchService::findNextEligibleDriver($ride)?->id, [$legacy->id, $toggle->id, $fresh->id]);

        $switched = DriverPresenceService::autoOfflineStale(10);

        $this->assertGreaterThanOrEqual(1, $switched);
        $this->assertSame('offline', $toggle->fresh()->online_status);
        $this->assertNull($legacy->fresh()->online_status);
        $this->assertSame('online', $fresh->fresh()->online_status);
        $this->assertContains('You are now offline', array_column($this->pushes, 'title'));

        // Legacy driver alone still gets the offer.
        $fresh->forceFill(['online_status' => 'offline'])->save();
        $this->assertSame($legacy->id, OfferDispatchService::findNextEligibleDriver($ride)?->id);
    }

    public function test_location_ping_records_last_location(): void
    {
        $driver = $this->makeDriver($this->vehicleType(false), ['online_status' => 'online', 'last_location_at' => now()->subHour()]);
        Sanctum::actingAs($driver, ['*']);

        $this->postJson('/api/driver/location/ping', ['latitude' => 10.001, 'longitude' => 10.001])->assertOk();
        $this->assertTrue($driver->fresh()->last_location_at->gt(now()->subMinute()));
    }

    public function test_admin_screens_show_presence(): void
    {
        $type = $this->vehicleType(false);
        $offline = $this->makeDriver($type, ['online_status' => 'offline']);
        $admin = $this->makeAdmin(['rides', 'delivery'], ['view driver', 'view live tracking', 'view delivery', 'assign ride']);

        $this->actingAs($admin)->get(route('dashboard.drivers.index'))->assertOk()->assertSee('Presence');
        $this->actingAs($admin)->get(route('dashboard.live-tracking.index', ['scope' => 'ride']))->assertOk()->assertSee('Offline');
        $this->actingAs($admin)->withSession(['workspace' => 'rides'])->get(route('dashboard'))->assertOk()->assertSee('Drivers Offline');
        $this->actingAs($admin)->get(route('dashboard.delivery-assignment.index'))->assertOk();
        $stats = $this->actingAs($admin)->getJson(route('dashboard.delivery-assignment.stats'))->assertOk()->json('stats');
        $this->assertArrayHasKey('offline', $stats);
        $this->assertNotNull($offline);
    }

    // ------------------------------------------------- Part 5: performance

    public function test_driver_performance_metrics_page_and_csv(): void
    {
        $type = $this->vehicleType(false);
        $driver = $this->makeDriver($type);
        $passenger = $this->makeUser('user');

        foreach (['accepted', 'rejected', 'expired', 'pending'] as $status) {
            $ride = $this->makeRide($type, $passenger);
            RideOffer::create(['ride_id' => $ride->id, 'driver_id' => $driver->id, 'proposed_price' => 300, 'eta_minutes' => 5, 'status' => $status, 'offered_at' => now()]);
        }
        $this->makeRide($type, $passenger, [
            'driver_id' => $driver->id, 'status' => 'completed',
            'accepted_at' => now()->subMinutes(30), 'started_at' => now()->subMinutes(25), 'completed_at' => now()->subMinutes(5),
        ]);
        $this->makeRide($type, $passenger, [
            'driver_id' => $driver->id, 'status' => 'cancelled', 'status_updated_by_role' => 'driver', 'cancelled_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('driver_reviews')->insert([
            'driver_id' => $driver->id, 'review_by' => $passenger->id, 'rating' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = PerformanceReportService::build('ride', now()->subDay(), now()->addDay())->firstWhere('driver_id', $driver->id);
        $this->assertSame(4, $row['offers_received']);
        $this->assertSame(1, $row['offers_accepted']);
        $this->assertSame(33.3, $row['acceptance_rate']);
        $this->assertSame(1, $row['completed_jobs']);
        $this->assertSame(1, $row['cancellations']);
        $this->assertEquals(5.0, $row['avg_pickup_minutes']);
        $this->assertEquals(20.0, $row['avg_trip_minutes']);
        $this->assertEquals(4.0, $row['avg_rating']);
        $this->assertNull($row['online_hours']);

        // Rider report doesn't include taxi drivers.
        $this->assertNull(PerformanceReportService::build('delivery', now()->subDay(), now()->addDay())->firstWhere('driver_id', $driver->id));

        $finance = $this->makeAdmin(['rides', 'delivery'], ['view performance reports']);
        $this->actingAs($finance)->get(route('dashboard.driver-performance.index', ['sort' => 'acceptance_rate', 'dir' => 'asc']))
            ->assertOk()->assertSee($driver->name);
        $this->actingAs($finance)->get(route('dashboard.rider-performance.index'))->assertOk();
        $csv = $this->actingAs($finance)->get(route('dashboard.driver-performance.export'));
        $csv->assertOk();
        $this->assertStringContainsString('Acceptance Rate (%)', $csv->streamedContent());

        $nobody = $this->makeAdmin(['rides'], []);
        $this->actingAs($nobody)->get(route('dashboard.driver-performance.index'))->assertForbidden();
    }

    // ------------------------------------------------- Part 6: no driver found

    public function test_setting_off_keeps_current_behaviour(): void
    {
        $type = $this->vehicleType(false); // no drivers have this type
        $ride = $this->makeRide($type, $this->makeUser('user'));

        $this->assertFalse(OfferDispatchService::offerToNextDriver($ride));
        $this->assertSame('requested', $ride->fresh()->status);
    }

    public function test_setting_on_marks_no_driver_found_and_customer_can_retry_or_cancel(): void
    {
        $this->enableNoDriverHandling();
        $type = $this->vehicleType(false);
        $passenger = $this->makeUser('user');
        $ride = $this->makeRide($type, $passenger);

        $this->assertFalse(OfferDispatchService::offerToNextDriver($ride));
        $this->assertSame('no_driver_found', $ride->fresh()->status);
        $this->assertContains('No drivers available right now', array_column($this->pushes, 'title'));

        // A driver was offered before and expired -- retry must be able to offer them again.
        $driver = $this->makeDriver($type);
        $old = RideOffer::create(['ride_id' => $ride->id, 'driver_id' => $driver->id, 'proposed_price' => 300, 'eta_minutes' => 0, 'status' => 'expired', 'offered_at' => now()]);

        Sanctum::actingAs($this->makeUser('user'), ['*']);
        $this->postJson('/api/customer/retry-dispatch', ['ride_id' => $ride->id])->assertForbidden();

        Sanctum::actingAs($passenger, ['*']);
        $this->postJson('/api/customer/retry-dispatch', ['ride_id' => $ride->id])
            ->assertOk()->assertJsonPath('status', 'requested')->assertJsonPath('offered_to_driver', true);
        $this->assertNotNull($old->fresh()->superseded_at);
        $this->assertTrue(RideOffer::where('ride_id', $ride->id)->where('driver_id', $driver->id)->where('status', 'pending')->exists());

        $this->postJson('/api/customer/retry-dispatch', ['ride_id' => $ride->id])
            ->assertStatus(400)->assertJsonPath('message', 'This ride is not waiting for a retry.');

        $ride->forceFill(['status' => 'no_driver_found'])->save();
        $this->postJson('/api/customer/cancel-ride', ['ride_id' => $ride->id])->assertOk();
        $this->assertSame('cancelled', $ride->fresh()->status);
    }

    public function test_setting_on_exhausted_attempts_and_parcels_but_never_food(): void
    {
        $this->enableNoDriverHandling();
        $passenger = $this->makeUser('user');

        $taxiType = $this->vehicleType(false);
        $this->makeDriver($taxiType);
        $tired = $this->makeRide($taxiType, $passenger, ['reoffer_attempts' => 5]);
        OfferDispatchService::offerToNextDriver($tired);
        $this->assertSame('no_driver_found', $tired->fresh()->status);

        $bikeType = $this->vehicleType(true);
        $parcel = $this->makeRide($bikeType, $passenger);
        OfferDispatchService::offerToNextDriver($parcel);
        $this->assertSame('no_driver_found', $parcel->fresh()->status);

        $foodRide = $this->makeRide($bikeType, $passenger);
        $r = $this->makeRestaurant();
        $order = new RestaurantOrder();
        $order->forceFill([
            'restaurant_id' => $r['restaurant']->id, 'customer_id' => $passenger->id, 'order_number' => 'B3-' . uniqid(),
            'subtotal' => 500, 'total_price' => 600, 'delivery_lat' => '24.86', 'delivery_lang' => '67.00',
            'payment_method' => 'cod', 'payment_status' => 'unpaid', 'status' => 'accepted', 'ride_id' => $foodRide->id,
        ])->save();
        OfferDispatchService::offerToNextDriver($foodRide);
        $this->assertSame('requested', $foodRide->fresh()->status);

        // Scan path too: an old unassigned parcel with nobody eligible.
        $oldParcel = $this->makeRide($bikeType, $passenger, ['requested_at' => now()->subHour()]);
        $this->artisan('app:scan-stale-jobs')->assertSuccessful();
        $this->assertSame('no_driver_found', $oldParcel->fresh()->status);
    }

    public function test_admin_can_assign_a_no_driver_found_parcel(): void
    {
        $type = $this->vehicleType(true);
        $rider = $this->makeDriver($type);
        $parcel = $this->makeRide($type, $this->makeUser('user'), ['status' => 'no_driver_found']);
        $admin = $this->makeAdmin(['delivery'], ['update ride', 'assign ride', 'view delivery']);

        $rows = collect($this->actingAs($admin)->getJson(route('dashboard.delivery.queue', ['status' => 'dispatch', 'window' => 'all']))->assertOk()->json('rides'));
        $this->assertTrue($rows->firstWhere('id', $parcel->id)['assignable']);

        $this->actingAs($admin)->putJson(route('dashboard.rides.update', $parcel->id), [
            'ride_id' => $parcel->id, 'status' => 'accepted', 'driver_id' => $rider->id,
        ])->assertOk();

        $this->assertSame($rider->id, $parcel->fresh()->driver_id);
        $this->assertSame('accepted', $parcel->fresh()->status);
    }

    // ------------------------------------------------- delivery dispatch page (previous change)

    public function test_delivery_dispatch_books_parcel_and_reassigns(): void
    {
        $type = $this->vehicleType(true);
        $riderA = $this->makeDriver($type, ['lat' => '24.8600', 'lang' => '67.0000']);
        $riderB = $this->makeDriver($type, ['lat' => '24.8610', 'lang' => '67.0010']);
        $admin = $this->makeAdmin(['delivery'], ['view delivery', 'assign ride', 'reassign ride']);

        $quote = $this->actingAs($admin)->postJson(route('dashboard.delivery-assignment.quote'), [
            'pickup_latitude' => 24.86, 'pickup_longitude' => 67.00, 'dropoff_latitude' => 24.87, 'dropoff_longitude' => 67.01,
        ])->assertOk()->json();
        $this->assertGreaterThan(0, $quote['delivery_fee']);

        $booked = $this->actingAs($admin)->postJson(route('dashboard.delivery-assignment.parcel'), [
            'pickup_latitude' => 24.86, 'pickup_longitude' => 67.00, 'dropoff_latitude' => 24.87, 'dropoff_longitude' => 67.01,
            'sender_name' => 'Sana', 'sender_phone' => '0301' . random_int(1000000, 9999999),
            'receiver_name' => 'Omar', 'receiver_phone' => '03019999999',
            'package_type' => 'Documents', 'package_size' => 'small', 'delivery_fee_paid_by' => 'sender',
            'rider_id' => $riderA->id,
        ])->assertOk()->json();

        $parcel = Ride::find($booked['ride_id']);
        $this->assertSame($riderA->id, $parcel->driver_id);
        $this->assertEquals($quote['delivery_fee'], (float) $parcel->total_fare);

        $this->actingAs($admin)->postJson(route('dashboard.delivery-assignment.reassign', $parcel->id), ['driver_id' => $riderB->id, 'reason' => 'not responding'])
            ->assertOk();
        $this->assertSame($riderB->id, $parcel->fresh()->driver_id);
        $this->assertContains('Delivery Reassigned', array_column($this->pushes, 'title'));

        $parcel->forceFill(['status' => 'started'])->save();
        $this->actingAs($admin)->postJson(route('dashboard.delivery-assignment.reassign', $parcel->id), ['driver_id' => $riderA->id])
            ->assertStatus(422);
    }
}
