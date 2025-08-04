<?php
namespace App\Services\Krypton;

use Illuminate\Support\Facades\DB;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\EmployeeLog;
use App\Models\Krypton\Session;
use App\Models\Krypton\Terminal;
use App\Models\Krypton\CashTraySession;
use App\Models\Krypton\TerminalService;
use App\Models\Krypton\Revenue;
use Carbon\Carbon;

class KryptonContextService
{  
    public $currentSessions = [];
    public $data = [];

    public function __construct()
    {
        $today = Carbon::now();
        $flag = true;


        $terminal = Terminal::where(['id' => 1 ])->first();

        $sessionId = Session::fromQuery('CALL get_latest_session_id()')->first();
        $session = Session::query()
            ->whereNull('date_time_closed')
            ->whereDate('date_time_opened', '=', $today)
            ->orderByDesc('id')
            ->first();

        if( !$session ) {
            $flag = false;
            $session = Session::query()
                ->whereNull('date_time_closed')
                ->orderByDesc('id')
                ->first();
        }

        $terminalSession = TerminalSession::query()
            ->whereNull('date_time_closed')
            ->orderByDesc('id')
            ->first();

        $employeeLog = EmployeeLog::query()
            ->whereNull('date_time_out')
            ->orderByDesc('id')
            ->first();
        
        $cashTraySession = CashTraySession::where('session_id', $session->id)->first();
            // ->where('session_id', $sessionId)
            // ->orderByDesc('id')
            // ->first();
          
        $terminalService = TerminalService::where('terminal_id', $terminal->id)->first();
        
        $revenue = Revenue::where(['id' => $terminalService->revenue_id, 'is_active' => true])->first();

        $priceLevelId = $revenue->price_level_id;
        $taxSetId = $revenue->tax_set_id;
        $serviceTypeId = $terminalService->service_type_id;
        $revenueId = $terminalService->revenue_id;
        $terminalId = $terminal->id;
        $sessionId = $session->id;
        $terminalSessionId = $terminalSession->id;
        $employeeLogId = $employeeLog->id;
        $cashTraySessionId = $cashTraySession->id;
        $terminalServiceId = $terminalService->id;
        $employeeId = $employeeLog->employee_id;

        $this->currentSessions = [
            'terminal' => $terminal,
            'session' => $session,
            'terminalSession' => $terminalSession,
            'employeeLog' => $employeeLog,
            'cashTraySession' => $cashTraySession,
            'terminalService' => $terminalService,
            'sessionFlag' => $flag,
        ];

        $this->data = [
            'price_level_id' => $priceLevelId,
            'tax_set_id' => $taxSetId,
            'service_type_id' => $serviceTypeId,
            'revenue_id' => $revenueId,
            'terminal_id' => $terminalId,
            'session_id' => $sessionId,
            'terminal_session_id' => $terminalSessionId,
            'employee_log_id' => $employeeLogId,
            'cash_tray_session_id' => $cashTraySessionId,
            'terminal_service_id' => $terminalServiceId,
            'employee_id' => $employeeId,
            'cashier_employee_id' => $employeeId
        ];
    }

    public function getCurrentSessions(): array
    {   
        return $this->currentSessions;
    }

    public function getData(): array
    {
        return $this->data;
    }
}