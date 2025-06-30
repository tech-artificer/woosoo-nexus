<?php

namespace App\Services\Krypton;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
// Krypton Models
use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\OrderedMenu;
use App\Models\Krypton\TableOrder;
use App\Models\Krypton\TableLink;
use App\Models\Krypton\Table;

use App\Models\Krypton\Session;
use App\Models\Krypton\Employee;
use App\Models\Krypton\Revenue;
use App\Models\Krypton\Terminal;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\TerminalService;
// App Models
use App\Models\Device;
use App\Models\DeviceOrder;
// Actions
use App\Actions\Order\CreateOrder;
use App\Actions\Order\CreateOrderedMenu;
use App\Actions\Order\CreateTableOrder;
use App\Actions\Order\CreateOrderCheck;


class OrderService
{

    public function create(Device $device, TerminalSession $terminalSession, array $attr)
    {
        $terminalService = TerminalService::where('terminal_id', $terminalSession->terminal_id)->first();
        $employee = Employee::with(['position'])->whereLike('first_name', 'ailene')->first(); 
        $revenue = Revenue::findOrFail($terminalService->revenue_id);

        $attr['session_id'] = $terminalSession->session_id;
        $attr['terminal_session_id'] = $terminalSession->id;
        $attr['terminal_id'] = $terminalSession->terminal_id;
        $attr['terminal_service_id'] = $terminalSession->terminal_service_id;
        $attr['revenue_id'] = $terminalService->revenue_id;
        $attr['service_type_id'] = $terminalService->service_type_id;
        $attr['start_employee_log_id'] = $employee->id;
        $attr['current_employee_log_id'] = $employee->id;
        $attr['close_employee_log_id'] = $employee->id;
        $attr['server_employee_log_id'] = $employee->id;
        $attr['cashier_employee_id'] = $employee->id;
        $attr['subtotal'] = $attr['total_amount'];

        return DB::transaction(function () use ($device, $revenue, $employee, $attr) {

            $order = CreateOrder::run($device, $attr);
            $transactionNo = $order->getTransactionNo($order->session_id);
            $order->transaction_no = $transactionNo;
            $order->save();

            $orderCheck = CreateOrderCheck::run($order, $attr);
            CreateOrderedMenu::run($order, $orderCheck, $revenue, $employee, $attr['items']);
            CreateTableOrder::run($device, $order);
            
            return $order;
        });
    }

}