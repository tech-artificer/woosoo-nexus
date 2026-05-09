<?php

namespace App\Services\Krypton;

use App\Exceptions\SessionNotFoundException;
use App\Models\Krypton\CashTraySession;
use App\Models\Krypton\EmployeeLog;
use App\Models\Krypton\Revenue;
use App\Models\Krypton\Session;
use App\Models\Krypton\Terminal;
use App\Models\Krypton\TerminalService;
use App\Models\Krypton\TerminalSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class KryptonContextService
{
    private array $currentSessions = [];

    private array $data = [];

    private bool $loaded = false;

    public function __construct()
    {
        // stay clean — no DB calls here
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        try {
            // Cache for 30 seconds (tweak as needed)
            [$this->currentSessions, $this->data] = Cache::remember('krypton.context', now()->addSeconds(30), function () {
                $today = Carbon::now();
                $flag = true;

                $terminal = Terminal::where('id', config('api.krypton.terminal_id', 1))->first();

                $terminalSession = TerminalSession::query()
                    ->when($terminal, fn ($query) => $query->where('terminal_id', $terminal->id))
                    ->whereNull('date_time_closed')
                    ->orderByDesc('id')
                    ->first();

                $session = null;
                if ($terminalSession?->session_id) {
                    $session = Session::query()
                        ->where('id', $terminalSession->session_id)
                        ->whereNull('date_time_closed')
                        ->first();
                }

                // If no session from terminal, look for ANY open session (not just today's)
                // Use fallback immediately instead of restricting to today first
                if (! $session) {
                    $session = Session::query()
                        ->whereNull('date_time_closed')
                        ->orderByDesc('id')
                        ->first();
                }

                $employeeLog = EmployeeLog::query()
                    ->when($session && $this->posColumnExists('employee_logs', 'session_id'), fn ($query) => $query->where('session_id', $session->id))
                        ->when($terminal && $this->posColumnExists('employee_logs', 'terminal_id'), fn ($query) => $query->where('terminal_id', $terminal->id))
                    ->whereNull('date_time_out')
                    ->orderByDesc('id')
                    ->first();

                    // Fallback: if no employee log found for current session, use any active log
                    if (! $employeeLog) {
                        $employeeLog = EmployeeLog::query()
                            ->when($terminal && $this->posColumnExists('employee_logs', 'terminal_id'), fn ($query) => $query->where('terminal_id', $terminal->id))
                            ->whereNull('date_time_out')
                            ->orderByDesc('id')
                            ->first();
                    }

                $cashTraySession = null;
                if ($session) {
                    $cashTraySession = CashTraySession::query()
                        ->where('session_id', $session->id)
                        ->when($terminalSession && $this->posColumnExists('cash_tray_sessions', 'terminal_session_id'), fn ($query) => $query->where('terminal_session_id', $terminalSession->id))
                        ->when($terminal && $this->posColumnExists('cash_tray_sessions', 'terminal_id'), fn ($query) => $query->where('terminal_id', $terminal->id))
                        ->orderByDesc('id')
                        ->first();
                }

                $terminalService = $terminal
                    ? TerminalService::where('terminal_id', $terminal->id)->first()
                    : null;

                $revenue = $terminalService
                    ? Revenue::where([
                        'id' => $terminalService->revenue_id,
                        'is_active' => true,
                    ])->first()
                    : null;

                $currentSessions = [
                    'terminal' => $terminal,
                    'session' => $session,
                    'terminalSession' => $terminalSession,
                    'employeeLog' => $employeeLog,
                    'cashTraySession' => $cashTraySession,
                    'terminalService' => $terminalService,
                    'sessionFlag' => $flag,
                ];

                // Enforce non-negotiable business rule: session_id MUST exist from Krypton
                if (! $session) {
                    throw new SessionNotFoundException(
                        'No active POS session found. Transaction cannot proceed. Ensure POS system is running and a session is opened.'
                    );
                }

                $data = [
                    'price_level_id' => $revenue?->price_level_id,
                    'tax_set_id' => $revenue?->tax_set_id,
                    'service_type_id' => $terminalService?->service_type_id,
                    'revenue_id' => $terminalService?->revenue_id,
                    'terminal_id' => $terminal?->id,
                    'session_id' => $session->id,
                    'terminal_session_id' => $terminalSession?->id,
                    'employee_log_id' => $employeeLog?->id,
                    'cash_tray_session_id' => $cashTraySession?->id,
                    'terminal_service_id' => $terminalService?->id,
                    'employee_id' => $employeeLog?->employee_id,
                    'cashier_employee_id' => $employeeLog?->employee_id,
                    // Native Krypton tablet-era rows leave this blank. Keep it null
                    // unless the POS app starts requiring a server log explicitly.
                    'server_employee_log_id' => null,
                ];

                return [$currentSessions, $data];
            });
        } catch (\Throwable $e) {
            Log::warning('KryptonContextService failed to load: '.$e->getMessage());
            $this->currentSessions = [];
            $this->data = [];
        }

        $this->loaded = true;
    }

    public function getCurrentSessions(): array
    {
        $this->load();

        return $this->currentSessions;
    }

    public function getData(): array
    {
        $this->load();

        return $this->data;
    }

    public function clearCache(): void
    {
        Cache::forget('krypton.context');
        $this->loaded = false;
    }

    private function posColumnExists(string $table, string $column): bool
    {
        try {
            return Schema::connection('pos')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
