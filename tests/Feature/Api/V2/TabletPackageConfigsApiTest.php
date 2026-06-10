<?php

namespace Tests\Feature\Api\V2;

use App\Models\Device;
use App\Models\TabletPackageAllowedMenu;
use App\Models\TabletPackageConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabletPackageConfigsApiTest extends TestCase
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

    public function test_package_configs_requires_device_auth(): void
    {
        $this->getJson('/api/v2/tablet/package-configs')->assertUnauthorized();
    }

    public function test_package_configs_returns_empty_when_none_configured(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/package-configs');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_package_configs_returns_only_active_configs(): void
    {
        $device = $this->authenticatedDevice();

        TabletPackageConfig::create(['name' => 'Active Set', 'base_price' => 499, 'is_active' => true, 'sort_order' => 0]);
        TabletPackageConfig::create(['name' => 'Hidden Set', 'base_price' => 599, 'is_active' => false, 'sort_order' => 1]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/package-configs');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active Set', $response->json('data.0.name'));
    }

    public function test_package_configs_returns_correct_shape(): void
    {
        $device = $this->authenticatedDevice();

        TabletPackageConfig::create([
            'name' => 'Premium Set',
            'description' => 'Our finest',
            'base_price' => 799,
            'min_meat' => 2,
            'max_meat' => 5,
            'min_side' => 1,
            'max_side' => 3,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/package-configs');

        $response->assertOk();
        $data = $response->json('data.0');

        $this->assertEquals('Premium Set', $data['name']);
        $this->assertEquals('Our finest', $data['description']);
        $this->assertEquals(2, $data['min_meat']);
        $this->assertEquals(5, $data['max_meat']);
        $this->assertEquals(1, $data['min_side']);
        $this->assertEquals(3, $data['max_side']);
        $this->assertArrayHasKey('allowed_menus', $data);
        $this->assertIsArray($data['allowed_menus']);
    }

    public function test_package_configs_includes_active_allowed_menus(): void
    {
        $device = $this->authenticatedDevice();

        $config = TabletPackageConfig::create(['name' => 'BBQ Set', 'base_price' => 499, 'is_active' => true]);

        TabletPackageAllowedMenu::create([
            'package_config_id' => $config->id,
            'krypton_menu_id' => 1001,
            'menu_type' => 'meat',
            'extra_price' => 20.00,
            'quantity_limit' => 2,
            'is_required' => true,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Inactive menu — must not appear
        TabletPackageAllowedMenu::create([
            'package_config_id' => $config->id,
            'krypton_menu_id' => 1002,
            'menu_type' => 'side',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/package-configs');

        $response->assertOk();
        $menus = $response->json('data.0.allowed_menus');
        $this->assertCount(1, $menus);
        $this->assertEquals(1001, $menus[0]['krypton_menu_id']);
        $this->assertEquals('meat', $menus[0]['menu_type']);
        $this->assertEquals('20.00', $menus[0]['extra_price']);
        $this->assertEquals(2, $menus[0]['quantity_limit']);
        $this->assertTrue($menus[0]['is_required']);
        $this->assertFalse($menus[0]['is_default']);
    }

    public function test_package_configs_returns_ordered_by_sort_order(): void
    {
        $device = $this->authenticatedDevice();

        TabletPackageConfig::create(['name' => 'Set C', 'base_price' => 699, 'is_active' => true, 'sort_order' => 2]);
        TabletPackageConfig::create(['name' => 'Set A', 'base_price' => 499, 'is_active' => true, 'sort_order' => 0]);
        TabletPackageConfig::create(['name' => 'Set B', 'base_price' => 599, 'is_active' => true, 'sort_order' => 1]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/package-configs');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertEquals(['Set A', 'Set B', 'Set C'], $names);
    }
}
