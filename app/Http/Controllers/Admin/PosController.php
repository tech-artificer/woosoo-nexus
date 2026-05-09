<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Order\CreateOrder;
use App\Actions\Order\CreateOrderCheck;
use App\Actions\Order\CreateTableOrder;
use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    /**
     * POS page data source is Krypton only (DB connection: pos).
     */
    public function index(): Response
    {
        $currentSession = DB::connection('pos')
            ->table('sessions')
            ->orderByDesc('id')
            ->first();

        $terminals = DB::connection('pos')
            ->table('terminals as t')
            ->leftJoin('devices as d', 'd.id', '=', 't.id')
            ->leftJoin('terminal_sessions as ts', function ($join): void {
                $join->on('ts.terminal_id', '=', 't.id')
                    ->whereRaw('ts.id = (SELECT MAX(ts2.id) FROM terminal_sessions ts2 WHERE ts2.terminal_id = t.id)');
            })
            ->leftJoin('sessions as s', 's.id', '=', 'ts.session_id')
            ->leftJoin('orders as o', function ($join): void {
                $join->on('o.terminal_id', '=', 't.id')
                    ->where('o.is_open', 1)
                    ->where('o.is_voided', 0);
            })
            ->whereRaw("UPPER(COALESCE(d.type, t.type, '')) <> 'PRINTER'")
            ->groupBy(
                't.id',
                't.type',
                'd.name',
                'd.ip_address',
                'd.port',
                'd.is_active',
                'ts.id',
                'ts.session_id',
                'ts.date_time_opened',
                'ts.date_time_closed',
                's.date_time_closed'
            )
            ->orderByDesc('d.is_active')
            ->orderBy('d.name')
            ->select([
                't.id',
                DB::raw("COALESCE(d.name, CONCAT('Terminal ', t.id)) as name"),
                DB::raw("COALESCE(d.type, t.type, 'Terminal') as type"),
                'd.ip_address',
                'd.port',
                'd.is_active',
                'ts.id as terminal_session_id',
                'ts.session_id',
                'ts.date_time_opened as terminal_session_opened_at',
                'ts.date_time_closed as terminal_session_closed_at',
                's.date_time_closed as session_closed_at',
                DB::raw('COUNT(o.id) as open_orders_count'),
            ])
            ->get();

        $initialTerminalId = $terminals->first()->id ?? null;
        $tables = $initialTerminalId ? $this->fetchTablesByTerminal((string) $initialTerminalId) : collect();

        return Inertia::render('POS/Index', [
            'title' => 'POS',
            'description' => 'Krypton POS terminal and table operations',
            'terminals' => $terminals,
            'tables' => $tables,
            'currentSession' => $currentSession,
        ]);
    }

    /**
     * Return all registered Krypton tables with occupancy indicator for the selected terminal.
     */
    public function terminalTables(string $terminalId): JsonResponse
    {
        $terminal = DB::connection('pos')
            ->table('terminals as t')
            ->leftJoin('devices as d', 'd.id', '=', 't.id')
            ->where('t.id', $terminalId)
            ->whereRaw("UPPER(COALESCE(d.type, t.type, '')) <> 'PRINTER'")
            ->select(['t.id', DB::raw("COALESCE(d.name, CONCAT('Terminal ', t.id)) as name")])
            ->first();

        if (! $terminal) {
            return response()->json([
                'success' => false,
                'message' => 'Terminal not found in Krypton.',
            ], 404);
        }

        $tables = $this->fetchTablesByTerminal($terminalId);

        return response()->json([
            'success' => true,
            'terminal' => $terminal,
            'tables' => $tables,
        ]);
    }

    /**
     * Return table-specific orders for selected terminal.
     */
    public function tableOrders(string $terminalId, string $tableId): JsonResponse
    {
        $table = DB::connection('pos')
            ->table('tables')
            ->where('id', $tableId)
            ->first();

        if (! $table) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found in Krypton.',
            ], 404);
        }

        $orders = DB::connection('pos')
            ->table('orders as o')
            // Subquery restricts to the single latest check per order, preventing
            // duplicate rows when an order has multiple order_check records.
            ->leftJoin(
                DB::raw('(SELECT order_id, MAX(id) AS max_id FROM order_checks GROUP BY order_id) AS oc_latest'),
                'oc_latest.order_id', '=', 'o.id'
            )
            ->leftJoin('order_checks as oc', 'oc.id', '=', 'oc_latest.max_id')
            ->leftJoin('table_orders as tor', 'tor.order_id', '=', 'o.id')
            ->leftJoin('tables as t', 't.id', '=', 'tor.table_id')
            ->where('o.terminal_id', $terminalId)
            ->where('tor.table_id', $tableId)
            ->where('o.is_open', 1)
            ->where('o.is_voided', 0)
            ->groupBy(
                'o.id',
                'o.reference',
                'o.date_time_opened',
                'o.date_time_closed',
                'o.guest_count',
                'o.terminal_id',
                'o.is_open',
                'o.is_voided',
                'oc.total_amount',
                'oc.paid_amount',
                'oc.is_settled',
                'oc.resetable_transaction_number'
            )
            ->orderByDesc('o.date_time_opened')
            ->select([
                'o.id',
                'o.reference',
                'o.date_time_opened',
                'o.date_time_closed',
                'o.guest_count',
                'o.terminal_id',
                'o.is_open',
                'o.is_voided',
                DB::raw('COALESCE(oc.total_amount, 0) as total_amount'),
                DB::raw('COALESCE(oc.paid_amount, 0) as paid_amount'),
                DB::raw('COALESCE(oc.is_settled, 0) as is_settled'),
                'oc.resetable_transaction_number',
                DB::raw("GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') as table_names"),
            ])
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'terminal_id' => $terminalId,
            'table' => $table,
            'orders' => $orders,
        ]);
    }

    public function addOrder(string $terminalId, string $tableId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
            'reference' => ['nullable', 'string', 'max:50'],
        ]);

        $table = DB::connection('pos')->table('tables')->where('id', $tableId)->first();
        if (! $table) {
            return response()->json(['success' => false, 'message' => 'Table not found.'], 404);
        }

        $ctx = $this->resolveContextForTerminal($terminalId);

        $result = DB::connection('pos')->transaction(function () use ($ctx, $terminalId, $tableId, $validated) {
            $order = CreateOrder::run([
                'session_id' => $ctx['session_id'],
                'terminal_session_id' => $ctx['terminal_session_id'],
                'revenue_id' => $ctx['revenue_id'],
                'terminal_id' => (int) $terminalId,
                'guest_count' => (int) $validated['guest_count'],
                'service_type_id' => $ctx['service_type_id'],
                'start_employee_log_id' => $ctx['employee_log_id'],
                'current_employee_log_id' => $ctx['employee_log_id'],
                'close_employee_log_id' => null,
                'server_employee_log_id' => $ctx['employee_log_id'],
                'reference' => $validated['reference'] ?? null,
                'cashier_employee_id' => $ctx['employee_id'],
                'terminal_service_id' => $ctx['terminal_service_id'],
                'is_online_order' => true,
                'customer_id' => null,
                'cash_tray_session_id' => $ctx['cash_tray_session_id'],
            ]);

            CreateTableOrder::run([
                'order_id' => $order->id,
                'table_id' => (int) $tableId,
            ]);

            $check = CreateOrderCheck::run([
                'order_id' => $order->id,
                'guest_count' => (int) $validated['guest_count'],
                'total_amount' => 0,
                'subtotal' => 0,
                'tax' => 0,
                'discount_amount' => 0,
                'taxable' => 0,
            ]);

            return [
                'order_id' => $order->id,
                'order_check_id' => $check->id ?? null,
            ];
        });

        AuditLogService::adminAction($request, 'pos.order_added', (int) $request->user()->id, [
            'terminal_id' => $terminalId,
            'table_id'    => $tableId,
            'order_id'    => $result['order_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order created in Krypton successfully.',
            'result' => $result,
        ]);
    }

    public function editOrder(string $orderId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
            'reference' => ['nullable', 'string', 'max:50'],
        ]);

        $updated = DB::connection('pos')
            ->table('orders')
            ->where('id', $orderId)
            ->where('is_voided', 0)
            ->update([
                'guest_count' => $validated['guest_count'],
                'reference' => $validated['reference'] ?? '',
            ]);

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or already voided.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated in Krypton successfully.',
        ]);
    }

    public function voidOrder(string $orderId, Request $request): JsonResponse
    {
        $now = now()->toDateTimeString();

        $affected = 0;

        DB::connection('pos')->transaction(function () use ($orderId, $now, &$affected): void {
            $affected = DB::connection('pos')
                ->table('orders')
                ->where('id', $orderId)
                ->update([
                    'is_voided' => 1,
                    'is_open' => 0,
                    'date_time_closed' => $now,
                ]);

            if ($affected) {
                DB::connection('pos')
                    ->table('order_checks')
                    ->where('order_id', $orderId)
                    ->update([
                        'is_voided' => 1,
                        'date_time_voided' => $now,
                    ]);

                $this->syncTablesForOrderClosure($orderId);
            }
        });

        if (! $affected) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        AuditLogService::adminAction($request, 'pos.order_voided', (int) $request->user()->id, [
            'order_id' => $orderId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order voided in Krypton.',
        ]);
    }

    public function payOrder(string $orderId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_type_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tip' => ['nullable', 'numeric', 'min:0'],
            'card_company' => ['nullable', 'string', 'max:50'],
            'card_number' => ['nullable', 'string', 'max:30'],
            'unique_code' => ['nullable', 'string', 'max:50'],
            'auth_code' => ['nullable', 'string', 'max:50'],
            'expiration_date' => ['nullable', 'string', 'max:16'],
        ]);

        $order = DB::connection('pos')
            ->table('orders')
            ->where('id', $orderId)
            ->where('is_open', 1)
            ->where('is_voided', 0)
            ->first();
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found, already closed, or voided.'], 404);
        }

        $orderCheck = DB::connection('pos')
            ->table('order_checks')
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->first();

        if (! $orderCheck) {
            return response()->json(['success' => false, 'message' => 'Order check not found.'], 404);
        }

        $ctx = $this->resolveContextForTerminal((string) $order->terminal_id);
        $amount = (float) $validated['amount'];
        $currentPaid = (float) ($orderCheck->paid_amount ?? 0);
        $totalAmount = (float) ($orderCheck->total_amount ?? 0);
        $remaining = max($totalAmount - $currentPaid, 0.0);
        $change = max($amount - $remaining, 0.0);
        $newPaid = $currentPaid + $amount;
        $isSettled = $newPaid >= $totalAmount ? 1 : 0;
        $now = now()->toDateTimeString();

        $paymentRows = null;

        // Wrap stored procedure + follow-up ORM writes in a single transaction so a
        // mid-sequence failure cannot leave partial payment state in the POS database.
        // Note: if create_check_payment issues implicit DDL/DML commits internally, those
        // cannot be rolled back by the outer transaction — audit that stored proc separately.
        DB::connection('pos')->transaction(function () use (
            $orderCheck, $validated, $amount, $change, $newPaid,
            $isSettled, $now, $orderId, $ctx, &$paymentRows
        ): void {
            $paymentRows = DB::connection('pos')->select(
                'CALL create_check_payment(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $orderCheck->id,
                    (int) $validated['payment_type_id'],
                    null,
                    0,
                    null,
                    null,
                    $amount,
                    $change,
                    $validated['card_company'] ?? null,
                    $validated['card_number'] ?? null,
                    $validated['unique_code'] ?? null,
                    $validated['auth_code'] ?? null,
                    (float) ($validated['tip'] ?? 0),
                    false,
                    false,
                    $now,
                    (int) $ctx['employee_log_id'],
                    $validated['expiration_date'] ?? null,
                ]
            );

            DB::connection('pos')
                ->table('order_checks')
                ->where('id', $orderCheck->id)
                ->update([
                    'paid_amount' => $newPaid,
                    'change' => $change,
                    'is_settled' => $isSettled,
                    'date_time_closed' => $isSettled ? $now : null,
                ]);

            if ($isSettled === 1) {
                DB::connection('pos')
                    ->table('orders')
                    ->where('id', $orderId)
                    ->update([
                        'is_open' => 0,
                        'date_time_closed' => $now,
                    ]);

                $this->syncTablesForOrderClosure($orderId);
            }
        });

        AuditLogService::adminAction($request, 'pos.order_paid', (int) $request->user()->id, [
            'order_id'   => $orderId,
            'amount'     => $validated['amount'],
            'is_settled' => (bool) $isSettled,
        ]);

        return response()->json([
            'success' => true,
            'message' => $isSettled ? 'Order paid and closed.' : 'Payment recorded.',
            'payment' => $paymentRows[0] ?? null,
            'is_settled' => $isSettled,
        ]);
    }

    private function fetchTablesByTerminal(string $terminalId)
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
    private function syncTablesForOrderClosure(string $orderId): void
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

    private function resolveContextForTerminal(string $terminalId): array
    {
        $session = DB::connection('pos')
            ->table('sessions')
            ->whereNull('date_time_closed')
            ->orderByDesc('id')
            ->first();

        if (! $session) {
            $session = DB::connection('pos')->table('sessions')->orderByDesc('id')->first();
        }

        $terminalSession = DB::connection('pos')
            ->table('terminal_sessions')
            ->where('terminal_id', $terminalId)
            ->whereNull('date_time_closed')
            ->orderByDesc('id')
            ->first();

        if (! $terminalSession) {
            $terminalSession = DB::connection('pos')
                ->table('terminal_sessions')
                ->where('terminal_id', $terminalId)
                ->orderByDesc('id')
                ->first();
        }

        $employeeLog = DB::connection('pos')
            ->table('employee_logs')
            ->whereNull('date_time_out')
            ->orderByDesc('id')
            ->first();

        if (! $employeeLog) {
            $employeeLog = DB::connection('pos')->table('employee_logs')->orderByDesc('id')->first();
        }

        $terminalService = DB::connection('pos')
            ->table('terminal_services')
            ->where('terminal_id', $terminalId)
            ->first();

        return [
            'session_id' => (int) ($session->id ?? 0),
            'terminal_session_id' => $terminalSession ? (int) $terminalSession->id : null,
            'employee_log_id' => (int) ($employeeLog->id ?? 1),
            'employee_id' => (int) ($employeeLog->employee_id ?? 1),
            'revenue_id' => (int) ($terminalService->revenue_id ?? 1),
            'service_type_id' => (int) ($terminalService->service_type_id ?? 1),
            'terminal_service_id' => (int) ($terminalService->id ?? 1),
            'cash_tray_session_id' => null,
        ];
    }
}
