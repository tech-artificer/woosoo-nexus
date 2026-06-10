<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use App\Services\AuditLogService;
use App\Services\Pos\PosOrderService;
use App\Services\Pos\PosTableService;
use App\Services\PosConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(
        private readonly PosOrderService $orderService,
        private readonly PosTableService $tableService,
        private readonly PosConnectionService $posConnection,
    ) {}

    /**
     * POS page data source is Krypton only (DB connection: pos).
     */
    public function index(): Response
    {
        $posStatus = $this->posConnection->posStatus();
        if (! $posStatus['connected']) {
            return $this->disconnectedPosPage($posStatus);
        }

        try {
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
            $tables = $initialTerminalId ? $this->tableService->getTablesForTerminal((string) $initialTerminalId) : collect();

            return Inertia::render('POS/Index', [
                'title' => 'POS',
                'description' => 'Krypton POS terminal and table operations',
                'terminals' => $terminals,
                'tables' => $tables,
                'currentSession' => $currentSession,
                'posConnected' => true,
                'posStatus' => 'connected',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[PosController] POS connection failure in index: '.$e->getMessage());

            return $this->disconnectedPosPage($this->posConnection->failureFromThrowable($e));
        }
    }

    /**
     * @param  array{connected: bool, status: string, message: string}  $posStatus
     */
    private function disconnectedPosPage(array $posStatus): Response
    {
        return Inertia::render('POS/Index', [
            'title' => 'POS',
            'description' => 'Krypton POS terminal and table operations',
            'terminals' => [],
            'tables' => collect(),
            'currentSession' => null,
            'posConnected' => false,
            'posStatus' => $posStatus['status'],
            'posMessage' => $posStatus['message'],
        ]);
    }

    private function posConnectionError(string $context, ?\Throwable $e = null): JsonResponse
    {
        $posStatus = $e !== null
            ? $this->posConnection->failureFromThrowable($e)
            : $this->posConnection->posStatus();

        Log::warning("[PosController] POS connection failure in {$context}: ".($e?->getMessage() ?? $posStatus['message']));

        return response()->json([
            'success' => false,
            'status' => $posStatus['status'],
            'message' => $posStatus['message'],
        ], 503);
    }

    /**
     * Return all registered Krypton tables with occupancy indicator for the selected terminal.
     */
    public function terminalTables(string $terminalId): JsonResponse
    {
        try {
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

            $tables = $this->tableService->getTablesForTerminal($terminalId);

            return response()->json([
                'success' => true,
                'terminal' => $terminal,
                'tables' => $tables,
            ]);
        } catch (\Throwable $e) {
            return $this->posConnectionError(__FUNCTION__, $e);
        }
    }

    /**
     * Return table-specific orders for selected terminal.
     */
    public function tableOrders(string $terminalId, string $tableId): JsonResponse
    {
        try {
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
        } catch (\Throwable $e) {
            return $this->posConnectionError(__FUNCTION__, $e);
        }
    }

    public function addOrder(string $terminalId, string $tableId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
            'reference' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $table = DB::connection('pos')->table('tables')->where('id', $tableId)->first();
            if (! $table) {
                return response()->json(['success' => false, 'message' => 'Table not found.'], 404);
            }

            $result = $this->orderService->createOrder(
                $terminalId,
                $tableId,
                (int) $validated['guest_count'],
                $validated['reference'] ?? null,
            );

            AuditLogService::adminAction($request, 'pos.order_added', (int) $request->user()->id, [
                'terminal_id' => $terminalId,
                'table_id' => $tableId,
                'order_id' => $result['order_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order created in Krypton successfully.',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return $this->posConnectionError(__FUNCTION__, $e);
        }
    }

    public function editOrder(string $orderId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
            'reference' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $updated = $this->orderService->updateOrder(
                $orderId,
                (int) $validated['guest_count'],
                $validated['reference'] ?? null,
            );

            if (! $updated) {
                return response()->json(['success' => false, 'message' => 'Order not found or already voided.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Order updated in Krypton successfully.']);
        } catch (\Throwable $e) {
            return $this->posConnectionError(__FUNCTION__, $e);
        }
    }

    public function voidOrder(string $orderId, Request $request): JsonResponse
    {
        try {
            $voided = $this->orderService->voidOrder($orderId);

            if (! $voided) {
                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }

            AuditLogService::adminAction($request, 'pos.order_voided', (int) $request->user()->id, [
                'order_id' => $orderId,
            ]);

            $deviceOrder = DeviceOrder::where('order_id', $orderId)
                ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::VOIDED->value])
                ->first();
            if ($deviceOrder) {
                $deviceOrder->status = OrderStatus::VOIDED;
                $deviceOrder->save();
            }

            return response()->json(['success' => true, 'message' => 'Order voided in Krypton.']);
        } catch (\Throwable $e) {
            return $this->posConnectionError(__FUNCTION__, $e);
        }
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

        try {
            $result = $this->orderService->payOrder($orderId, $validated);

            if (isset($result['error'])) {
                return response()->json(['success' => false, 'message' => 'Payment processing failed: '.$result['error']], 500);
            }
            if (isset($result['not_found'])) {
                return response()->json(['success' => false, 'message' => 'Order not found, already closed, or voided.'], 404);
            }
            if (isset($result['check_not_found'])) {
                return response()->json(['success' => false, 'message' => 'Order check not found.'], 404);
            }

            AuditLogService::adminAction($request, 'pos.order_paid', (int) $request->user()->id, [
                'order_id' => $orderId,
                'amount' => $validated['amount'],
                'is_settled' => (bool) $result['is_settled'],
            ]);

            if ($result['is_settled']) {
                $deviceOrder = DeviceOrder::where('order_id', $orderId)
                    ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::VOIDED->value])
                    ->first();
                if ($deviceOrder) {
                    $deviceOrder->status = OrderStatus::COMPLETED;
                    $deviceOrder->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => $result['is_settled'] ? 'Order paid and closed.' : 'Payment recorded.',
                'payment' => $result['payment'],
                'is_settled' => $result['is_settled'],
            ]);
        } catch (\Throwable $e) {
            return $this->posConnectionError(__FUNCTION__, $e);
        }
    }
}
