<?php

namespace Tests\Feature\Console;

use App\Models\Package;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncPackageModifiersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosSqliteConnection();
        $this->createPosTables();
        $this->seedPosMenus();
    }

    public function test_sync_command_supports_dry_run_without_writing_rows(): void
    {
        $this->artisan('woosoo:sync-package-modifiers', ['--dry-run' => true])
            ->expectsOutputToContain('Prepared package modifier sync for 3 package(s) and 33 modifier row(s).')
            ->expectsOutputToContain('Dry run complete. No database changes were written.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('package_modifiers', 0);
    }

    public function test_sync_command_reseeds_expected_modifier_counts_for_supported_packages(): void
    {
        $this->artisan('woosoo:sync-package-modifiers')->assertExitCode(0);

        $countsByPackage = Package::query()
            ->whereIn('krypton_menu_id', [46, 47, 48])
            ->withCount('modifiers')
            ->pluck('modifiers_count', 'krypton_menu_id')
            ->all();

        $this->assertSame([
            46 => 5,
            47 => 8,
            48 => 20,
        ], $countsByPackage);
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
    }

    private function createPosTables(): void
    {
        if (! Schema::connection('pos')->hasTable('menus')) {
            Schema::connection('pos')->create('menus', function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('name')->nullable();
                $table->string('receipt_name')->nullable();
                $table->boolean('is_modifier_only')->default(false);
            });
        }
    }

    private function seedPosMenus(): void
    {
        $receiptCodes = [
            'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9',
            'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
            'C1',
        ];

        $rows = [];

        foreach ($receiptCodes as $index => $receiptCode) {
            $rows[] = [
                'id' => 1000 + $index,
                'name' => $receiptCode,
                'receipt_name' => $receiptCode,
                'is_modifier_only' => true,
            ];
        }

        DB::connection('pos')->table('menus')->insert($rows);
    }
}
