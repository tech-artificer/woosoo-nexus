<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_package_with_allowed_menus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->post(route('packages.store'), [
            'name' => 'Set Meal X',
            'description' => 'A hearty sampler set.',
            'base_price' => 449.00,
            'min_meat' => 1,
            'max_meat' => 3,
            'is_active' => true,
            'sort_order' => 4,
            'allowed_menus' => [
                ['krypton_menu_id' => 101, 'menu_type' => 'meat', 'sort_order' => 0, 'quantity_limit' => 1],
                ['krypton_menu_id' => 102, 'menu_type' => 'meat', 'sort_order' => 1, 'quantity_limit' => 1],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'name' => 'Set Meal X',
            'description' => 'A hearty sampler set.',
            'base_price' => 449.00,
            'is_active' => 1,
            'sort_order' => 4,
        ]);

        $package = Package::where('name', 'Set Meal X')->firstOrFail();
        $this->assertDatabaseHas('package_allowed_menus', [
            'package_id' => $package->id,
            'krypton_menu_id' => 101,
            'menu_type' => 'meat',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('package_allowed_menus', [
            'package_id' => $package->id,
            'krypton_menu_id' => 102,
            'menu_type' => 'meat',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_update_package_and_replace_allowed_menus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $package = Package::create([
            'name' => 'Set Meal Legacy',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $package->allowedMenus()->createMany([
            ['krypton_menu_id' => 101, 'menu_type' => 'meat', 'sort_order' => 0, 'quantity_limit' => 1],
            ['krypton_menu_id' => 102, 'menu_type' => 'meat', 'sort_order' => 1, 'quantity_limit' => 1],
        ]);

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->put(route('packages.update', $package), [
            'name' => 'Set Meal Updated',
            'description' => 'Now with updated copy.',
            'base_price' => 549.00,
            'is_active' => false,
            'sort_order' => 8,
            'allowed_menus' => [
                ['krypton_menu_id' => 201, 'menu_type' => 'side', 'sort_order' => 0, 'quantity_limit' => 2],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'Set Meal Updated',
            'description' => 'Now with updated copy.',
            'base_price' => 549.00,
            'is_active' => 0,
            'sort_order' => 8,
        ]);

        $this->assertDatabaseMissing('package_allowed_menus', [
            'package_id' => $package->id,
            'krypton_menu_id' => 101,
        ]);
        $this->assertDatabaseHas('package_allowed_menus', [
            'package_id' => $package->id,
            'krypton_menu_id' => 201,
            'menu_type' => 'side',
            'sort_order' => 0,
        ]);
    }

    public function test_admin_can_delete_package(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $package = Package::create([
            'name' => 'Set Meal Delete',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $package->allowedMenus()->create([
            'krypton_menu_id' => 101,
            'menu_type' => 'meat',
            'sort_order' => 0,
            'quantity_limit' => 1,
        ]);

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->delete(route('packages.destroy', $package));

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
        $this->assertDatabaseMissing('package_allowed_menus', ['package_id' => $package->id]);
    }

    public function test_admin_can_sync_allowed_menus_via_dedicated_route(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $package = Package::create([
            'name' => 'Set Meal Sync',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $package->allowedMenus()->create([
            'krypton_menu_id' => 101,
            'menu_type' => 'meat',
            'sort_order' => 0,
            'quantity_limit' => 1,
        ]);

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->postJson(route('packages.sync-menus', $package), [
            'allowed_menus' => [
                ['krypton_menu_id' => 201, 'menu_type' => 'side', 'sort_order' => 0, 'quantity_limit' => 3],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('package_allowed_menus', ['package_id' => $package->id, 'krypton_menu_id' => 101]);
        $this->assertDatabaseHas('package_allowed_menus', ['package_id' => $package->id, 'krypton_menu_id' => 201]);
    }
}
