<?php
// Audit Fix (2026-04-06): verify admin package CRUD endpoints persist package + modifier configuration.

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_package_with_modifiers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $response = $this->actingAs($admin)->post(route('packages.store'), [
            'name' => 'Set Meal X',
            'krypton_menu_id' => 9046,
            'is_active' => true,
            'sort_order' => 4,
            'modifiers' => [
                ['krypton_menu_id' => 3001, 'sort_order' => 0],
                ['krypton_menu_id' => 3002, 'sort_order' => 1],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'name' => 'Set Meal X',
            'krypton_menu_id' => 9046,
            'is_active' => 1,
            'sort_order' => 4,
        ]);

        $package = Package::where('krypton_menu_id', 9046)->firstOrFail();
        $this->assertDatabaseHas('package_modifiers', [
            'package_id' => $package->id,
            'krypton_menu_id' => 3001,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('package_modifiers', [
            'package_id' => $package->id,
            'krypton_menu_id' => 3002,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_update_package_and_replace_modifiers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $package = Package::create([
            'name' => 'Set Meal Legacy',
            'krypton_menu_id' => 9047,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $package->modifiers()->createMany([
            ['krypton_menu_id' => 3101, 'sort_order' => 0],
            ['krypton_menu_id' => 3102, 'sort_order' => 1],
        ]);

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $response = $this->actingAs($admin)->put(route('packages.update', $package), [
            'name' => 'Set Meal Updated',
            'krypton_menu_id' => 9047,
            'is_active' => false,
            'sort_order' => 8,
            'modifiers' => [
                ['krypton_menu_id' => 3201, 'sort_order' => 0],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'Set Meal Updated',
            'is_active' => 0,
            'sort_order' => 8,
        ]);

        $this->assertDatabaseMissing('package_modifiers', [
            'package_id' => $package->id,
            'krypton_menu_id' => 3101,
        ]);
        $this->assertDatabaseHas('package_modifiers', [
            'package_id' => $package->id,
            'krypton_menu_id' => 3201,
            'sort_order' => 0,
        ]);
    }

    public function test_admin_can_delete_package(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $package = Package::create([
            'name' => 'Set Meal Delete',
            'krypton_menu_id' => 9048,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $response = $this->actingAs($admin)->delete(route('packages.destroy', $package));

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }
}
