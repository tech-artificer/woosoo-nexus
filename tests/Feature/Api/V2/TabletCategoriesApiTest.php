<?php

namespace Tests\Feature\Api\V2;

use App\Http\Controllers\Api\V2\TabletApiController;
use App\Models\Device;
use App\Models\TabletCategory;
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
        $this->assertCount(3, $data);
        $this->assertEquals('sides', $data[0]['slug']);
    }

    public function test_returns_db_categories_when_present(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletApiController::CATEGORIES_CACHE_KEY);

        TabletCategory::create(['name' => 'Grilled', 'sort_order' => 0, 'is_active' => true]);
        TabletCategory::create(['name' => 'Seafood', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('grilled', $data[0]['slug']);
        $this->assertArrayHasKey('menu_count', $data[0]);
        $this->assertSame(0, $data[0]['menu_count']);
    }

    public function test_excludes_meats_slug_from_db_categories(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletApiController::CATEGORIES_CACHE_KEY);

        TabletCategory::create(['name' => 'Meats', 'slug' => 'meats', 'is_active' => true]);
        TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('sides', $data[0]['slug']);
    }

    public function test_menu_count_reflects_pivot_rows(): void
    {
        $device = $this->authenticatedDevice();
        Cache::forget(TabletApiController::CATEGORIES_CACHE_KEY);

        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);
        $category->menuPivots()->create(['krypton_menu_id' => 101, 'sort_order' => 0]);
        $category->menuPivots()->create(['krypton_menu_id' => 102, 'sort_order' => 1]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.0.menu_count'));
    }

    public function test_inactive_db_categories_excluded(): void
    {
        $device = $this->authenticatedDevice();

        TabletCategory::create(['name' => 'Active Cat',   'is_active' => true]);
        TabletCategory::create(['name' => 'Inactive Cat', 'is_active' => false]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v2/tablet/categories')->assertUnauthorized();
    }
}
