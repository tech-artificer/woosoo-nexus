<?php

namespace App\Testing\Fakes\Krypton;

use App\Services\Krypton\KryptonContextService;

class FakeKryptonContextService extends KryptonContextService
{
    public function getCurrentSessions(): array
    {
        return [
            'terminal' => ['id' => 0, 'name' => 'fake-terminal'],
            'session' => ['id' => 1],
            'terminalSession' => ['id' => 1],
            'employeeLog' => ['id' => 1],
            'cashTraySession' => ['id' => 1],
            'terminalService' => ['id' => 1],
            'sessionFlag' => true,
        ];
    }

    public function getData(): array
    {
        return [
            'price_level_id' => null,
            'tax_set_id' => null,
            'service_type_id' => null,
            'revenue_id' => null,
            'session_id' => 1,
            'terminal_id' => null,
            'terminal_session_id' => null,
            'employee_log_id' => null,
            'cash_tray_session_id' => null,
            'terminal_service_id' => null,
            'employee_id' => null,
            'cashier_employee_id' => null,
            'server_employee_log_id' => null,
        ];
    }

    public function clearCache(): void
    {
        // No-op for deterministic tests.
    }
}
