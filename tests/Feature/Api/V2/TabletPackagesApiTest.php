<?php

namespace Tests\Feature\Api\V2;

use App\Models\Device;
use App\Models\Package;
use App\Models\PackageModifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TabletPackagesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosSqliteConnection();
        $this->createPosTables();
        $this->seedPosMenus();
    }

    private function authenticatedDevice(): Device
    {
        return Device::factory()->create(['is_active' => true]);
    }

    private function deviceToken(Device $device): string
    {
        return $device->createToken('test')->plainTextToken;
    }

    private function setUpPosSqliteConnection(): void
    {
        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('pos');
        DB::reconnect('pos');

        $pdo = DB::connection('pos')->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('FIELD', function (...$args): int {
                $needle = $args[0] ?? null;
                foreach (array_slice($args, 1) as $index => $value) {
                    if ((string) $needle === (string) $value) {
                        return $index + 1;
                    }
                }

                return 0;
            }, -1);
        }
    }

    private function createPosTables(): void
    {
        Schema::connection('pos')->create('menu_categories', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
        });

        Schema::connection('pos')->create('menu_groups', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->integer('menu_category_id')->nullable();
            $table->integer('menu_course_type_id')->nullable();
        });

        Schema::connection('pos')->create('menu_course_types', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
        });

        Schema::connection('pos')->create('taxes', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->decimal('percentage', 8, 2)->default(0);
            $table->integer('rounding')->default(0);
        });

        Schema::connection('pos')->create('menus', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name')->nullable();
            $table->string('kitchen_name')->nullable();
            $table->string('receipt_name')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_modifier')->default(false);
            $table->boolean('is_modifier_only')->default(false);
            $table->integer('menu_category_id')->nullable();
            $table->integer('menu_group_id')->nullable();
            $table->integer('menu_tax_type_id')->nullable();
            $table->integer('menu_course_type_id')->nullable();
        });

        Schema::connection('pos')->create('menu_images', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('menu_id');
            $table->string('path')->nullable();
            $table->integer('media_file_id')->nullable();
            $table->string('image_type')->nullable();
            $table->integer('sort_order')->nullable();
        });
    }

    private function seedPosMenus(): void
    {
        DB::connection('pos')->table('menu_categories')->insert([
            ['id' => 1, 'name' => 'Sides'],
            ['id' => 2, 'name' => 'Dessert'],
        ]);

        DB::connection('pos')->table('menu_groups')->insert([
            ['id' => 1, 'name' => 'drinks', 'menu_category_id' => 1, 'menu_course_type_id' => null],
        ]);

        DB::connection('pos')->table('menu_course_types')->insert([
            ['id' => 1, 'name' => 'dessert'],
        ]);

        DB::connection('pos')->table('taxes')->insert([
            ['id' => 1, 'percentage' => 0, 'rounding' => 0],
        ]);

        DB::connection('pos')->table('menus')->insert([
            [
                'id' => 46,
                'name' => 'Set Meal A',
                'kitchen_name' => 'Set Meal A',
                'receipt_name' => 'SET_A',
                'price' => 449,
                'is_taxable' => true,
                'is_available' => true,
                'is_modifier' => false,
                'is_modifier_only' => false,
                'menu_category_id' => 1,
                'menu_group_id' => 1,
                'menu_tax_type_id' => 1,
                'menu_course_type_id' => null,
            ],
            [
                'id' => 47,
                'name' => 'Set Meal B',
                'kitchen_name' => 'Set Meal B',
                'receipt_name' => 'SET_B',
                'price' => 549,
                'is_taxable' => true,
                'is_available' => true,
                'is_modifier' => false,
                'is_modifier_only' => false,
                'menu_category_id' => 1,
                'menu_group_id' => 1,
                'menu_tax_type_id' => 1,
                'menu_course_type_id' => null,
            ],
            [
                'id' => 48,
                'name' => 'Set Meal C',
                'kitchen_name' => 'Set Meal C',
                'receipt_name' => 'SET_C',
                'price' => 649,
                'is_taxable' => true,
                'is_available' => true,
                'is_modifier' => false,
                'is_modifier_only' => false,
                'menu_category_id' => 1,
                'menu_group_id' => 1,
                'menu_tax_type_id' => 1,
                'menu_course_type_id' => null,
            ],
            [
                'id' => 101,
                'name' => 'Pork 1',
                'kitchen_name' => 'Pork 1',
                'receipt_name' => 'P1',
                'price' => 0,
                'is_taxable' => false,
                'is_available' => true,
                'is_modifier' => true,
                'is_modifier_only' => true,
                'menu_category_id' => 1,
                'menu_group_id' => 1,
                'menu_tax_type_id' => 1,
                'menu_course_type_id' => null,
            ],
            [
                'id' => 102,
                'name' => 'Pork 2',
                'kitchen_name' => 'Pork 2',
                'receipt_name' => 'P2',
                'price' => 0,
                'is_taxable' => false,
                'is_available' => true,
                'is_modifier' => true,
                'is_modifier_only' => true,
                'menu_category_id' => 1,
                'menu_group_id' => 1,
                'menu_tax_type_id' => 1,
                'menu_course_type_id' => null,
            ],
            [
                'id' => 300,
                'name' => 'Kimchi Side',
                'kitchen_name' => 'Kimchi Side',
                'receipt_name' => 'KIMCHI',
                'price' => 99,
                'is_taxable' => true,
                'is_available' => true,
                'is_modifier' => false,
                'is_modifier_only' => false,
                'menu_category_id' => 1,
                'menu_group_id' => 1,
                'menu_tax_type_id' => 1,
                'menu_course_type_id' => null,
            ],
        ]);
    }

    public function test_packages_endpoint_returns_empty_when_no_configured_packages(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_packages_endpoint_returns_only_active_configured_packages(): void
    {
        $device = $this->authenticatedDevice();

        // Create active package
        $activePackage = Package::create([
            'name' => 'Set Meal A',
            'description' => 'Classic Feast package copy.',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Create inactive package (should not appear)
        Package::create([
            'name' => 'Set Meal B',
            'krypton_menu_id' => 47,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(46, $response->json('data.0.id'));
        $this->assertEquals(46, $response->json('data.0.krypton_menu_id'));
        $this->assertEquals($activePackage->id, $response->json('data.0.package_config_id'));
        $this->assertEquals('Classic Feast package copy.', $response->json('data.0.package_config_description'));
        $this->assertEquals('Classic Feast package copy.', $response->json('data.0.description'));
        $this->assertEquals('Set Meal A', $response->json('data.0.name'));
        $this->assertEquals('449.00', $response->json('data.0.price'));
    }

    public function test_package_with_invalid_krypton_menu_id_is_excluded(): void
    {
        $device = $this->authenticatedDevice();

        Package::create([
            'name' => 'Broken Package',
            'description' => 'This package points at a missing POS menu.',
            'krypton_menu_id' => 9999,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_package_with_valid_modifiers_returns_modifiers(): void
    {
        $device = $this->authenticatedDevice();

        $package = Package::create([
            'name' => 'Set Meal A',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        PackageModifier::create([
            'package_id' => $package->id,
            'krypton_menu_id' => 101,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $modifiers = $response->json('data.0.modifiers');
        $this->assertCount(1, $modifiers);
        $this->assertEquals(101, $modifiers[0]['id']);
        $this->assertEquals(101, $modifiers[0]['krypton_menu_id']);
        $this->assertEquals('Pork 1', $modifiers[0]['name']);
        $this->assertEquals(0, $modifiers[0]['sort_order']);
    }

    public function test_package_with_invalid_modifier_excludes_it(): void
    {
        $device = $this->authenticatedDevice();

        $package = Package::create([
            'name' => 'Set Meal A',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Valid modifier
        PackageModifier::create([
            'package_id' => $package->id,
            'krypton_menu_id' => 101,
            'sort_order' => 0,
        ]);

        // Invalid modifier (points to non-existent menu)
        PackageModifier::create([
            'package_id' => $package->id,
            'krypton_menu_id' => 9999,
            'sort_order' => 1,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        // Only valid modifier appears
        $this->assertCount(1, $response->json('data.0.modifiers'));
        $this->assertEquals(101, $response->json('data.0.modifiers.0.id'));
    }

    public function test_package_details_returns_payload_for_configured_active_package(): void
    {
        $device = $this->authenticatedDevice();

        $package = Package::create([
            'name' => 'Set Meal A',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        PackageModifier::create([
            'package_id' => $package->id,
            'krypton_menu_id' => 101,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages/46');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.package.id', 46);
        $this->assertIsArray($response->json('data.allowed_menus.meat'));
        $this->assertCount(1, $response->json('data.allowed_menus.meat'));
    }

    public function test_package_details_rejects_inactive_package(): void
    {
        $device = $this->authenticatedDevice();

        Package::create([
            'name' => 'Set Meal A',
            'krypton_menu_id' => 46,
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages/46');

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
    }

    public function test_package_details_rejects_non_configured_package(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages/46');

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
    }

    public function test_meat_categories_returns_expected_static_values(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/meat-categories');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(3, $response->json('data'));
        $response->assertJsonPath('data.0.slug', 'pork');
    }

    public function test_category_menus_rejects_invalid_slug(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/invalid/menus');

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_category_menus_resolves_meats_via_group_id(): void
    {
        $device = $this->authenticatedDevice();

        // Add a non-modifier-only menu to meats group (ID 34)
        DB::connection('pos')->table('menus')->insert([
            'id' => 200,
            'name' => 'Wagyu Beef',
            'kitchen_name' => 'Wagyu',
            'receipt_name' => 'WAGYU',
            'price' => 99,
            'is_available' => true,
            'is_modifier_only' => false,
            'menu_group_id' => 34, // meats group
            'menu_tax_type_id' => 1,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/meats/menus');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_category_menus_resolves_sides_via_group_id(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_category_menus_resolves_drinks_via_group_id(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/drinks/menus');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_category_menus_resolves_desserts_via_course(): void
    {
        $device = $this->authenticatedDevice();

        DB::connection('pos')->table('menus')->insert([
            'id' => 210,
            'name' => 'Vanilla Cake',
            'kitchen_name' => 'Cake',
            'receipt_name' => 'CAKE',
            'price' => 49,
            'is_available' => true,
            'is_modifier_only' => false,
            'menu_group_id' => 1,
            'menu_tax_type_id' => 1,
            'menu_course_type_id' => 1, // dessert course
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/desserts/menus');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_device_auth_is_required_for_tablet_v2_endpoints(): void
    {
        $this->getJson('/api/v2/tablet/packages')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/packages/46')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/meat-categories')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/categories/sides/menus')->assertUnauthorized();
    }
}
