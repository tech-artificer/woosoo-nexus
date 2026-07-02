<?php

namespace Tests\Feature\Api\V2;

use App\Models\Device;
use App\Models\TabletCategory;
use App\Services\TabletCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TabletCategoriesApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedDevice(): Device
    {
        $device = Device::factory()->create(['is_active' => true]);

        return $device;
    }

    private function deviceToken(Device $device): string
    {
        return $device->createToken('test')->plainTextToken;
    }

    public function test_returns_hardcoded_fallback_when_no_db_categories(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $data = $response->json('data');

        // Fallback advertises only tabs the legacy resolver can serve. 'alacarte'
        // has no legacy POS mapping, so it must NOT appear (it would 404 on menus).
        $slugs = array_column($data, 'slug');
        $this->assertSame(['sides', 'dessert', 'beverage'], $slugs);
        $this->assertNotContains('alacarte', $slugs);

        // Refill eligibility ships with the fallback: sides is unlimited, the rest are not.
        $bySlug = collect($data)->keyBy('slug');
        $this->assertTrue($bySlug['sides']['is_unlimited']);
        $this->assertFalse($bySlug['dessert']['is_unlimited']);
        $this->assertFalse($bySlug['beverage']['is_unlimited']);
    }

    public function test_fallback_returned_when_only_meats_db_category_active(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletCatalogService::CATEGORIES_CACHE_KEY);

        // A lone active 'meats' row must not suppress the bootstrap fallback:
        // the non-meats tabs still come from the legacy list (the PWA injects
        // its own meats tab when the payload carries none).
        TabletCategory::create(['name' => 'Meats', 'slug' => 'meats', 'is_active' => true]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $this->assertSame(['sides', 'dessert', 'beverage'], array_column($response->json('data'), 'slug'));
    }

    public function test_returns_db_categories_with_synthesized_meats_when_no_meats_row(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletCatalogService::CATEGORIES_CACHE_KEY);

        TabletCategory::create(['name' => 'Grilled', 'sort_order' => 0, 'is_active' => true]);
        TabletCategory::create(['name' => 'Seafood', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);

        // The meats tab must never disappear: synthesized at the front when absent.
        $this->assertSame('meats', $data[0]['slug']);
        $this->assertSame(0, $data[0]['id']);
        $this->assertTrue($data[0]['is_unlimited']);
        $this->assertArrayNotHasKey('menu_count', $data[0]);

        $this->assertEquals('grilled', $data[1]['slug']);
        $this->assertArrayHasKey('menu_count', $data[1]);
        $this->assertSame(0, $data[1]['menu_count']);
    }

    public function test_includes_meats_row_with_admin_metadata(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletCatalogService::CATEGORIES_CACHE_KEY);

        $meats = TabletCategory::create(['name' => 'Grill Meats', 'slug' => 'meats', 'sort_order' => 0, 'is_active' => true, 'is_unlimited' => true]);
        TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Admin meats row drives the tab metadata (label, position, flag)...
        $this->assertSame('meats', $data[0]['slug']);
        $this->assertSame($meats->id, $data[0]['id']);
        $this->assertSame('Grill Meats', $data[0]['name']);
        $this->assertTrue($data[0]['is_unlimited']);

        // ...but its catalog stays POS-group-driven, so menu_count is omitted.
        $this->assertArrayNotHasKey('menu_count', $data[0]);

        $this->assertEquals('sides', $data[1]['slug']);
    }

    public function test_menu_count_reflects_pivot_rows(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletCatalogService::CATEGORIES_CACHE_KEY);

        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);
        $category->menuPivots()->create(['krypton_menu_id' => 101, 'sort_order' => 0]);
        $category->menuPivots()->create(['krypton_menu_id' => 102, 'sort_order' => 1]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $sides = collect($response->json('data'))->firstWhere('slug', 'sides');
        $this->assertSame(2, $sides['menu_count']);
    }

    public function test_is_unlimited_reflects_db_flag(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletCatalogService::CATEGORIES_CACHE_KEY);

        TabletCategory::create(['name' => 'Sides',  'slug' => 'sides',  'is_active' => true, 'is_unlimited' => true]);
        TabletCategory::create(['name' => 'Drinks', 'slug' => 'drinks', 'is_active' => true, 'is_unlimited' => false]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $bySlug = collect($response->json('data'))->keyBy('slug');
        $this->assertTrue($bySlug['sides']['is_unlimited']);
        $this->assertFalse($bySlug['drinks']['is_unlimited']);
    }

    public function test_inactive_db_categories_excluded(): void
    {
        $device = $this->authenticatedDevice();

        TabletCategory::create(['name' => 'Active Cat',   'is_active' => true]);
        TabletCategory::create(['name' => 'Inactive Cat', 'is_active' => false]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');
        $this->assertContains('active-cat', $slugs);
        $this->assertNotContains('inactive-cat', $slugs);
        // active-cat + synthesized meats only
        $this->assertCount(2, $slugs);
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v2/tablet/categories')->assertUnauthorized();
    }
}
