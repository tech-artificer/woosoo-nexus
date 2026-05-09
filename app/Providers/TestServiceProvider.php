<?php

namespace App\Providers;

use App\Repositories\Krypton\EmployeeRepository;
use App\Repositories\Krypton\MenuRepository;
use App\Repositories\Krypton\OrderItemRepository;
use App\Repositories\Krypton\OrderRepository;
use App\Repositories\Krypton\ReportRepository;
use App\Repositories\Krypton\SessionRepository;
use App\Repositories\Krypton\TableRepository;
use App\Repositories\Krypton\TerminalRepository;
use App\Testing\Fakes\Krypton\FakeEmployeeRepository;
use App\Testing\Fakes\Krypton\FakeMenuRepository;
use App\Testing\Fakes\Krypton\FakeOrderItemRepository;
use App\Testing\Fakes\Krypton\FakeOrderRepository;
use App\Testing\Fakes\Krypton\FakeReportRepository;
use App\Testing\Fakes\Krypton\FakeSessionRepository;
use App\Testing\Fakes\Krypton\FakeTableRepository;
use App\Testing\Fakes\Krypton\FakeTerminalRepository;
use Illuminate\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
    /**
     * Register services for testing environment.
     */
    public function register(): void
    {
        if (! $this->isRunningTests()) {
            return;
        }

        // Bind Krypton repositories to light-weight fakes to avoid stored
        // procedure calls and external POS dependencies during tests.
        $this->app->bind(TableRepository::class, function () {
            return new FakeTableRepository;
        });

        $this->app->bind(OrderRepository::class, function () {
            return new FakeOrderRepository;
        });

        $this->app->bind(MenuRepository::class, function () {
            return new FakeMenuRepository;
        });

        $this->app->bind(SessionRepository::class, function () {
            return new FakeSessionRepository;
        });

        $this->app->bind(TerminalRepository::class, function () {
            return new FakeTerminalRepository;
        });

        $this->app->bind(ReportRepository::class, function () {
            return new FakeReportRepository;
        });

        $this->app->bind(OrderItemRepository::class, function () {
            return new FakeOrderItemRepository;
        });

        $this->app->bind(EmployeeRepository::class, function () {
            return new FakeEmployeeRepository;
        });
    }

    public function boot(): void
    {
        // Nothing to bootstrap for tests.
    }

    private function isRunningTests(): bool
    {
        return $this->app->runningUnitTests()
            || $this->app->environment('testing')
            || env('APP_ENV') === 'testing';
    }
}
