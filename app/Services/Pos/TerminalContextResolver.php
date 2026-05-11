<?php

namespace App\Services\Pos;

use Illuminate\Support\Facades\DB;

class TerminalContextResolver
{
    public function resolve(string $terminalId): array
    {
        $session = DB::connection('pos')
            ->table('sessions')
            ->whereNull('date_time_closed')
            ->orderByDesc('id')
            ->first();

        if (! $session) {
            $session = DB::connection('pos')->table('sessions')->orderByDesc('id')->first();
        }

        $terminalSession = DB::connection('pos')
            ->table('terminal_sessions')
            ->where('terminal_id', $terminalId)
            ->whereNull('date_time_closed')
            ->orderByDesc('id')
            ->first();

        if (! $terminalSession) {
            $terminalSession = DB::connection('pos')
                ->table('terminal_sessions')
                ->where('terminal_id', $terminalId)
                ->orderByDesc('id')
                ->first();
        }

        $employeeLog = DB::connection('pos')
            ->table('employee_logs')
            ->whereNull('date_time_out')
            ->orderByDesc('id')
            ->first();

        if (! $employeeLog) {
            $employeeLog = DB::connection('pos')->table('employee_logs')->orderByDesc('id')->first();
        }

        $terminalService = DB::connection('pos')
            ->table('terminal_services')
            ->where('terminal_id', $terminalId)
            ->first();

        // Look up cash tray session for the current session and terminal
        $cashTraySession = null;
        if ($session && $terminalSession) {
            $cashTraySession = DB::connection('pos')
                ->table('cash_tray_sessions')
                ->where('session_id', $session->id)
                ->where('terminal_session_id', $terminalSession->id)
                ->whereNull('date_time_closed')
                ->orderByDesc('id')
                ->first();

            // Fallback: any open cash tray for this session
            if (! $cashTraySession) {
                $cashTraySession = DB::connection('pos')
                    ->table('cash_tray_sessions')
                    ->where('session_id', $session->id)
                    ->whereNull('date_time_closed')
                    ->orderByDesc('id')
                    ->first();
            }

            // Final fallback: most recent cash tray for this session
            if (! $cashTraySession) {
                $cashTraySession = DB::connection('pos')
                    ->table('cash_tray_sessions')
                    ->where('session_id', $session->id)
                    ->orderByDesc('id')
                    ->first();
            }
        }

        return [
            'session_id'          => (int) ($session->id ?? 0),
            'terminal_session_id' => $terminalSession ? (int) $terminalSession->id : null,
            'employee_log_id'     => (int) ($employeeLog->id ?? 1),
            'employee_id'         => (int) ($employeeLog->employee_id ?? 1),
            'revenue_id'          => (int) ($terminalService->revenue_id ?? 1),
            'service_type_id'     => (int) ($terminalService->service_type_id ?? 1),
            'terminal_service_id' => (int) ($terminalService->id ?? 1),
            'cash_tray_session_id' => $cashTraySession ? (int) $cashTraySession->id : null,
        ];
    }
}
