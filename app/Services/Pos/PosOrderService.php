<?php

namespace App\Services\Pos;

use App\Actions\Order\CreateOrder;
use App\Actions\Order\CreateOrderCheck;
use App\Actions\Order\CreateTableOrder;
use Illuminate\Support\Facades\DB;

class PosOrderService
{
    public function __construct(
        private readonly TerminalContextResolver $contextResolver,
        private readonly PosTableService $tableService,
    ) {}

    /**
     * Create a new POS order for a table, including table_order and order_check rows.
     * Runs inside a single POS DB transaction.
     *
     * @return array{order_id: int, order_check_id: int|null}
     */
    public function createOrder(string $terminalId, string $tableId, int $guestCount, ?string $reference): array
    {
        $ctx = $this->contextResolver->resolve($terminalId);

        return DB::connection('pos')->transaction(function () use ($ctx, $terminalId, $tableId, $guestCount, $reference) {
            $order = CreateOrder::run([
                'session_id'              => $ctx['session_id'],
                'terminal_session_id'     => $ctx['terminal_session_id'],
                'revenue_id'              => $ctx['revenue_id'],
                'terminal_id'             => (int) $terminalId,
                'guest_count'             => $guestCount,
                'service_type_id'         => $ctx['service_type_id'],
                'start_employee_log_id'   => $ctx['employee_log_id'],
                'current_employee_log_id' => $ctx['employee_log_id'],
                'close_employee_log_id'   => null,
                'server_employee_log_id'  => $ctx['employee_log_id'],
                'reference'               => $reference,
                'cashier_employee_id'     => $ctx['employee_id'],
                'terminal_service_id'     => $ctx['terminal_service_id'],
                'is_online_order'         => true,
                'customer_id'             => null,
                'cash_tray_session_id'    => $ctx['cash_tray_session_id'],
            ]);

            CreateTableOrder::run([
                'order_id' => $order->id,
                'table_id' => (int) $tableId,
            ]);

            $check = CreateOrderCheck::run([
                'order_id'        => $order->id,
                'guest_count'     => $guestCount,
                'total_amount'    => 0,
                'subtotal'        => 0,
                'tax'             => 0,
                'discount_amount' => 0,
                'taxable'         => 0,
            ]);

            return [
                'order_id'       => $order->id,
                'order_check_id' => $check->id ?? null,
            ];
        });
    }

    /**
     * Update guest count and reference on an open, non-voided order.
     * Returns true on success, false when the order was not found or already voided.
     */
    public function updateOrder(string $orderId, int $guestCount, ?string $reference): bool
    {
        $updated = DB::connection('pos')
            ->table('orders')
            ->where('id', $orderId)
            ->where('is_voided', 0)
            ->update([
                'guest_count' => $guestCount,
                'reference'   => $reference ?? '',
            ]);

        return (bool) $updated;
    }

    /**
     * Void an order and its checks, then sync table lock states.
     * Returns true on success, false when the order was not found.
     */
    public function voidOrder(string $orderId): bool
    {
        $now = now()->toDateTimeString();
        $affected = 0;

        DB::connection('pos')->transaction(function () use ($orderId, $now, &$affected): void {
            $affected = DB::connection('pos')
                ->table('orders')
                ->where('id', $orderId)
                ->update([
                    'is_voided'         => 1,
                    'is_open'           => 0,
                    'date_time_closed'  => $now,
                ]);

            if ($affected) {
                DB::connection('pos')
                    ->table('order_checks')
                    ->where('order_id', $orderId)
                    ->update([
                        'is_voided'         => 1,
                        'date_time_voided'  => $now,
                    ]);

                $this->tableService->syncTablesForOrderClosure($orderId);
            }
        });

        return (bool) $affected;
    }

    /**
     * Record a payment against an order check.
     *
     * Wraps create_check_payment stored proc + follow-up ORM writes in a single
     * POS transaction. Returns the payment row and settlement flag.
     *
     * Note: if create_check_payment issues implicit DDL/DML commits internally those
     * cannot be rolled back by the outer transaction — audit that stored proc separately.
     *
     * @return array{payment: object|null, is_settled: int}
     */
    public function payOrder(string $orderId, array $validated): array
    {
        $order = DB::connection('pos')
            ->table('orders')
            ->where('id', $orderId)
            ->where('is_open', 1)
            ->where('is_voided', 0)
            ->first();

        if (! $order) {
            return ['payment' => null, 'is_settled' => 0, 'not_found' => true];
        }

        $orderCheck = DB::connection('pos')
            ->table('order_checks')
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->first();

        if (! $orderCheck) {
            return ['payment' => null, 'is_settled' => 0, 'check_not_found' => true];
        }

        $ctx          = $this->contextResolver->resolve((string) $order->terminal_id);
        $amount       = (float) $validated['amount'];
        $currentPaid  = (float) ($orderCheck->paid_amount ?? 0);
        $totalAmount  = (float) ($orderCheck->total_amount ?? 0);
        $remaining    = max($totalAmount - $currentPaid, 0.0);
        $change       = max($amount - $remaining, 0.0);
        $newPaid      = $currentPaid + $amount;
        $isSettled    = $newPaid >= $totalAmount ? 1 : 0;
        $now          = now()->toDateTimeString();
        $paymentRows  = null;

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
                    'paid_amount'       => $newPaid,
                    'change'            => $change,
                    'is_settled'        => $isSettled,
                    'date_time_closed'  => $isSettled ? $now : null,
                ]);

            if ($isSettled === 1) {
                DB::connection('pos')
                    ->table('orders')
                    ->where('id', $orderId)
                    ->update([
                        'is_open'          => 0,
                        'date_time_closed' => $now,
                    ]);

                $this->tableService->syncTablesForOrderClosure($orderId);
            }
        });

        return [
            'payment'    => $paymentRows[0] ?? null,
            'is_settled' => $isSettled,
        ];
    }
}
