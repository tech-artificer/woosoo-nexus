<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment and ensure the `pos` connection is
     * mapped to an in-memory sqlite database to avoid remote MySQL
     * connections during CI/test runs. Also create minimal POS tables
     * required by application code so tests don't hit missing-table
     * errors when code references POS models.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            // Use a file-backed sqlite DB for `pos` during tests. In-memory
            // sqlite can be isolated per connection/process which causes "no
            // such table" errors in some CI environments; a file-based DB is
            // more reliable across connections.
            $posDbPath = database_path('testing-pos.sqlite');

            if (! file_exists($posDbPath)) {
                // Ensure the database directory exists and create an empty file.
                if (! is_dir(dirname($posDbPath))) {
                    mkdir(dirname($posDbPath), 0777, true);
                }
                touch($posDbPath);
            }

            // Copy the testing connection config and point it at the file DB.
            $posConnection = config('database.connections.testing');
            $posConnection['database'] = $posDbPath;

            config(['database.connections.pos' => $posConnection]);

            // Purge any existing `pos` connection so the DatabaseManager will
            // recreate it using the updated config (prevents lingering MySQL
            // connection objects that still use information_schema queries).
            DB::purge('pos');
            DB::reconnect('pos');

            // Create minimal POS schema required by tests on the `pos`
            // connection (in-memory). This avoids 'no such table' errors
            // when code references Krypton models during tests.
            $schema = Schema::connection('pos');

            if (! $schema->hasTable('tables')) {
                $schema->create('tables', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                });
            }

            if (! $schema->hasTable('orders')) {
                $schema->create('orders', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('order_id')->nullable();
                    $table->string('status')->nullable();
                });
            }

            if (! $schema->hasTable('menus')) {
                $schema->create('menus', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                    $table->string('receipt_name')->nullable();
                    $table->decimal('price', 8, 2)->default(0);
                });
            }

            if (! $schema->hasTable('ordered_menus')) {
                $schema->create('ordered_menus', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('menu_id')->nullable();
                    $table->decimal('price', 8, 2)->nullable();
                    $table->decimal('sub_total', 8, 2)->nullable();
                    $table->decimal('tax', 8, 2)->nullable();
                    $table->string('note')->nullable();
                });
            }

            if (! $schema->hasTable('sessions')) {
                $schema->create('sessions', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('status')->nullable();
                });
            }

            if (! $schema->hasTable('order_checks')) {
                $schema->create('order_checks', function (Blueprint $table) {
                    $table->increments('id');
                    $table->decimal('subtotal_amount', 8, 2)->nullable();
                    $table->decimal('tax_amount', 8, 2)->nullable();
                    $table->decimal('discount_amount', 8, 2)->nullable();
                    $table->decimal('total_amount', 8, 2)->nullable();
                });
            }
        }
    }
}
