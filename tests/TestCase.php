<?php

namespace Tests;

use App\Models\Branch;
use App\Services\LocalBranchResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    private const TEST_KRYPTON_SESSION_CACHE_KEY = 'testing.krypton.session_id';

    /**
     * Lane A: opt-out for tests that intentionally exercise the multi-branch
     * ambiguity error path in LocalBranchResolver (e.g. LocalBranchIdentityTest).
     */
    protected bool $skipAutoBranchSeed = false;

    /**
     * Ensure RefreshDatabase does not pass a false --seed flag that still
     * triggers DatabaseSeeder side effects in this suite.
     */
    protected function migrateFreshUsing()
    {
        $parameters = [
            '--database' => 'testing',
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
            '--force' => true,
        ];

        if ($this->shouldSeed()) {
            $parameters['--seed'] = true;
        }

        if ($seeder = $this->seeder()) {
            $parameters['--seeder'] = $seeder;
        }

        return $parameters;
    }

    protected function shouldDropViews()
    {
        return false;
    }

    protected function shouldDropTypes()
    {
        return false;
    }

    /**
     * Ensure framework-driven test migrations never hit interactive
     * confirmation paths in RefreshDatabase.
     */
    public function artisan($command, $parameters = [])
    {
        if ($command === 'migrate:fresh') {
            if (! array_key_exists('--force', $parameters)) {
                $parameters['--force'] = true;
            }

            if (! array_key_exists('--database', $parameters)) {
                $parameters['--database'] = 'testing';
            }
        }

        return parent::artisan($command, $parameters);
    }

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

        // Docker test runs in this repo may bootstrap with APP_ENV=production.
        // Normalize both Laravel's resolved environment and env()/config lookups
        // so test-only fallbacks consistently activate.
        $this->app['env'] = 'testing';
        config(['app.env' => 'testing']);
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        // Skip Vite manifest resolution in tests — assets are not built locally.
        $this->withoutVite();

        // Always run in tests — TestCase.php is only used by the test suite.
        // The original env-detection guard was unreliable in Docker (APP_ENV=production
        // at bootstrap even during phpunit runs), causing the pos connection to remain
        // pointed at krypton_woosoo instead of the in-memory SQLite test DB.
        if (true) {
            Cache::forget(self::TEST_KRYPTON_SESSION_CACHE_KEY);

            // Map the `pos` connection to the testing sqlite connection so
            // tests do not accidentally attempt to connect to the external
            // MySQL POS database. Keep the connection configuration identical
            // to the `testing` connection to allow creation of minimal POS
            // tables in tests when necessary.
            $posConnection = [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ];
            config(['database.connections.pos' => $posConnection]);
            config(['database.connections.krypton_woosoo' => $posConnection]);

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
                    $table->integer('cash_tray_session_id')->nullable();
                    $table->integer('server_employee_log_id')->nullable();
                    $table->integer('close_employee_log_id')->nullable();
                    $table->integer('cashier_employee_id')->nullable();
                    $table->integer('end_terminal_id')->nullable();
                    $table->integer('guest_count')->nullable();
                    $table->string('reference')->nullable();
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
                    $table->integer('menu_tax_type_id')->nullable();
                    $table->boolean('is_modifier_only')->default(false);
                    $table->boolean('is_available')->default(true);
                    $table->boolean('is_discountable')->default(false);
                    $table->boolean('is_taxable')->default(true);
                });
            }

            if (! $schema->hasTable('taxes')) {
                $schema->create('taxes', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                    $table->decimal('percentage', 5, 2)->default(0);
                    $table->integer('rounding')->default(0);
                });
            }

            if (! $schema->hasTable('ordered_menus')) {
                $schema->create('ordered_menus', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('order_id')->nullable();
                    $table->integer('order_check_id')->nullable();
                    $table->integer('menu_id')->nullable();
                    $table->decimal('price', 8, 2)->nullable();
                    $table->decimal('original_price', 8, 2)->nullable();
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
                    $table->integer('session_id')->nullable();
                    $table->dateTime('date_time_in')->nullable();
                    $table->dateTime('date_time_out')->nullable();
                });
            }

            if (! $schema->hasTable('cash_tray_sessions')) {
                $schema->create('cash_tray_sessions', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('session_id')->nullable();
                    $table->integer('terminal_session_id')->nullable();
                    $table->integer('terminal_id')->nullable();
                    $table->integer('employee_log_id')->nullable();
                    $table->boolean('is_open')->nullable();
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
            // Only perform schema creation/recreation on SQLite connections.
            // When the default connection is MySQL (e.g., Docker with APP_ENV=production),
            // the production schema already has all required tables — skip destructive DDL.
            $defaultIsSqlite = config("database.connections.{$defaultConn}.driver") === 'sqlite';

            if ($defaultIsSqlite && ! $schemaDefault->hasTable('tables')) {
                $schemaDefault->create('tables', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('name')->nullable();
                });
            }

            // Ensure minimal device orders and items tables exist for tests on the default testing connection
            $testingSchema = Schema::connection('testing');

            // Recreate device_orders on both default and testing connections to
            // ensure test schema is fresh and includes all expected columns.
            if ($defaultIsSqlite) {
                if ($schemaDefault->hasTable('device_orders')) {
                    $schemaDefault->dropIfExists('device_orders');
                }
                $schemaDefault->create('device_orders', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('void_reason')->nullable();
                    $table->integer('device_id')->nullable();
                    $table->integer('table_id')->nullable();
                    $table->integer('terminal_session_id')->nullable();
                    $table->integer('session_id')->nullable();
                    $table->integer('order_id')->nullable();
                    $table->string('order_number')->nullable();
                    $table->uuid('order_uuid')->nullable()->unique();
                    $table->string('status')->nullable();
                    $table->smallInteger('recalled')->default(0);
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
            }

            // Ensure device_order_items has the expected columns by dropping
            // any previous test table and recreating it on both connections.
            if ($defaultIsSqlite && $schemaDefault->hasTable('device_order_items')) {
                $schemaDefault->dropIfExists('device_order_items');
            }
            if ($defaultIsSqlite) {
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
                    $table->boolean('done')->default(false);
                    $table->timestamp('done_at')->nullable();
                    $table->boolean('is_printed')->default(false);
                    $table->timestamp('printed_at')->nullable();
                    $table->unsignedBigInteger('printed_by_print_event_id')->nullable();
                    $table->string('print_type')->nullable();
                    $table->uuid('client_submission_id')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }

            if ($testingSchema->hasTable('device_orders')) {
                $testingSchema->dropIfExists('device_orders');
            }
            $testingSchema->create('device_orders', function (Blueprint $table) {
                $table->increments('id');
                $table->string('void_reason')->nullable();
                $table->integer('device_id')->nullable();
                $table->integer('table_id')->nullable();
                $table->integer('terminal_session_id')->nullable();
                $table->integer('session_id')->nullable();
                $table->integer('order_id')->nullable();
                $table->string('order_number')->nullable();
                $table->uuid('order_uuid')->nullable()->unique();
                $table->string('status')->nullable();
                $table->smallInteger('recalled')->default(0);
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
                $table->boolean('done')->default(false);
                $table->timestamp('done_at')->nullable();
                $table->boolean('is_printed')->default(false);
                $table->timestamp('printed_at')->nullable();
                $table->unsignedBigInteger('printed_by_print_event_id')->nullable();
                $table->string('print_type')->nullable();
                $table->uuid('client_submission_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Lane A: preserve the real single-branch invariant without polluting
        // tests that intentionally create their own branch fixtures.
        // - zero branches: lazily create exactly one synthetic branch unless opted out
        // - one branch: resolve it normally
        // - multiple branches: keep the ambiguity failure path intact
        $skipAutoBranchSeed = $this->skipAutoBranchSeed;
        $this->app->forgetInstance(LocalBranchResolver::class);
        $this->app->instance(LocalBranchResolver::class, new class($skipAutoBranchSeed) extends LocalBranchResolver
        {
            public function __construct(private readonly bool $skipAutoBranchSeed) {}

            public function resolve(): ?Branch
            {
                $count = Branch::query()->count();

                if ($count === 1) {
                    return Branch::query()->first();
                }

                if ($count === 0 && ! $this->skipAutoBranchSeed) {
                    return Branch::create([
                        'name' => 'Test Branch',
                        'location' => 'Test Location',
                    ]);
                }

                return null;
            }
        });

        // Lane B: disable CSRF token validation for feature tests. Laravel 11's
        // default web group includes ValidateCsrfToken; Pest's $this->post(...) does
        // not mint tokens, so web POSTs would otherwise return 419. CSRF is
        // config-driven and not the subject of any unit test in this suite.
        $this->withoutMiddleware([ValidateCsrfToken::class]);
    }

    /**
     * Helper: Create an active Krypton POS session for testing.
     * This ensures tests have a valid session_id from the POS context.
     *
     * @return int Session ID created
     */
    protected function createTestSession(array $attributes = []): int
    {
        $posSchema = Schema::connection('pos');

        // Ensure minimal required tables exist
        if (! $posSchema->hasTable('sessions')) {
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

        Cache::put(self::TEST_KRYPTON_SESSION_CACHE_KEY, $sessionId, now()->addHour());

        return $sessionId;
    }

    /**
     * Drain any open transactions to prevent cascading test failures
     * when a test leaves the database mid-transaction. SQLite savepoint
     * desync can cause "There is already an active transaction" on the next test.
     */
    private function drainOpenTransactions(): void
    {
        foreach (['testing', 'pos'] as $connection) {
            try {
                $conn = DB::connection($connection);
                $level = 0;
                // Get the transaction level safely
                try {
                    $level = $conn->transactionLevel();
                } catch (\Throwable) {
                    // If we can't get the level, assume we need to purge
                    $level = 1;
                }

                // Rollback all nested transactions/savepoints
                while ($level > 0) {
                    try {
                        $conn->rollBack();
                        $level--;
                    } catch (\Throwable) {
                        // If rollback fails, force purge and reconnect
                        DB::purge($connection);
                        DB::reconnect($connection);
                        break;
                    }
                }
            } catch (\Throwable) {
                // Silently ignore if any connection errors occur
                // Real test failure will surface if this is critical
            }
        }
    }

    /**
     * Ensure open transactions are cleaned up after every test to prevent
     * PDOException cascades when manual transaction handling leaves savepoints open.
     * Call drainOpenTransactions BEFORE parent::tearDown() so we intercept
     * open transactions before RefreshDatabase's cleanup runs.
     */
    protected function tearDown(): void
    {
        try {
            $this->drainOpenTransactions();
        } catch (\Throwable) {
            // Ignore errors from drain to never mask a real test failure
        } finally {
            parent::tearDown();
        }
    }
}
