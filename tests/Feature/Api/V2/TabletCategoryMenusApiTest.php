<?php

namespace Tests\Feature\Api\V2;

use App\Http\Controllers\Api\V2\TabletApiController;
use App\Models\Device;
use App\Models\TabletCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TabletCategoryMenusApiTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        TabletApiController::forgetCategoriesCache();
    }

    public function test_unknown_slug_returns_404_when_db_categories_exist(): void
    {
        $device = $this->authenticatedDevice();
        TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/unknown/menus')
            ->assertNotFound();
    }

    public function test_empty_pivot_returns_200_with_empty_array(): void
    {
        $device = $this->authenticatedDevice();
        TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_pivot_menus_endpoint_returns_success_with_attached_ids(): void
    {
        $device = $this->authenticatedDevice();
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);
        $category->menuPivots()->create(['krypton_menu_id' => 201, 'sort_order' => 0]);
        $category->menuPivots()->create(['krypton_menu_id' => 202, 'sort_order' => 1]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    public function test_inactive_category_returns_404(): void
    {
        $device = $this->authenticatedDevice();
        TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => false]);
        TabletCategory::create(['name' => 'Drinks', 'slug' => 'drinks', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertNotFound();
    }

    public function test_meats_slug_still_resolves_when_db_categories_exist(): void
    {
        $device = $this->authenticatedDevice();
        TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/meats/menus')
            ->assertOk();
    }

    public function test_cache_is_busted_after_forget_categories_cache(): void
    {
        $device = $this->authenticatedDevice();
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk();

        $this->assertTrue(Cache::has(TabletApiController::categoryMenusCacheKey('sides')));

        TabletApiController::forgetCategoriesCache('sides');

        $this->assertFalse(Cache::has(TabletApiController::categoryMenusCacheKey('sides')));

        $category->menuPivots()->create(['krypton_menu_id' => 301, 'sort_order' => 0]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk();
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v2/tablet/categories/sides/menus')->assertUnauthorized();
    }
}
