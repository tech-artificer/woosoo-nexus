<?php

namespace Tests\Feature\Api\V2;

use App\Http\Controllers\Api\V2\TabletApiController;
use App\Models\Device;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TabletPackagesApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedDevice(): Device
    {
        return Device::factory()->create(['is_active' => true]);
    }

    private function deviceToken(Device $device): string
    {
        return $device->createToken('test')->plainTextToken;
    }

    public function test_packages_endpoint_returns_empty_when_no_configured_packages(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_packages_endpoint_returns_only_active_packages(): void
    {
        $device = $this->authenticatedDevice();

        Package::factory()->create(['name' => 'Active Package', 'krypton_menu_id' => 46, 'is_active' => true, 'sort_order' => 0, 'base_price' => 449]);
        Package::factory()->create(['name' => 'Inactive Package', 'krypton_menu_id' => 47, 'is_active' => false, 'sort_order' => 1]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active Package', $response->json('data.0.name'));
        $this->assertEquals(46, $response->json('data.0.krypton_menu_id'));
        $this->assertEquals(449.0, $response->json('data.0.base_price'));
    }

    public function test_packages_endpoint_resolves_base_price_from_pos_menu(): void
    {
        $device = $this->authenticatedDevice();

        Cache::forget(TabletApiController::PACKAGES_CACHE_KEY);

        DB::connection('pos')->table('menus')->insert([
            'id' => 54,
            'name' => 'Noble Selection',
            'receipt_name' => 'PKG54',
            'price' => 499,
            'menu_group_id' => 1,
            'is_modifier_only' => false,
            'is_available' => true,
        ]);

        Package::factory()->create([
            'name' => 'Noble Selection',
            'krypton_menu_id' => 54,
            'is_active' => true,
            'base_price' => 449,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $this->assertEquals(54, $response->json('data.0.krypton_menu_id'));
        $this->assertEquals(499.0, $response->json('data.0.base_price'));
    }

    public function test_packages_endpoint_includes_allowed_menus(): void
    {
        $device = $this->authenticatedDevice();

        $package = Package::factory()->create(['name' => 'Set Meal A', 'is_active' => true, 'base_price' => 449]);
        $package->allowedMenus()->create([
            'krypton_menu_id' => 101,
            'menu_type' => 'meat',
            'sort_order' => 0,
            'quantity_limit' => 1,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.0.allowed_menus'));
        $this->assertEquals(101, $response->json('data.0.allowed_menus.0.krypton_menu_id'));
        $this->assertEquals('meat', $response->json('data.0.allowed_menus.0.menu_type'));
    }

    public function test_packages_endpoint_allowed_menus_include_rich_menu_fields(): void
    {
        $device = $this->authenticatedDevice();

        Cache::forget(TabletApiController::PACKAGES_CACHE_KEY);

        DB::connection('pos')->table('taxes')->insert([
            'id' => 1,
            'name' => 'VAT',
            'percentage' => 12,
            'rounding' => 2,
        ]);

        DB::connection('pos')->table('menus')->insert([
            'id' => 401,
            'name' => 'Pork Belly',
            'receipt_name' => 'P001 Pork Belly',
            'price' => 120,
            'menu_group_id' => 34,
            'menu_tax_type_id' => 1,
            'is_modifier_only' => true,
            'is_available' => true,
            'is_taxable' => true,
        ]);

        $package = Package::factory()->create(['name' => 'Set Meal D', 'is_active' => true]);
        $package->allowedMenus()->create([
            'krypton_menu_id' => 401,
            'menu_type' => 'meat',
            'sort_order' => 0,
            'quantity_limit' => 1,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $entry = $response->json('data.0.allowed_menus.0');
        $this->assertEquals('P001 Pork Belly', $entry['receipt_name']);
        $this->assertArrayHasKey('img_url', $entry);
        $this->assertArrayHasKey('price', $entry);
        $this->assertEquals('VAT', $entry['tax']['name']);
    }

    public function test_packages_response_includes_meat_limits_and_most_popular(): void
    {
        $device = $this->authenticatedDevice();

        Package::factory()->create([
            'name' => 'Set Meal B',
            'is_active' => true,
            'min_meat' => 1,
            'max_meat' => 3,
            'is_most_popular' => true,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.0.min_meat'));
        $this->assertEquals(3, $response->json('data.0.max_meat'));
        $this->assertTrue($response->json('data.0.is_most_popular'));
        // Non-meat limits are no longer part of the package contract (banchan is global).
        $this->assertArrayNotHasKey('min_side', $response->json('data.0'));
    }

    public function test_package_details_returns_payload_for_active_package(): void
    {
        $device = $this->authenticatedDevice();

        $package = Package::factory()->create([
            'name' => 'Set Meal A',
            'is_active' => true,
            'base_price' => 449,
            'min_meat' => 1,
            'max_meat' => 3,
        ]);
        $package->allowedMenus()->create([
            'krypton_menu_id' => 101,
            'menu_type' => 'meat',
            'sort_order' => 0,
            'quantity_limit' => 1,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson("/api/v2/tablet/packages/{$package->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.package.id', $package->id);
        $response->assertJsonPath('data.package.base_price', 449);
        $response->assertJsonPath('data.package.limits.meat.min', 1);
        $response->assertJsonPath('data.package.limits.meat.max', 3);
        $this->assertCount(1, $response->json('data.allowed_menus.meat'));
        $this->assertEquals(101, $response->json('data.allowed_menus.meat.0.krypton_menu_id'));
    }

    public function test_package_details_rejects_inactive_package(): void
    {
        $device = $this->authenticatedDevice();

        $package = Package::factory()->create(['name' => 'Set Meal A', 'is_active' => false]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson("/api/v2/tablet/packages/{$package->id}");

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
    }

    public function test_package_details_rejects_nonexistent_package(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages/99999');

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
    }

    public function test_package_details_groups_allowed_menus_by_type(): void
    {
        $device = $this->authenticatedDevice();

        $package = Package::factory()->create(['name' => 'Set Meal C', 'is_active' => true]);
        $package->allowedMenus()->createMany([
            ['krypton_menu_id' => 101, 'menu_type' => 'meat',    'sort_order' => 0, 'quantity_limit' => 1],
            ['krypton_menu_id' => 301, 'menu_type' => 'side',    'sort_order' => 0, 'quantity_limit' => 2],
            ['krypton_menu_id' => 401, 'menu_type' => 'dessert', 'sort_order' => 0, 'quantity_limit' => 1],
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson("/api/v2/tablet/packages/{$package->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.allowed_menus.meat'));
        $this->assertCount(1, $response->json('data.allowed_menus.side'));
        $this->assertCount(1, $response->json('data.allowed_menus.dessert'));
        $this->assertCount(0, $response->json('data.allowed_menus.drinks'));
    }

    public function test_meat_categories_returns_expected_static_values(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/meat-categories');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(3, $response->json('data'));
        $response->assertJsonPath('data.0.slug', 'pork');
    }

    public function test_device_auth_is_required_for_tablet_v2_endpoints(): void
    {
        $this->getJson('/api/v2/tablet/packages')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/packages/1')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/meat-categories')->assertUnauthorized();
    }
}
