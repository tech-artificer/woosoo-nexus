<?php

namespace App\Services\Krypton;

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
    Table,
};
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Actions\Order\{
    CreateOrder,
    CreateOrderCheck,
    CreateTableOrder,
    CreateOrderedMenu
};

use App\Enums\OrderStatus;
use App\Services\Krypton\KryptonContextService;
use App\Services\BroadcastService;
use App\Events\Order\OrderVoided;
use App\Events\PrintOrder;
use App\Exceptions\SessionNotFoundException;

use Illuminate\Support\Facades\Log;

class OrderService
{
    public $attributes = [];
    /**
     * Process an order for a given device with specified attributes.
     *
     * @param Device $device
     * @param array $attributes
     * @return Order|bool
     */
    public function processOrder(Device $device, array $attributes)
    {
        // Fetch default values and merge them with provided attributes
        $defaults = $this->getDefaultAttributes();
        $attributes = array_merge($defaults, $attributes, ['device_id' => $device->id, 'table_id' => $device->table_id]);

        // Always derive monetary totals from item lines server-side.
        // This prevents client-side floating-point drift from propagating to transactions.
        $clientTotals = [
            'subtotal' => $attributes['subtotal'] ?? null,
            'tax' => $attributes['tax'] ?? null,
            'total_amount' => $attributes['total_amount'] ?? null,
        ];
        $calculatedTotals = $this->calculateTotalsFromItems($attributes['items'] ?? []);
        $attributes['subtotal'] = $calculatedTotals['subtotal'];
        $attributes['tax'] = $calculatedTotals['tax'];
        $attributes['total_amount'] = $calculatedTotals['total'];
        $attributes['discount_amount'] = $this->money($attributes['discount'] ?? 0);

        if (
            $clientTotals['subtotal'] !== null ||
            $clientTotals['tax'] !== null ||
            $clientTotals['total_amount'] !== null
        ) {
            $clientNormalized = [
                'subtotal' => $this->money($clientTotals['subtotal'] ?? 0),
                'tax' => $this->money($clientTotals['tax'] ?? 0),
                'total_amount' => $this->money($clientTotals['total_amount'] ?? 0),
            ];

            if (
                $clientNormalized['subtotal'] !== $attributes['subtotal'] ||
                $clientNormalized['tax'] !== $attributes['tax'] ||
                $clientNormalized['total_amount'] !== $attributes['total_amount']
            ) {
                Log::warning('OrderService: Client totals drift detected; server totals enforced', [
                    'client' => $clientNormalized,
                    'server' => [
                        'subtotal' => $attributes['subtotal'],
                        'tax' => $attributes['tax'],
                        'total_amount' => $attributes['total_amount'],
                    ],
                    'items_count' => count($attributes['items'] ?? []),
                ]);
            }
        }

        // Keep `items` for downstream CreateOrderedMenu action.
        // DeviceOrder persistence already uses an explicit create() payload,
        // so no legacy JSON columns are mass-assigned from here.

        $this->attributes = $attributes;

        $createdOrderId = null;

        try {
            return DB::transaction(function () use ($device, &$createdOrderId) {
                // Create a new order using the provided attributes
                $order = CreateOrder::run($this->attributes);

                if (! $order) {
                    return null;
                }

                $createdOrderId = $order->id ?? null;

                $this->updateAttributes([
                    'order_id' => $order->id,
                ]);

                CreateTableOrder::run($this->attributes);

                Table::where('id', $this->attributes['table_id'])->update([
                    'is_available' => true,
                    'is_locked' => true,
                ]);

                $orderCheck = CreateOrderCheck::run($this->attributes);

                $this->updateAttributes([
                    'order_check_id' => $orderCheck->id,
                ]);

                // Create a new device order FIRST so we have device_order_id for items
                // session_id must come from POS; terminal_session_id can fall back to null if not available
                $deviceOrder = $device->orders()->create([
                    'order_id' => $order->id,
                    'table_id' => $device->table_id,
                    'terminal_session_id' => $order->terminal_session_id ?? $this->attributes['terminal_session_id'],
                    'status' => OrderStatus::CONFIRMED,
                    'guest_count' => $order->guest_count,
                    'session_id' => $order->session_id ?? $this->attributes['session_id'],
                    'total' => $this->attributes['total_amount'],
                    'subtotal' => $this->attributes['subtotal'],
                    'tax' => $this->attributes['tax'],
                    'discount' => $orderCheck->discount_amount,
                ]);

                $this->updateAttributes([
                    'device_order_id' => $deviceOrder->id,
                ]);

                CreateOrderedMenu::run($this->attributes);

                DB::afterCommit(function () use ($deviceOrder) {
                    try {
                        app(\App\Services\PrintEventService::class)->createForOrder($deviceOrder, 'INITIAL');
                        $deviceOrder->refresh();
                        \App\Events\PrintOrder::dispatch($deviceOrder);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });

                return $deviceOrder;
            });
        } catch (\Throwable $e) {
            if ($createdOrderId !== null) {
                DB::connection('pos')->table('ordered_menus')->where('order_id', $createdOrderId)->delete();
                DB::connection('pos')->table('order_checks')->where('order_id', $createdOrderId)->delete();
                DB::connection('pos')->table('table_orders')->where('order_id', $createdOrderId)->delete();
                DB::connection('pos')->table('orders')->where('id', $createdOrderId)->delete();
            }

            Log::error('Order creation failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function updateAttributes($array = []) {
        foreach ($array as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Fetch default values needed for order processing.
     *
     * @return array
     */
    protected function getDefaultAttributes(): array
    {   
        $contextService = app(KryptonContextService::class);
        $defaults = $contextService->getData();

        // session_id is MANDATORY from Krypton - no fallback to null or defaults allowed
        // terminal_session_id, terminal_id, revenue_id have sensible fallbacks if missing from POS context
        $normalized = [
            'price_level_id' => $defaults['price_level_id'] ?? null,
            'tax_set_id' => $defaults['tax_set_id'] ?? null,
            'service_type_id' => $defaults['service_type_id'] ?? 1,
            'revenue_id' => $defaults['revenue_id'] ?? 1,
            'terminal_id' => $defaults['terminal_id'] ?? 1,
            'session_id' => $defaults['session_id'],  // REQUIRED - no fallback, exception thrown by KryptonContextService if missing
            'terminal_session_id' => $defaults['terminal_session_id'] ?? null,
            'employee_log_id' => $defaults['employee_log_id'] ?? null,
            'cash_tray_session_id' => $defaults['cash_tray_session_id'] ?? null,
            'terminal_service_id' => $defaults['terminal_service_id'] ?? null,
            'employee_id' => $defaults['employee_id'] ?? null,
            'cashier_employee_id' => $defaults['cashier_employee_id'] ?? null,
            'server_employee_log_id' => $defaults['server_employee_log_id'] ?? ($defaults['employee_log_id'] ?? null),
        ];

        $params = [
            'start_employee_log_id' => $normalized['employee_log_id'] ?? null,
            'current_employee_log_id' => $normalized['employee_log_id'] ?? null,
            'close_employee_log_id' => $normalized['employee_log_id'] ?? null,
            'server_employee_log_id' => $normalized['server_employee_log_id'] ?? null,
            'is_online_order' => false,
            'reference' => '',
        ];

        return array_merge($normalized, $params);
    }

    protected function cancelOrder(Device $device) {
        
    }

    public function voidOrder(DeviceOrder $deviceOrder) {
        app(BroadcastService::class)->dispatchBroadcastJob(new OrderVoided($deviceOrder));
    }

    protected function rollBackOrder(Device $device) {
        
    }

    /**
     * Calculate totals from items array
     * This matches the calculation logic in CreateOrderedMenu
     *
     * @param array $items
     * @return array
     */
    protected function calculateTotalsFromItems(array $items): array
    {
        $subtotal = 0;
        $tax = 0;
        $taxRate = config('api.krypton.tax_rate', 0.10);

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $price = $this->money($item['price'] ?? 0);
            
            // Calculate item total (price * quantity)
            $itemTotal = $this->money($price * $quantity);
            
            // Calculate tax for this item (same as CreateOrderedMenu: totalItemPrice * taxRate)
            $itemTax = $this->money($itemTotal * $taxRate);
            
            $subtotal = $this->money($subtotal + $itemTotal);
            $tax = $this->money($tax + $itemTax);
        }

        $total = $this->money($subtotal + $tax);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }
}

// applied_taxes
// applied_discounts
// applied_menu_categories
