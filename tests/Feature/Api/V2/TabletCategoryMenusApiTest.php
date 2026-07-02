<?php

namespace Tests\Feature\Api\V2;

use App\Models\Device;
use App\Models\TabletCategory;
use App\Services\TabletCatalogService;
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

    /**
     * Seed a POS menu row so the controller can resolve attached pivots to real
     * menus (the `pos` connection is mapped to in-memory sqlite by TestCase).
     */
    private function seedPosMenu(int $id, string $name): void
    {
        DB::connection('pos')->table('menus')->insert([
            'id' => $id,
            'name' => $name,
            'price' => 10.00,
            'is_available' => true,
            'is_modifier_only' => false,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TabletCatalogService::forgetCategoriesCache();
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

    public function test_pivot_menus_endpoint_returns_attached_ids_in_pivot_order(): void
    {
        $device = $this->authenticatedDevice();
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);
        // sort_order is deliberately reversed vs. id to prove ordering is by pivot.
        $category->menuPivots()->create(['krypton_menu_id' => 202, 'sort_order' => 0]);
        $category->menuPivots()->create(['krypton_menu_id' => 201, 'sort_order' => 1]);
        $this->seedPosMenu(201, 'Steamed Rice');
        $this->seedPosMenu(202, 'Garlic Rice');

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        // Must return exactly the attached menus, ordered by pivot sort_order, not dropped.
        $this->assertSame([202, 201], array_column($response->json('data'), 'id'));
    }

    public function test_featured_pivot_surfaces_is_featured_flag(): void
    {
        $device = $this->authenticatedDevice();
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $this->seedPosMenu(301, 'Kimchi');

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
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk();

        $this->assertTrue(Cache::has(TabletCatalogService::categoryMenusCacheKey('sides')));

        TabletCatalogService::forgetCategoriesCache('sides');

        $this->assertFalse(Cache::has(TabletCatalogService::categoryMenusCacheKey('sides')));

        $category->menuPivots()->create(['krypton_menu_id' => 301, 'sort_order' => 0]);
        $this->seedPosMenu(301, 'Kimchi');

        // After the cache bust the refetched payload must reflect the new pivot,
        // not a stale (empty) cached response.
        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        $this->assertSame([301], array_column($response->json('data'), 'id'));
    }

    public function test_legacy_fallback_serves_mapped_tabs_and_404s_alacarte(): void
    {
        $device = $this->authenticatedDevice();

        // No DB categories → legacy POS-group fallback. Mapped tabs resolve (200,
        // possibly empty); 'alacarte' has no mapping and must 404 rather than appear broken.
        foreach (['sides', 'dessert', 'beverage'] as $slug) {
            $this->withToken($this->deviceToken($device), 'Bearer')
                ->getJson("/api/v2/tablet/categories/{$slug}/menus")
                ->assertOk();
        }

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/alacarte/menus')
            ->assertNotFound();
    }

    public function test_only_meats_db_category_still_serves_legacy_fallback_tabs(): void
    {
        $device = $this->authenticatedDevice();

        // A lone active 'meats' row must not flip categoryMenus() onto the DB path
        // for the bootstrap tabs; 'sides' should still resolve via legacy fallback.
        TabletCategory::create(['name' => 'Meats', 'slug' => 'meats', 'is_active' => true]);

        $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk();
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v2/tablet/categories/sides/menus')->assertUnauthorized();
    }
}
