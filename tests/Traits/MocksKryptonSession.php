<?php

namespace Tests\Traits;

use App\Exceptions\SessionNotFoundException;
use App\Services\Krypton\KryptonContextService;

trait MocksKryptonSession
{
    /**
     * Mock KryptonContextService to return a valid active session.
     * Use this for tests that expect order creation to succeed.
     */
    protected function mockActiveKryptonSession(array $overrides = []): void
    {
        $defaultSession = [
            'session_id' => 1,
            'terminal_id' => 1,
            'branch_id' => 1,
            'user_id' => 1,
            'terminal_name' => 'TEST_TERMINAL',
            'cashier_name' => 'Test Cashier',
        ];

        $sessionData = array_merge($defaultSession, $overrides);

        $this->mock(KryptonContextService::class, function ($mock) use ($sessionData) {
            $mock->shouldReceive('getData')
                 ->andReturn($sessionData);
        });
    }

    /**
     * Mock KryptonContextService to throw SessionNotFoundException.
     * Use this for tests that expect order creation to fail due to missing session.
     */
    protected function mockMissingKryptonSession(): void
    {
        $this->mock(KryptonContextService::class, function ($mock) {
            $mock->shouldReceive('getData')
                 ->andThrow(new SessionNotFoundException());
        });
    }

    /**
     * Mock KryptonContextService to return custom session data.
     * Use this when you need specific session attributes for edge case testing.
     */
    protected function mockKryptonSessionWith(array $sessionData): void
    {
        $this->mock(KryptonContextService::class, function ($mock) use ($sessionData) {
            $mock->shouldReceive('getData')
                 ->andReturn($sessionData);
        });
    }
}
