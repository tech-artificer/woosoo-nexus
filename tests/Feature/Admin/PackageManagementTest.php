<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedPosPackageAnchor(int $id = 46, string $name = 'Classic Feast', float $price = 449): void
    {
        DB::connection('pos')->table('menus')->insert([
            'id' => $id,
            'name' => $name,
            'receipt_name' => 'PKG'.$id,
            'price' => $price,
            'menu_group_id' => 1,
            'is_modifier_only' => false,
            'is_available' => true,
        ]);
    }

    private function seedPosMeatModifier(int $id, string $code = 'P1'): void
    {
        DB::connection('pos')->table('menu_groups')->insertOrIgnore([
            'id' => 34,
            'name' => 'Meat Order',
        ]);

        DB::connection('pos')->table('menus')->insert([
            'id' => $id,
            'name' => 'Meat '.$code,
            'receipt_name' => $code,
            'price' => 0,
            'menu_group_id' => 34,
            'is_modifier_only' => true,
            'is_available' => true,
        ]);
    }

    public function test_admin_can_create_package_with_allowed_menus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->seedPosPackageAnchor(46, 'Set Meal X', 449);
        $this->seedPosMeatModifier(101, 'P1');
        $this->seedPosMeatModifier(102, 'P2');

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->post(route('packages.store'), [
            'krypton_menu_id' => 46,
            'description' => 'A hearty sampler set.',
            'min_meat' => 1,
            'max_meat' => 5,
            'is_active' => true,
            'sort_order' => 4,
            'allowed_menus' => [
                ['krypton_menu_id' => 101, 'sort_order' => 0],
                ['krypton_menu_id' => 102, 'sort_order' => 1],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'krypton_menu_id' => 46,
            'name' => 'Set Meal X',
            'description' => 'A hearty sampler set.',
            'base_price' => 449.00,
            'is_active' => 1,
            'sort_order' => 4,
            'max_meat' => 5,
        ]);

        $package = Package::where('krypton_menu_id', 46)->firstOrFail();
        $this->assertDatabaseHas('package_allowed_menus', [
            'package_id' => $package->id,
            'krypton_menu_id' => 101,
            'menu_type' => 'meat',
            'quantity_limit' => 1,
            'sort_order' => 0,
        ]);
    }

    public function test_admin_can_update_package_and_replace_allowed_menus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->seedPosPackageAnchor(46, 'Set Meal Legacy', 449);
        $this->seedPosPackageAnchor(47, 'Set Meal Updated', 549);
        $this->seedPosMeatModifier(201, 'B1');

        $package = Package::create([
            'krypton_menu_id' => 46,
            'name' => 'Set Meal Legacy',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $package->allowedMenus()->createMany([
            ['krypton_menu_id' => 101, 'menu_type' => 'meat', 'sort_order' => 0, 'quantity_limit' => 1],
        ]);
        $this->seedPosMeatModifier(101, 'P1');

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->put(route('packages.update', $package), [
            'krypton_menu_id' => 47,
            'description' => 'Now with updated copy.',
            'is_active' => false,
            'sort_order' => 8,
            'allowed_menus' => [
                ['krypton_menu_id' => 201, 'sort_order' => 0],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'krypton_menu_id' => 47,
            'name' => 'Set Meal Updated',
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
            'menu_type' => 'meat',
            'quantity_limit' => 1,
            'sort_order' => 0,
        ]);
    }

    public function test_admin_can_delete_package(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $package = Package::create([
            'krypton_menu_id' => 46,
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
        $this->seedPosMeatModifier(101, 'P1');
        $this->seedPosMeatModifier(201, 'P2');

        $package = Package::create([
            'krypton_menu_id' => 46,
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
        $response = $this->actingAs($admin)->post(route('packages.sync-menus', $package), [
            'allowed_menus' => [
                ['krypton_menu_id' => 201, 'sort_order' => 0, 'quantity_limit' => 3],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));

        $this->assertDatabaseMissing('package_allowed_menus', ['package_id' => $package->id, 'krypton_menu_id' => 101]);
        $this->assertDatabaseHas('package_allowed_menus', [
            'package_id' => $package->id,
            'krypton_menu_id' => 201,
            'menu_type' => 'meat',
            'quantity_limit' => 1,
            'sort_order' => 0,
        ]);
    }

    public function test_sync_rejects_non_meat_menu_ids(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->seedPosPackageAnchor(46);
        $this->seedPosMeatModifier(101, 'P1');

        $package = Package::create([
            'krypton_menu_id' => 46,
            'name' => 'Set Meal Bad',
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
                ['krypton_menu_id' => 46],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('package_allowed_menus', ['package_id' => $package->id, 'krypton_menu_id' => 101]);
    }

    public function test_sync_allowed_menus_rejects_invalid_payload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $package = Package::create([
            'krypton_menu_id' => 46,
            'name' => 'Set Meal Bad',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $package->allowedMenus()->create(['krypton_menu_id' => 101, 'menu_type' => 'meat', 'sort_order' => 0, 'quantity_limit' => 1]);

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->postJson(route('packages.sync-menus', $package), [
            'allowed_menus' => [
                ['krypton_menu_id' => 0, 'quantity_limit' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('package_allowed_menus', ['package_id' => $package->id, 'krypton_menu_id' => 101]);
    }

    public function test_store_requires_krypton_menu_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->post(route('packages.store'), [
            'description' => 'Missing anchor',
            'min_meat' => 1,
            'max_meat' => 5,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('krypton_menu_id');
    }

    public function test_store_rejects_max_meat_below_min_meat(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->seedPosPackageAnchor(46);

        /** @var Authenticatable $admin */
        $response = $this->actingAs($admin)->post(route('packages.store'), [
            'krypton_menu_id' => 46,
            'min_meat' => 3,
            'max_meat' => 1,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('max_meat');
        $this->assertDatabaseMissing('packages', ['krypton_menu_id' => 46]);
    }
}
