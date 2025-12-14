<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Prevent tests running against a non-testing DB to avoid wiping production data.
if (env('DB_CONNECTION') !== 'testing' && env('APP_ENV') !== 'testing') {
    fwrite(STDERR, "Aborting tests: DB_CONNECTION must be 'testing' and APP_ENV must be 'testing' in phpunit.xml or environment.\n");
    exit(1);
}

// NOTE: POS connection mapping to the testing DB is performed in
// `Tests\TestCase::setUp()` once the application container has been
// created. Avoid calling `config()` here because the container isn't
// guaranteed to be available at Pest bootstrap time.

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

// Ensure minimal POS schema exists after RefreshDatabase migrations run.
// Pest's `beforeEach` runs after PHPUnit setUp (which includes
// `RefreshDatabase`), so creating the POS tables here guarantees they
// are present for tests that reference Krypton POS models.
beforeEach(function () {
    if (app()->environment('testing') || env('APP_ENV') === 'testing') {
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
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
