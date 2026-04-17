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
            // Map the `pos` connection to the testing sqlite connection so
            // tests do not accidentally attempt to connect to the external
            // MySQL POS database. Keep the connection configuration identical
            // to the `testing` connection to allow creation of minimal POS
            // tables in tests when necessary.
            $posConnection = config('database.connections.testing');
            config(['database.connections.pos' => $posConnection]);

            // Purge any existing `pos` connection so the DatabaseManager will
            // recreate it using the updated config (prevents lingering MySQL
            // connection objects that still use information_schema queries).
            DB::purge('pos');
            DB::reconnect('pos');

            // Avoid contacting external broadcast drivers (e.g., Pusher) during tests.
            // Use the `log` broadcaster so broadcasts are harmless and local.
            config(['broadcasting.default' => 'log']);

            // Create minimal POS schema required by tests on the `pos`
            // connection (in-memory). This avoids 'no such table' errors
            // when code references Krypton models during tests.
            $schema = Schema::connection('pos');

            if (! $schema->hasTable('tables')) {
                $schema->create('tables', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                    $table->boolean('is_available')->default(false);
                    $table->boolean('is_locked')->default(false);
                });
            }

            if (! $schema->hasTable('orders')) {
                $schema->create('orders', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('order_id')->nullable();
                    $table->integer('session_id')->nullable();
                    $table->integer('terminal_session_id')->nullable();
                    $table->integer('guest_count')->nullable();
                    $table->string('status')->nullable();
                });
            }

            if (! $schema->hasTable('menu_groups')) {
                $schema->create('menu_groups', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                });
            }

            if (! $schema->hasTable('menus')) {
                $schema->create('menus', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                    $table->string('receipt_name')->nullable();
                    $table->decimal('price', 8, 2)->default(0);
                    $table->integer('menu_group_id')->nullable();
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

            if (! $schema->hasTable('table_orders')) {
                $schema->create('table_orders', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('order_id')->nullable();
                    $table->integer('table_id')->nullable();
                });
            }

            if (! $schema->hasTable('sessions')) {
                $schema->create('sessions', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('status')->nullable();
                    $table->dateTime('date_time_opened')->nullable();
                    $table->dateTime('date_time_closed')->nullable();
                });
            }

            if (! $schema->hasTable('order_checks')) {
                $schema->create('order_checks', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('order_id')->nullable();
                    $table->decimal('total_amount', 8, 2)->nullable();
                    $table->decimal('paid_amount', 8, 2)->nullable()->default(0);
                    $table->decimal('tax_amount', 8, 2)->nullable()->default(0);
                    $table->decimal('discount_amount', 8, 2)->nullable()->default(0);
                    $table->decimal('subtotal_amount', 8, 2)->nullable();
                });
            }

            // Additional POS tables required by KryptonContextService::load()
            // so that queries inside Cache::remember don't throw "no such table"
            // and abort the entire context load.

            if (! $schema->hasTable('terminals')) {
                $schema->create('terminals', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                });
                // Insert a default terminal row so Terminal::where('id', 1)->first()
                // returns a model instead of null.
                DB::connection('pos')->table('terminals')->insert(['id' => 1, 'name' => 'Test Terminal']);
            }

            if (! $schema->hasTable('terminal_sessions')) {
                $schema->create('terminal_sessions', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('terminal_id')->nullable();
                    $table->integer('session_id')->nullable();
                    $table->dateTime('date_time_opened')->nullable();
                    $table->dateTime('date_time_closed')->nullable();
                });
            }

            if (! $schema->hasTable('employee_logs')) {
                $schema->create('employee_logs', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('employee_id')->nullable();
                    $table->integer('terminal_id')->nullable();
                    $table->dateTime('date_time_in')->nullable();
                    $table->dateTime('date_time_out')->nullable();
                });
            }

            if (! $schema->hasTable('cash_tray_sessions')) {
                $schema->create('cash_tray_sessions', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('session_id')->nullable();
                });
            }

            if (! $schema->hasTable('terminal_services')) {
                $schema->create('terminal_services', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('terminal_id')->nullable();
                    $table->integer('revenue_id')->nullable();
                    $table->integer('service_type_id')->nullable();
                });
            }

            if (! $schema->hasTable('revenues')) {
                $schema->create('revenues', function (Blueprint $table) {
                    $table->increments('id');
                    $table->boolean('is_active')->default(false);
                    $table->integer('price_level_id')->nullable();
                    $table->integer('tax_set_id')->nullable();
                });
            }

            // Some Eloquent queries use `whereHas('table')` which generates
            // subqueries referencing the `tables` table on the default DB
            // connection. To avoid cross-connection missing-table errors in
            // tests, ensure a minimal `tables` table exists on the default
            // (testing) connection as well.
            $defaultConn = config('database.default');
            $schemaDefault = Schema::connection($defaultConn);
            if (! $schemaDefault->hasTable('tables')) {
                $schemaDefault->create('tables', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                });
            }

            // Ensure minimal device orders and items tables exist for tests on the default testing connection
            $testingSchema = Schema::connection('testing');

            // Recreate device_orders on both default and testing connections to
            // ensure test schema is fresh and includes all expected columns.
            if ($schemaDefault->hasTable('device_orders')) {
                $schemaDefault->dropIfExists('device_orders');
            }
            $schemaDefault->create('device_orders', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('device_id')->nullable();
                $table->integer('table_id')->nullable();
                $table->integer('terminal_session_id')->nullable();
                $table->integer('session_id')->nullable();
                $table->integer('order_id')->nullable();
                $table->string('order_number')->nullable();
                $table->uuid('order_uuid')->nullable()->unique();
                $table->string('status')->nullable();
                $table->decimal('subtotal', 8, 2)->nullable();
                $table->decimal('tax', 8, 2)->nullable();
                $table->decimal('discount', 8, 2)->nullable();
                $table->decimal('total', 8, 2)->nullable();
                $table->integer('guest_count')->nullable();
                $table->integer('branch_id')->nullable();
                $table->boolean('is_printed')->default(false);
                $table->timestamp('printed_at')->nullable();
                $table->string('printed_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            if ($testingSchema->hasTable('device_orders')) {
                $testingSchema->dropIfExists('device_orders');
            }
            $testingSchema->create('device_orders', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('device_id')->nullable();
                $table->integer('table_id')->nullable();
                $table->integer('terminal_session_id')->nullable();
                $table->integer('session_id')->nullable();
                $table->integer('order_id')->nullable();
                $table->string('order_number')->nullable();
                $table->uuid('order_uuid')->nullable()->unique();
                $table->string('status')->nullable();
                $table->decimal('subtotal', 8, 2)->nullable();
                $table->decimal('tax', 8, 2)->nullable();
                $table->decimal('discount', 8, 2)->nullable();
                $table->decimal('total', 8, 2)->nullable();
                $table->integer('guest_count')->nullable();
                $table->integer('branch_id')->nullable();
                $table->boolean('is_printed')->default(false);
                $table->timestamp('printed_at')->nullable();
                $table->string('printed_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            // Ensure device_order_items has the expected columns by dropping
            // any previous test table and recreating it on both connections.
            if ($schemaDefault->hasTable('device_order_items')) {
                $schemaDefault->dropIfExists('device_order_items');
            }
            $schemaDefault->create('device_order_items', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('order_id')->nullable();
                $table->integer('device_order_id')->nullable();
                $table->integer('menu_id')->nullable();
                $table->integer('ordered_menu_id')->nullable();
                $table->integer('quantity')->nullable();
                $table->decimal('price', 8, 2)->nullable();
                $table->decimal('subtotal', 8, 2)->nullable();
                $table->decimal('tax', 8, 2)->nullable();
                $table->decimal('total', 8, 2)->nullable();
                $table->string('notes')->nullable();
                $table->string('note')->nullable();
                $table->integer('seat_number')->nullable();
                $table->integer('index')->nullable();
                $table->boolean('is_refill')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });

            if ($testingSchema->hasTable('device_order_items')) {
                $testingSchema->dropIfExists('device_order_items');
            }
            $testingSchema->create('device_order_items', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('order_id')->nullable();
                $table->integer('device_order_id')->nullable();
                $table->integer('menu_id')->nullable();
                $table->integer('ordered_menu_id')->nullable();
                $table->integer('quantity')->nullable();
                $table->decimal('price', 8, 2)->nullable();
                $table->decimal('subtotal', 8, 2)->nullable();
                $table->decimal('tax', 8, 2)->nullable();
                $table->decimal('total', 8, 2)->nullable();
                $table->string('notes')->nullable();
                $table->string('note')->nullable();
                $table->integer('seat_number')->nullable();
                $table->integer('index')->nullable();
                $table->boolean('is_refill')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Helper: Create an active Krypton POS session for testing.
     * This ensures tests have a valid session_id from the POS context.
     *
     * @param array $attributes
     * @return int Session ID created
     */
    protected function createTestSession(array $attributes = []): int
    {
        $posSchema = Schema::connection('pos');
        
        // Ensure minimal required tables exist
        if (!$posSchema->hasTable('sessions')) {
            $posSchema->create('sessions', function (Blueprint $table) {
                $table->increments('id');
                $table->dateTime('date_time_opened')->nullable();
                $table->dateTime('date_time_closed')->nullable();
            });
        }

        // Insert a new active session
        $sessionId = DB::connection('pos')->table('sessions')->insertGetId(array_merge([
            'date_time_opened' => now(),
            'date_time_closed' => null,  // Active (not closed)
        ], $attributes));

        return $sessionId;
    }
}
