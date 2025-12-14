<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment and ensure the `pos` connection is
     * mapped to an in-memory sqlite database to avoid remote MySQL
     * connections during CI/test runs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            // Point the POS connection at the testing sqlite DB.
            config(['database.connections.pos' => config('database.connections.testing')]);
        }
    }
}
