<?php

namespace App\Services\Krypton;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

use App\Models\Krypton\{
    Order,
    Menu,
    Session,
    Tax,
    EmployeeLog,
    Revenue,
    Terminal,
    TerminalSession,
    TerminalService,
    CashTraySession,
};

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Actions\Order\{
    CreateOrder,
    CreateOrderCheck,
    CreateTableOrder,
    CreateOrderedMenu
};

use App\Repositories\Krypton\MenuRepository;
use App\Enums\OrderStatus;

class OrderService
{
    public function processOrder(Device $device, array $attributes)
    {

        $defaults = $this->fetchDefaults();
        $attributes = array_merge($defaults, $attributes);
      
        return DB::transaction(function () use ($attributes, $device) {

            $order = CreateOrder::run($attributes);

            if (!$order) {
                return false;
            }
            
            $order->update([
                'end_terminal_id' => $order->terminal_id, 
                'cash_tray_session_id' => $attributes['cash_tray_session_id'],
                'cashier_employee_id' => $attributes['cashier_employee_id'],
            ]); 

            $attributes['order_id'] = $order->id;
            $order->orderCheck = CreateOrderCheck::run($attributes);
            $attributes['order_check_id'] = $order->orderCheck->id;
            $order->tableOrder = CreateTableOrder::run($attributes);
            $order->orderedMenus = CreateOrderedMenu::run($attributes);

            $device->orders()->create([
                'order_id' => $order->id,
                'table_id' => $device->table_id,
                'terminal_session_id' => $order->terminal_session_id,
                'status' => OrderStatus::CONFIRMED,
                'items' => $order->orderedMenus->toJson(),
                'meta' => [
                    'checks' => $order->orderCheck->toJson(),
                    'table_order' => $order->tableOrder->toJson(),
                ],
            ]);

            return $order;
        });
    }

    protected function fetchDefaults(): array
    {
        $session = Session::getLatestSession()->first();
        $terminalSession = TerminalSession::where(['session_id' => $session->id])->first();
        $terminalService = TerminalService::first();
        $terminal = Terminal::where('id', $terminalService->terminal_id)->first();
        $revenue = Revenue::where('id', $terminalService->revenue_id)->first();
        $employeeLogs = EmployeeLog::getEmployeeLogsForSession($session->id)->first();
        $cashTraySession = CashTraySession::where('session_id', $session->id)->first();

        return [
            'session_id' => $session->id ?? null,
            'terminal_session_id' => $terminalSession->id ?? null,
            'revenue_id' => $revenue->id ?? null,
            'terminal_id' => $terminal->id ?? null,
            'service_type_id' => $terminalService->service_type_id ?? null,
            'start_employee_log_id' =>  $employeeLogs->id,
            'current_employee_log_id' => $employeeLogs->id,
            'close_employee_log_id' =>  $employeeLogs->id,
            'server_employee_log_id' => null,
            'cashier_employee_id' => 2,
            'employee_log_id' => $employeeLogs->id ?? null,
            'terminal_service_id' => $terminalService->id ?? null,
            'tax_set_id' => $revenue->tax_set_id ?? null,
            'cash_tray_session_id' => $cashTraySession->id ?? null
        ];
    }
}
