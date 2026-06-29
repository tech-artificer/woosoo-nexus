<?php

namespace Tests\Feature\Api\V2;

use App\Http\Controllers\Api\V2\TabletApiController;
use App\Models\Device;
use App\Models\Krypton\Menu;
use App\Models\TabletCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $menu1 = Menu::factory()->create();
        $menu2 = Menu::factory()->create();
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);
        $category->menuPivots()->create(['krypton_menu_id' => $menu1->id, 'sort_order' => 0]);
        $category->menuPivots()->create(['krypton_menu_id' => $menu2->id, 'sort_order' => 1]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($menu1->id, $data[0]['id']);
        $this->assertSame($menu2->id, $data[1]['id']);
    }

    public function test_meats_only_in_db_does_not_suppress_legacy_fallback_for_other_slugs(): void
    {
        $device = $this->authenticatedDevice();
        TabletCategory::create(['name' => 'Meats', 'slug' => 'meats', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk();
    }

    public function test_featured_pivot_surfaces_is_featured_flag(): void
    {
        $device = $this->authenticatedDevice();
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        DB::connection('pos')->table('menus')->insert([
            'id' => 301,
            'name' => 'Kimchi',
            'receipt_name' => 'Kimchi',
            'price' => 0,
            'is_modifier_only' => false,
            'is_available' => true,
        ]);

        $category->menuPivots()->create(['krypton_menu_id' => 301, 'sort_order' => 0, 'is_featured' => true]);
        $category->menuPivots()->create(['krypton_menu_id' => 302, 'sort_order' => 1, 'is_featured' => false]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_featured'));
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
        $menu = Menu::factory()->create();
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk();

        $this->assertTrue(Cache::has(TabletApiController::categoryMenusCacheKey('sides')));

        TabletApiController::forgetCategoriesCache('sides');

        $this->assertFalse(Cache::has(TabletApiController::categoryMenusCacheKey('sides')));

        $category->menuPivots()->create(['krypton_menu_id' => $menu->id, 'sort_order' => 0]);

        $refetch = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk();

        $this->assertCount(1, $refetch->json('data'));
        $this->assertSame($menu->id, $refetch->json('data.0.id'));
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v2/tablet/categories/sides/menus')->assertUnauthorized();
    }
}
