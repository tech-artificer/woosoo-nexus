<?php

namespace Tests\Feature\Providers;

use App\Services\Krypton\KryptonContextService;
use App\Testing\Fakes\Krypton\FakeKryptonContextService;
use Closure;
use Inertia\Inertia;
use Tests\TestCase;

class KryptonContextServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(KryptonContextService::class, function () {
            return new FakeKryptonContextService;
        });
    }

    public function test_krypton_backed_inertia_props_are_registered_as_lazy_closures(): void
    {
        $expectedKeys = [
            'terminal',
            'session',
            'terminalSession',
            'employeeLog',
            'cashTraySession',
            'terminalService',
            'sessionFlag',
        ];

        foreach ($expectedKeys as $key) {
            $shared = Inertia::getShared($key);

            $this->assertInstanceOf(
                Closure::class,
                $shared,
                "Expected Inertia shared prop [{$key}] to be lazily registered as a closure."
            );
        }
    }

    public function test_krypton_context_service_resolves_to_fake_in_tests(): void
    {
        $resolved = app(KryptonContextService::class);

        $this->assertInstanceOf(FakeKryptonContextService::class, $resolved);
    }

    public function test_invoking_lazy_terminal_prop_resolves_through_fake_without_pos_access(): void
    {
        /** @var Closure $sharedTerminal */
        $sharedTerminal = Inertia::getShared('terminal');

        $resolvedTerminal = app()->call($sharedTerminal);

        $this->assertSame(
            ['id' => 0, 'name' => 'fake-terminal'],
            $resolvedTerminal
        );
    }
}
