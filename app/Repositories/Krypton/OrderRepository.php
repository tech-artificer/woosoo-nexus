<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\OrderedMenu;
use App\Models\Krypton\Table;

use App\Models\DeviceOrder;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\Session;

class OrderRepository
{
    /**
     * Fetches all orders and their associated device/table data from different databases.
     *
     * @return Collection
     */

    public static function getAllOrdersWithDeviceData($currentSessions) : Collection
    {
        $session = $currentSessions['session'];
        $terminalSession =  $currentSessions['terminalSession'];

        if(  empty($terminalSession) ) {
            return response()->json([
                'message' => 'No active terminal session found for the current session.'
            ], 400);
        }

        if( !$terminalSession ) return collect([]);

        $orders = Order::select([
                'id','session_id','terminal_session_id',
                'date_time_opened','date_time_closed','revenue_id',
                'terminal_id','is_open','is_transferred',
                'is_voided','guest_count','service_type_id', 'is_available',
                'transaction_no', 'terminal_service_id','reprint_count',
        ])
        ->where(['terminal_session_id' => $terminalSession->id])
        ->latest('created_on')
        ->get();

        $deviceOrders = DeviceOrder::select(['order_id', 'order_number', 'status', 'device_id', 'table_id'])
                            ->with('device', 'table')
                            ->where(['terminal_session_id' => $terminalSession->id])
                            ->get()
                            ->keyBy('order_id');


        $mergedOrders = $orders->transform(function ($order) use ($deviceOrders) {
            
            $data = $deviceOrders->get($order->id) ?? null;

            $order->deviceOrder = $data ?? null;
            $order->device = $data->device ?? null;
            $order->table = $data->table ?? null;
            $order->orderCheck = OrderCheck::where('order_id', $order->id)->first();
            $order->orderedMenus = OrderedMenu::where('order_id', $order->id)->first();

            // unset($order->deviceOrder->device);
            // unset($order->deviceOrder->table);

            return $order;
        });

        return $mergedOrders;
    }

    /**
     * Gets all open orders with their associated tables.
     *
     * This function first gets all open orders, then joins them with the table_orders table to get the associated table ID.
     * It then left joins the table_links table to get any linked tables associated with the order.
     * The result is a collection of orders, each with a table_id and parent_table_id (if applicable).
     *
     * @return Collection
     */
    public static function getOpenOrdersWithTables()
    {
        return Order::select('orders.*', DB::raw("
                IF(table_links.primary_table_id IS NULL, table_orders.table_id, table_links.table_id) AS table_id,
                table_links.primary_table_id AS parent_table_id
            "))
            ->join('table_orders', 'orders.id', '=', 'table_orders.order_id')
            ->leftJoin('table_links', function ($join) {
                $join->on('table_links.order_id', '=', 'table_orders.order_id')
                    ->where('table_links.is_active', 1)
                    ->where(function ($query) {
                        $query->whereNull('table_links.primary_table_id')
                              ->whereColumn('table_links.table_id', 'table_orders.table_id')
                              ->orWhereColumn('table_links.primary_table_id', 'table_orders.table_id');
                    });
            })
            ->join('tables', 'table_orders.table_id', '=', 'tables.id')
            ->where('orders.is_open', 1)
            ->orderBy('orders.id')
            ->orderByRaw('IFNULL(table_links.primary_table_id, 0) ASC')
            ->orderBy('table_links.table_id')
            ->get();
    }

    public static function getOpenOrdersByTable(int $tableId)
    {
        return Order::select('orders.*', DB::raw("
                IF(table_links.primary_table_id IS NULL, table_orders.table_id, table_links.table_id) AS table_id,
                table_links.primary_table_id AS parent_table_id
            "))
            ->join('table_orders', 'orders.id', '=', 'table_orders.order_id')
            ->leftJoin('table_links', function ($join) {
                $join->on('table_links.order_id', '=', 'table_orders.order_id')
                    ->where('table_links.is_active', 1)
                    ->where(function ($query) {
                        $query->whereNull('table_links.primary_table_id')
                              ->whereColumn('table_links.table_id', 'table_orders.table_id')
                              ->orWhereColumn('table_links.primary_table_id', 'table_orders.table_id');
                    });
            })
            ->where('orders.is_open', 1)
            ->whereRaw('IFNULL(table_links.table_id, table_orders.table_id) = ?', [$tableId])
            ->orderBy('orders.id')
            ->orderByRaw('IFNULL(table_links.primary_table_id, 0) ASC')
            ->orderBy('table_links.table_id')
            ->get();
    }

    public function getOpenOrdersForSession($sessionId) {
        try {
            return Order::fromQuery("CALL get_open_orders_for_session(?)", [$sessionId]);
        } catch (\Throwable $th) {
            \Log::error('Procedure call failed: ' . $th->getMessage());
            return false;
        }
    }
    
}