<?php

namespace Tests\Feature\Api\V2;

use App\Models\Device;
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

    public function test_packages_endpoint_returns_legacy_packages_for_authenticated_device(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_package_details_returns_payload_for_legacy_package_id(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages/46');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.package.id', 46);
        $this->assertIsArray($response->json('data.allowed_menus.meat'));
    }

    public function test_package_details_rejects_unknown_package_id(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/packages/999');

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
            ->getJson('/api/v2/tablet/categories/not-a-slug/menus');

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_category_menus_returns_data_for_valid_slug(): void
    {
        $device = $this->authenticatedDevice();

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/categories/sides/menus');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_device_auth_is_required_for_tablet_v2_endpoints(): void
    {
        $this->getJson('/api/v2/tablet/packages')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/packages/46')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/meat-categories')->assertUnauthorized();
        $this->getJson('/api/v2/tablet/categories/sides/menus')->assertUnauthorized();
    }
}
