<?php
namespace App\Services\Krypton;

use Illuminate\Support\Facades\DB;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\EmployeeLog;
use App\Models\Krypton\Session;
class KryptonContextService
{
    public function getCurrentSessions(): array
    {
        // Example query logic, update to fit actual schema
        $session = Session::query()
            ->whereNull('date_time_closed')
            ->orderByDesc('id')
            ->first();

        $terminalSession = TerminalSession::query()
            ->whereNull('date_time_closed')
            ->orderByDesc('id')
            ->first();

        $employeeLog = EmployeeLog::query()
            ->whereNull('date_time_out')
            ->orderByDesc('id')
            ->first();

        return [
            'session' => $session,
            'terminalSession' => $terminalSession,
            'employeeLog' => $employeeLog,
        ];
    }
}