<?php

namespace App\Http\Controllers\Api\V1\Krypton;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Krypton\Session;
use App\Models\Krypton\CashTraySession;
use App\Models\Krypton\Tax;
use App\Models\Krypton\EmployeeLog;
use App\Models\Krypton\Revenue;
use App\Models\Krypton\Terminal;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\TerminalService;
use App\Models\Krypton\TableOrder;


class TerminalSessionApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $session = Session::getLatestSession()->first();

        // if (!$session) {
        //     return response()->json(['message' => 'No active session found'], 404);
        // }

        // // Check if the session is already closed
        // if ($session->date_time_closed !== null) {
        //     return response()->json(['message' => 'Session already closed'], 400);
        // }

        // // Check if there's an open cash tray session
        // $cashTraySession = CashTraySession::where('session_id', $session->id)
        //     ->where('is_open', true)
        //     ->exists();

        // if ($cashTraySession) {
        //     return response()->json(['message' => 'Cash tray session already open'], 400);
        // }

        // // Check terminal session
        // $terminalSession = TerminalSession::select([
        //         'id',
        //         'date_time_opened',
        //         'terminal_id',
        //         'session_id',
        //         'terminal_session_id'
        //     ])
        //     ->where('session_id', $session->id)
        //     ->first();

        // if (!$terminalSession) {
        //     return response()->json(['message' => 'No terminal session found'], 404);
        // }

        // if ($terminalSession->date_time_closed !== null) {
        //     return response()->json(['message' => 'Terminal session already closed'], 400);
        // }

            
        
        // $terminalService = TerminalService::select('id', 'alias', 'service_type_id', 'revenue_id', 'terminal_id')->first();
        // $terminal = Terminal::select('id', 'receipt_prefix')->where('id', $terminalService->terminal_id)->first();
        // $revenue = Revenue::select('id', 'price_level_id', 'tax_set_id', 'name')->where('id', $terminalService->revenue_id)->first();
        // $employeeLogsForSession = EmployeeLog::getEmployeeLogsForSession($session->id)->first();
        // $employeeLogs = EmployeeLog::getEmployeeLog($employeeLogsForSession->id);
        // $employeeLogs2 = EmployeeLog::getEmployeeLog($employeeLogsForSession[1]->id) ?? null;
        // $tableOrders = TableOrder::fromQuery('CALL get_active_table_orders()');

        // return response()->json([
        //     // 'session' => $session,
        //     'terminalSession' => $terminalSession,
        //     // 'terminalService' => $terminalService,
        //     // 'terminal' => $terminal,
        //     // 'revenue' => $revenue,
        //     'cashTraySession' => $cashTraySession,
        //     // 'employeeLogsForSession' => $employeeLogsForSession,
        //     // 'employeeLogs' => $employeeLogs,
        //     // 'employeeLogs1' => $employeeLogs2,
        //     // 'tableOrders' => $tableOrders,
        // ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
