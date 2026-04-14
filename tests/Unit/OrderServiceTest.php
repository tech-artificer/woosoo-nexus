<?php

namespace Tests\Unit;

use App\Services\Krypton\KryptonContextService;
use App\Services\Krypton\OrderService;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    public function test_default_attributes_map_server_employee_log_id_from_employee_log_context(): void
    {
        $this->mock(KryptonContextService::class, function ($mock) {
            $mock->shouldReceive('getData')->andReturn([
                'session_id' => 99,
                'employee_log_id' => 12,
                'cashier_employee_id' => 7,
            ]);
        });

        $service = app(OrderService::class);
        $method = new \ReflectionMethod($service, 'getDefaultAttributes');
        $method->setAccessible(true);

        $defaults = $method->invoke($service);

        $this->assertSame(12, $defaults['start_employee_log_id']);
        $this->assertSame(12, $defaults['current_employee_log_id']);
        $this->assertSame(12, $defaults['close_employee_log_id']);
        $this->assertSame(12, $defaults['server_employee_log_id']);
        $this->assertSame(99, $defaults['session_id']);
    }

    public function test_default_attributes_keep_server_employee_log_id_null_when_context_missing(): void
    {
        $this->mock(KryptonContextService::class, function ($mock) {
            $mock->shouldReceive('getData')->andReturn([
                'session_id' => 100,
            ]);
        });

        $service = app(OrderService::class);
        $method = new \ReflectionMethod($service, 'getDefaultAttributes');
        $method->setAccessible(true);

        $defaults = $method->invoke($service);

        $this->assertNull($defaults['start_employee_log_id']);
        $this->assertNull($defaults['current_employee_log_id']);
        $this->assertNull($defaults['close_employee_log_id']);
        $this->assertNull($defaults['server_employee_log_id']);
        $this->assertSame(100, $defaults['session_id']);
    }
}
