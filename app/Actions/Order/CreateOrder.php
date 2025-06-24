<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;

// use App\Repositories\Krypton\OrderRepository;
// use App\Repositories\Krypton\TerminalRepository;
// use App\Repositories\Krypton\EmployeeRepository;

use App\Models\Krypton\Session;
use App\Models\Krypton\Employee;
use App\Models\Krypton\EmployeePosition;
use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\OrderedMenu;
use App\Models\Krypton\Revenue;
use App\Models\Krypton\Terminal;
use App\Models\Krypton\TerminalSession;

use Carbon\Carbon;

class CreateOrder
{
    use AsAction;

    public function handle()
    {
        $today = Carbon::now();
        // $employeeLogRepository = new EmployeeLogRepository();
        $session = new Session();
        $revenue = Revenue::select('id', 'price_level_id', 'tax_set_id')->where(['name' => 'In-house', 'is_active' => true ])->latest('created_on')->first();
        $terminal = Terminal::select(['id', 'type'])->pos($session->id)->first();

        $activeEmployees = Employee::getActiveEmployees();
        $cashierPosition = EmployeePosition::where('name', 'Cashier')->first();

        $cashier = null;
        foreach ($activeEmployees as $activeEmployee) {

            if( $activeEmployee->employee_position_id == $cashierPosition->id ) {
                $cashier = $activeEmployee;
                break;
            }
        }

        // $employeeLogs = $employeeLogRepository->getEmployeeLogsForSession($session->id);
        // $employeeLatestLogs = EmployeeRepository::getLatestEmployeeLogs($session->id, );
        $terminalSession = TerminalSession::select(['id', 'terminal_session_id', 'date_time_opened', 'date_time_closed'])
                            ->whereDate('date_time_opened', $today)
                            ->whereNull('date_time_closed')
                            ->latest('created_on')
                            ->first();
       


        // $order = new Order();
        // $orderCheck = new OrderCheck();
        // $orderedMenu = new OrderedMenu();
        // $revenue = new Revenue();

       return [
            'session_id' => $session->id,
            'terminal' => $terminal,
            'terminal_session' => $terminalSession,
            'revenue' => $revenue,
            'cashier' => $cashier,
            // 'employee_logs' => $employeeLogs,
       ];
    }

    protected function createOrder() {}
    protected function createOrderCheck() {}
    protected function createOrderedMenu() {}
    protected function createTableOrder() {}
    protected function createOrderTransactionNo() {}
    // update tables
    // table links
    // table orders
}
