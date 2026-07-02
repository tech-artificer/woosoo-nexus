<?php

namespace Tests\Feature\Api\V2;

use App\Models\Device;
use App\Models\Package;
use App\Models\TabletCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin mirror of the V2 tablet catalog (/api/v2/admin/tablet/*).
 *
 * Contract: the mirror serves byte-identical payloads to the device-auth
 * tablet routes — both sides call the same TabletCatalogService and share
 * the same caches.
 */
class AdminTabletCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function deviceToken(): string
    {
        return Device::factory()->create(['is_active' => true])->createToken('test')->plainTextToken;
    }

    public function test_unauthenticated_requests_rejected_on_all_mirror_routes(): void
    {
        $this->getJson('/api/v2/admin/tablet/packages')->assertUnauthorized();
        $this->getJson('/api/v2/admin/tablet/packages/1')->assertUnauthorized();
        $this->getJson('/api/v2/admin/tablet/categories')->assertUnauthorized();
        $this->getJson('/api/v2/admin/tablet/categories/sides/menus')->assertUnauthorized();
    }

    public function test_admin_packages_payload_matches_device_payload(): void
    {
        $package = Package::factory()->create(['name' => 'Set Meal A', 'is_active' => true, 'base_price' => 449]);
        $package->allowedMenus()->create([
            'krypton_menu_id' => 101,
            'menu_type' => 'meat',
            'sort_order' => 0,
            'quantity_limit' => 1,
        ]);

        $devicePayload = $this->withToken($this->deviceToken(), 'Bearer')
            ->getJson('/api/v2/tablet/packages')
            ->assertOk()
            ->json('data');

        $adminPayload = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v2/admin/tablet/packages')
            ->assertOk()
            ->json('data');

        $this->assertSame($devicePayload, $adminPayload);
        $this->assertCount(1, $adminPayload);
        $this->assertEquals('Set Meal A', $adminPayload[0]['name']);
    }

    public function test_admin_package_details_matches_device_payload_and_404s_unknown(): void
    {
        $package = Package::factory()->create(['name' => 'Set Meal B', 'is_active' => true, 'min_meat' => 1, 'max_meat' => 3]);

        $devicePayload = $this->withToken($this->deviceToken(), 'Bearer')
            ->getJson("/api/v2/tablet/packages/{$package->id}")
            ->assertOk()
            ->json('data');

        $adminPayload = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v2/admin/tablet/packages/{$package->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame($devicePayload, $adminPayload);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v2/admin/tablet/packages/99999')
            ->assertNotFound();
    }

    public function test_admin_categories_payload_matches_device_payload(): void
    {
        TabletCategory::create(['name' => 'Meats', 'slug' => 'meats', 'sort_order' => 0, 'is_active' => true, 'is_unlimited' => true]);
        TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'sort_order' => 1, 'is_active' => true, 'is_unlimited' => true]);
        TabletCategory::create(['name' => 'Drinks', 'slug' => 'drinks', 'sort_order' => 2, 'is_active' => true]);

        $devicePayload = $this->withToken($this->deviceToken(), 'Bearer')
            ->getJson('/api/v2/tablet/categories')
            ->assertOk()
            ->json('data');

        $adminPayload = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v2/admin/tablet/categories')
            ->assertOk()
            ->json('data');

        $this->assertSame($devicePayload, $adminPayload);
        $this->assertSame(['meats', 'sides', 'drinks'], array_column($adminPayload, 'slug'));
    }

    public function test_admin_category_menus_matches_device_payload_and_404s_unknown_slug(): void
    {
        $category = TabletCategory::create(['name' => 'Sides', 'slug' => 'sides', 'is_active' => true]);
        $category->menuPivots()->create(['krypton_menu_id' => 101, 'sort_order' => 0]);

        $devicePayload = $this->withToken($this->deviceToken(), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus')
            ->assertOk()
            ->json('data');

        $adminPayload = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v2/admin/tablet/categories/sides/menus')
            ->assertOk()
            ->json('data');

        $this->assertSame($devicePayload, $adminPayload);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v2/admin/tablet/categories/nope/menus')
            ->assertNotFound();
    }

    public function test_device_token_can_read_mirror_routes_current_sanctum_posture(): void
    {
        // Documents current posture: `auth:sanctum` has no provider constraint in
        // this app, so any personal access token (incl. device tokens) passes —
        // same as the sibling /api/v2/devices admin group. Harmless here: the
        // mirror is read-only and serves data devices already receive on their
        // own routes. If guards are ever tightened to a users-only provider,
        // update this assertion deliberately (CONTRACTS §4).
        $this->withToken($this->deviceToken(), 'Bearer')
            ->getJson('/api/v2/admin/tablet/categories')
            ->assertOk();
    }
}
