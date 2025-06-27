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
        $tableLink = new TableLink();
        $orderedMenu = new OrderedMenu();
        $tableOrder = new TableOrder();
        $table = Table::find($request->user()->table_id);
        
        $orderDetails = [
            'session_id' =>  269, //$session->id,
            // 'terminal' => $terminal,
            // 'terminal_session' => $terminalSession,
            // 'revenue' => $revenue->id,
            // 'cashier' => $cashier,
            'terminal_session_id' => 270,//$terminalSession->id,
            'date_time_opened' => $terminalSession->date_time_opened,
            'date_time_closed' => NULL,
            'revenue_id' => 1, //$revenue->id,
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
            'start_employee_log_id' => 287, //$cashier->id,
            'current_employee_log_id' => 287, //$cashier->id,
            'close_employee_log_id' => 287, //$cashier->id,
            'server_employee_log_id' => 287, //$cashier->id,
            // 'transaction_no' => '',
            'reference' => '',
            'cashier_employee_id' => 2, //$cashier->id,
            'terminal_service_id' => 1, //$terminalService->id,
            'is_online_order' => 0,
            // 'reprint_count'
        ];

        $order->fill(($orderDetails));
        $order->createOrder();
        $currentOrder = Order::find(18739);

 
        // $tableLink->order_id = $currentOrder->id;
        // $tableLink->table_id = $table->id;
        // $tableLink->primary_table_id =$table->id;
        // $tableLink->link_color = 1;
        // $tableLink->createLinkTable();

        $tableOrder->order_id = $currentOrder->id;
        $tableOrder->table_id = $table->id;
        $tableOrder->parent_table_id = NULL;
        $tableOrder->createTableOrder();

        $orderCheck = $this->createOrderCheck($currentOrder, $params);
        
        // $table->changeTableStatus();

        return [
            'order' => $order,
            // 'ordered_menus' => $params['items'],
            // 'orderCheck' => $orderCheck,
            // 'tableLink' => $tableLink,
            // 'orderedMenu' => $orderedMenu,
            // 'tableOrder' => $tableOrder,
            // 'table' => $table
        ];
    }

    // add transaction number table

    protected function createOrder(Order $order, array $params) {

    }
    protected function createOrderCheck(Order $order, array $params) {

        $orderCheck = new OrderCheck();

        $total = $params['total_amount'];
        $subtotal = $total;
        $guestCount = $params['guest_count'];

        $details = [
            'order_id' => $order->id,
            'date_time_opened' => Carbon::parse($order->date_time_opened),
            'is_voided' => $order->is_voided,
            'is_settled' => 0,
            'from_split' => 0,
            'total_amount' => $total,
            'paid_amount' => 0.0,
            'change' => 0.0,
            'subtotal_amount' => $total,
            'tax_amount' => 0.0,
            'discount_amount' => 0.0,
            'transaction_number' => 4,
            'gross_amount' => 0.0,
            'taxable_amount' => 0.0,
            'tax_exempt_amount' => 0.0,
            'item_discount_amount' => 0.0,
            'check_discount_amount' => 0.0,
            'regular_guest_count' => $guestCount,
            'exempt_guest_count' => 0,
            'surcharges_amount' => 0.0,
            'tax_sales_amount' => 0.0,
            'tax_exempt_sales_amount' => 0.0,
            'guest_count' => $order->guest_count,
            'comp_discount' => 0.0,
            'zero_rated_sales_amount' => 0.0,
            'tax_sales_amount_discounted' => 0.0,
            'tax_exempt_sales_amount_discounted' => 0.0,
            'surcharge_vatable' => 0.0,
            'surcharge_vat' => 0.0,

        ];

        return $orderCheck->fill($details)->createOrderCheck();

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
