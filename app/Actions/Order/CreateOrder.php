<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Request;
// use App\Repositories\Krypton\OrderRepository;
// use App\Repositories\Krypton\TerminalRepository;
// use App\Repositories\Krypton\EmployeeRepository;

use App\Models\Krypton\Session;
use App\Models\Krypton\Employee;
use App\Models\Krypton\EmployeePosition;
use App\Models\Krypton\Revenue;
use App\Models\Krypton\Terminal;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\TerminalService;

use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\OrderedMenu;
use App\Models\Krypton\TableOrder;
use App\Models\Krypton\TableLink;
use App\Models\Krypton\Table;

use Carbon\Carbon;

class CreateOrder
{
    use AsAction;

    public function handle(Request $request, array $params)
    {
        $today = Carbon::now();
        $session = new Session();
        $revenue = Revenue::select('id', 'price_level_id', 'tax_set_id')->where(['name' => 'In-house', 'is_active' => true ])->latest('created_on')->first();
        $terminal = Terminal::select(['id', 'type'])->pos($session->id)->first();
        $terminalService = TerminalService::latest('created_on')->first();
        $activeEmployees = Employee::getActiveEmployees();
        $cashierPosition = EmployeePosition::where('name', 'Cashier')->first();

        $cashier = null;
        foreach ($activeEmployees as $activeEmployee) {

            if( $activeEmployee->employee_position_id == $cashierPosition->id ) {
                $cashier = $activeEmployee;
                break;
            }
        }

        $terminalSession = TerminalSession::select(['id', 'transaction_count', 'session_id', 'terminal_session_id', 'date_time_opened', 'terminal_id', 'date_time_closed'])
                            ->whereNotNull('date_time_opened')
                            ->whereNull('date_time_closed')
                            ->latest('created_on')
                            ->first();

        $order = new Order();
        $orderCheck = new OrderCheck();
        $tableLink = new TableLink();
        $orderedMenu = new OrderedMenu();
        $tableOrder = new TableOrder();
        $table = Table::find($request->user()->table_id);
        
        $orderDetails = [
            'session_id' => $session->id,
            // 'terminal' => $terminal,
            // 'terminal_session' => $terminalSession,
            // 'revenue' => $revenue->id,
            // 'cashier' => $cashier,
            'terminal_session_id' => $terminalSession->id,
            'date_time_opened' => $terminalSession->date_time_opened,
            'date_time_closed' => NULL,
            'revenue_id' => $revenue->id,
            'terminal_id' => $terminal->id,
            'customer_id' => NULL,
            // 'current_terminal_id' => $terminalSession->terminal_id,
            // 'end_terminal_id' => $terminalSession->terminal_id,
            'is_open' => 1,
            'is_transferred' => 0,
            'is_voided' => '0',
            'guest_count' => $params['guest_count'],
            'service_type_id' => $terminalService->service_type_id,
            // 'is_available' => 1,
            // 'cash_tray_session_id' => 1,
            // 'server_banking_session_id' => '',
            'start_employee_log_id' => $cashier->id,
            'current_employee_log_id' => $cashier->id,
            'close_employee_log_id' => $cashier->id,
            'server_employee_log_id' => $cashier->id,
            // 'transaction_no' => '',
            'reference' => '',
            'cashier_employee_id' => $cashier->id,
            'terminal_service_id' => $terminalService->id,
            'is_online_order' => 0,
            // 'reprint_count'
        ];

        $order->fill(($orderDetails));
        $order->createOrder();
        $currentOrder = Order::latest('created_on')->first();

 
        $tableLink->order_id = $currentOrder->id;
        $tableLink->table_id = $table->id;
        $tableLink->primary_table_id =$table->id;
        $tableLink->link_color = 1;
        $tableLink->createLinkTable();

        $tableOrder->order_id = $currentOrder->id;
        $tableOrder->table_id = $table->id;
        $tableOrder->parent_table_id = NULL;
        $tableOrder->createTableOrder();
        
        // $table->changeTableStatus();

        return [
            'order' => $order,
            'orderCheck' => $orderCheck,
            'tableLink' => $tableLink,
            'orderedMenu' => $orderedMenu,
            'tableOrder' => $tableOrder,
            'table' => $table
        ];
        // return $order;
        // 'session_id' => '',
        // 'terminal_session_id' => '',
        // 'date_time_opened' => '',
        // 'date_time_closed' => '',
        // 'revenue_id' => '',
        // 'terminal_id' => '',
        // 'customer_id' => '',
        // 'current_terminal_id' => '',
        // 'end_terminal_id' => '',
        // 'customer_id' => '',
        // 'is_open' => '',
        // 'is_transferred' => '',
        // 'is_voided' => '',
        // 'guest_count' => '',
        // 'service_type_id' => '',
        // // 'is_available' => '',
        // // 'cash_tray_session_id' => '',
        // // 'server_banking_session_id' => '',
        // 'start_employee_log_id' => '',
        // 'current_employee_log_id' => '',
        // 'close_employee_log_id' => '',
        // 'server_employee_log_id' => '',
        // // 'transaction_no' => '',
        // 'reference' => '',
        // 'cashier_employee_id' => '',
        // 'terminal_service_id' => '',
        // 'is_online_order' => '',
        // // 'reprint_count'

    //     $orderDetails = [
    //         'session_id' => $session->id,
    //         'terminal' => $terminal,
    //         'terminal_session' => $terminalSession,
    //         'revenue' => $revenue->id,
    //         'cashier' => $cashier,
    //         'terminal_session_id' => $terminalSession->id,
    //         'date_time_opened' => $terminalSession->date_time_opened,
    //         // 'date_time_closed' => '',
    //         'revenue_id' => $revenue->id,
    //         'terminal_id' => $terminal->id,
    //         // 'customer_id' => '',
    //         'current_terminal_id' => $terminalSession->terminal_id,
    //         'end_terminal_id' => $terminalSession->terminal_id,
    //         'is_open' => 1,
    //         'is_transferred' => 0,
    //         'is_voided' => '0',
    //         'guest_count' => $params['guest_count'],
    //         'service_type_id' => $terminalService->service_type_id,
    //         'is_available' => 1,
    //         'cash_tray_session_id' => 1,
    //         // 'server_banking_session_id' => '',
    //         'start_employee_log_id' => $cashier->id,
    //         'current_employee_log_id' => $cashier->id,
    //         'close_employee_log_id' => $cashier->id,
    //         // 'server_employee_log_id' => '',
    //         // 'transaction_no' => '',
    //         'reference' => '',
    //         'cashier_employee_id' => $cashier->id,
    //         'terminal_service_id' => $terminalService->id,
    //         // 'is_online_order' => '',
    //         // 'reprint_count'
    //    ];
    }

    protected function createOrder() {}
    protected function createOrderCheck(Order $order) {

        $orderCheck = new OrderCheck();

        $details = [

            'order_id' => $order->id,
            'date_time_opened' => $order->date_time_opened,
            'is_voided' => $order->is_voided,
            'is_settled' => $order->is_settled,
            'from_split' => $order->from_split,
            'total_amount' => '',
            'paid_amount' => '',
            'change' => '',
            'subtotal_amount' => '',
            'tax_amount' => '',
            'discount_amount' => '',
            'transaction_number' => '',
            'gross_amount' => '',
            'taxable_amount' => '',
            'tax_exempt_amount' => '',
            'item_discount_amount' => '',
            'check_discount_amount' => '',
            'regular_guest_count' => '',
            'exempt_guest_count' => '',
            'surcharges_amount' => '',
            'tax_sales_amount' => '',
            'tax_exempt_sales_amount' => '',
            'guest_count' => $order->guest_count,
            'comp_discount' => '',
            'zero_rated_sales_amount' => '',
            'tax_sales_amount_discounted' => '',
            'tax_exempt_sales_amount_discounted' => '',
            'surcharge_vatable' => '',
            'surcharge_vat' => '',

        ];
        // $orderCheck->createOrderCheck();

    }
    protected function createOrderedMenu(Order $order) {

        $details = [
            'order_id',
            'menu_id',
            'price_level_id',
            'ordered_menu_id',
            'order_check_id',
            'modifier_adjective_id',
            'cancelled_order_id',
            'seat_number',
            'quantity',
            'price',
            'discount',
            'original_price',
            'tax',
            'time_sent',
            'index',
            'is_printed',
            'is_held',
            'name',
            'kitchen_name',
            'receipt_name',
            'menu_description',
            'note',
            'employee_log_id',
            'for_kitchen_display',
            'taxable_price',
            'item_discount',
            'check_discount',
            'unit_price',
            'tax_exempt',
            'cost',
            'is_tax_removed',
            'no_tax_price',
            'item_gross_total',
            'item_original_without_vat',
            'item_discount_main',
            'item_discount_adj',
            'vatable_original',
            'vatable_sales_discounted',
            'vatable_amount',
            'vatable_sub_total',
            'vat_exempt_sales',
            'vat_exempt_sales_discounted',
            'vat_exempt_amount',
            'vat_exempt_sub_total',
            'non_vatable_exempt_sales',
            'non_vatable_sub_total',
            'zero_rated_value',
            'zero_rated_sub_total',
            'sub_total'
        ];

    }

    protected function createTableOrder() {}
    protected function createOrderTransactionNo() {}
    // update tables
    // table links
    // table orders
    // transaction_no
    // transactions_summary
}
