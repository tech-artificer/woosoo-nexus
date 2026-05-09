<?php

namespace App\Services\Pos;

use Illuminate\Support\Facades\DB;

class PosTableService
{
    public function getTablesForTerminal(string $terminalId)
    {
        return DB::connection('pos')
            ->table('tables as t')
            ->leftJoin('table_orders as tor', 'tor.table_id', '=', 't.id')
            ->leftJoin('orders as o', function ($join) use ($terminalId): void {
                $join->on('o.id', '=', 'tor.order_id')
                    ->where('o.terminal_id', $terminalId)
                    ->where('o.is_open', 1)
                    ->where('o.is_voided', 0);
            })
            ->groupBy('t.id', 't.name', 't.status', 't.is_available', 't.is_locked', 't.table_group_id', 't.order_created_in')
            ->orderBy('t.name')
            ->select([
                't.id',
                't.name',
                't.status',
                't.is_available',
                't.is_locked',
                't.table_group_id',
                't.order_created_in',
                DB::raw('COUNT(o.id) as open_orders_count'),
                DB::raw('CASE WHEN COUNT(o.id) > 0 OR t.is_locked = 1 THEN 1 ELSE 0 END as is_occupied'),
            ])
            ->get();
    }

    /**
     * Recalculate table lock/availability for all tables linked to a closed/voided order.
     *
     * Table remains locked when it still has at least one open + non-void order.
     */
    public function syncTablesForOrderClosure(string $orderId): void
    {
        $tableIds = DB::connection('pos')
            ->table('table_orders')
            ->where('order_id', $orderId)
            ->pluck('table_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($tableIds as $tableId) {
            $remainingOpenOrders = DB::connection('pos')
                ->table('table_orders as tor')
                ->join('orders as o', 'o.id', '=', 'tor.order_id')
                ->where('tor.table_id', $tableId)
                ->where('o.is_open', 1)
                ->where('o.is_voided', 0)
                ->count();

            DB::connection('pos')
                ->table('tables')
                ->where('id', $tableId)
                ->update([
                    'is_locked' => $remainingOpenOrders > 0 ? 1 : 0,
                    // is_available intentionally excluded — manual offline/maintenance states must not be overwritten on order closure
                ]);
        }
    }
}
