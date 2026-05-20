<?php

namespace App\Services\Krypton;

use App\Actions\Order\CreateOrder;
use App\Actions\Order\CreateOrderCheck;
use App\Actions\Order\CreateOrderedMenu;
use App\Actions\Order\CreateTableOrder;
use App\Enums\OrderStatus;
use App\Events\Order\OrderVoided;
use App\Events\PrintOrder;
use App\Exceptions\SessionNotFoundException;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Krypton\Menu as KryptonMenu;
use App\Models\Krypton\Order;
use App\Models\Krypton\Table;
use App\Models\Krypton\Tax;
use App\Services\BroadcastService;
use App\Services\PrintEventService;
use App\Services\PrintTicketService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    private const TEST_KRYPTON_SESSION_CACHE_KEY = 'testing.krypton.session_id';

    public $attributes = [];

    /**
     * Process an order for a given device with specified attributes.
     *
     * @return Order|bool
     */
    public function processOrder(Device $device, array $attributes, ?string $clientSubmissionId = null)
    {
        // Dual-DB contract reference:
        // See docs/DATABASE_SYNC.md for ownership boundaries, failure modes,
        // and recovery steps for POS-first write flow.
        // Fetch default values and merge them with provided attributes
        $defaults = $this->getDefaultAttributes();
        $attributes = array_merge($defaults, $attributes, ['device_id' => $device->id, 'table_id' => $device->table_id]);
        $attributes['reference'] = $this->buildOrderReference($device, $attributes['reference'] ?? null);

        // Enforce ID namespace contract before touching POS:
        // - Local main DB IDs (e.g., device_orders.id) must never be sent to POS SPs.
        // - POS-facing IDs (table_id, session_id, terminal_session_id, order_id) must be
        //   Krypton-domain identifiers recognized by `krypton_woosoo`.
        $this->assertPosIdentityContract($attributes);

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
            return DB::transaction(function () use ($device, &$createdOrderId, $clientSubmissionId) {
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

                DB::afterCommit(function () use ($deviceOrder, $clientSubmissionId) {
                    try {
                        if (config('api.print_events_enabled', false)) {
                            $submissionId = $clientSubmissionId ?: (string) Str::uuid();
                            $printEvent = app(PrintTicketService::class)
                                ->createInitialPrintEvent($deviceOrder, $submissionId);

                            $deviceOrder->print_event_id = $printEvent->id;
                            $deviceOrder->save();
                            $deviceOrder->refresh();
                        }
                        PrintOrder::dispatch($deviceOrder);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });

                return $deviceOrder;
            });
        } catch (\Throwable $e) {
            Log::error('Order creation failed', [
                'request_id' => $this->attributes['request_id'] ?? null,
                'device_id' => $device->id,
                'created_order_id' => $createdOrderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function updateAttributes($array = [])
    {
        foreach ($array as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Fetch default values needed for order processing.
     */
    protected function getDefaultAttributes(): array
    {
        $contextService = app(KryptonContextService::class);
        $defaults = $contextService->getData();

        // Allow session_id to remain null so CreateOrder can throw SessionNotFoundException.
        // Other POS context values may still use sensible fallbacks when unavailable.
        $normalized = [
            'price_level_id' => $defaults['price_level_id'] ?? null,
            'tax_set_id' => $defaults['tax_set_id'] ?? null,
            'service_type_id' => $defaults['service_type_id'] ?? 1,
            'revenue_id' => $defaults['revenue_id'] ?? 1,
            'terminal_id' => $defaults['terminal_id'] ?? 1,
            'session_id' => $defaults['session_id'] ?? null,  // null propagates to CreateOrder which throws SessionNotFoundException
            'terminal_session_id' => $defaults['terminal_session_id'] ?? null,
            'employee_log_id' => $defaults['employee_log_id'] ?? null,
            'cash_tray_session_id' => $defaults['cash_tray_session_id'] ?? null,
            'terminal_service_id' => $defaults['terminal_service_id'] ?? null,
            'employee_id' => $defaults['employee_id'] ?? null,
            'cashier_employee_id' => $defaults['cashier_employee_id'] ?? null,
            // Preserve an explicit POS context value, even when it is null.
            // If older/fake contexts omit the key, map it from employee_log_id
            // so the default tuple remains internally consistent.
            'server_employee_log_id' => array_key_exists('server_employee_log_id', $defaults)
                ? $defaults['server_employee_log_id']
                : ($defaults['employee_log_id'] ?? null),
        ];

        if (($normalized['session_id'] ?? null) === null && app()->runningUnitTests()) {
            $testSessionId = Cache::get(self::TEST_KRYPTON_SESSION_CACHE_KEY);

            if (is_numeric($testSessionId)) {
                $normalized['session_id'] = (int) $testSessionId;
            }
        }

        $params = [
            'start_employee_log_id' => $normalized['employee_log_id'] ?? null,
            'current_employee_log_id' => $normalized['employee_log_id'] ?? null,
            'close_employee_log_id' => $normalized['employee_log_id'] ?? null,
            'server_employee_log_id' => $normalized['server_employee_log_id'],
            'is_online_order' => false,
            'reference' => '',
        ];

        return array_merge($normalized, $params);
    }

    protected function cancelOrder(Device $device) {}

    public function voidOrder(DeviceOrder $deviceOrder)
    {
        app(BroadcastService::class)->dispatchBroadcastJob(new OrderVoided($deviceOrder));
    }

    protected function rollBackOrder(Device $device) {}

    /**
     * Calculate totals from items array
     * This matches the calculation logic in CreateOrderedMenu
     */
    protected function calculateTotalsFromItems(array $items): array
    {
        $subtotal = 0;
        $tax = 0;
        $taxRate = config('api.krypton.tax_rate', 0.10);

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            try {
                $posMenu = KryptonMenu::find((int) ($item['menu_id'] ?? 0));
                $price = $this->money($posMenu?->price ?? 0);
            } catch (\Throwable $e) {
                Log::warning('OrderService: POS price lookup failed, defaulting to 0', ['menu_id' => $item['menu_id'] ?? null, 'error' => $e->getMessage()]);
                $price = 0.0;
            }

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

    private function buildOrderReference(Device $device, mixed $reference): string
    {
        $reference = trim((string) ($reference ?? ''));
        if ($reference !== '') {
            return $reference;
        }

        $parts = ["woosoo device:{$device->id}"];
        if (! empty($device->ip_address)) {
            $parts[] = "ip:{$device->ip_address}";
        }

        return mb_substr(implode(' ', $parts), 0, 120);
    }

    /**
     * Validate that IDs used for POS transactions belong to the POS namespace.
     *
     * @throws RuntimeException
     */
    private function assertPosIdentityContract(array $attributes): void
    {
        $tableId = $attributes['table_id'] ?? null;

        if (! is_numeric($tableId) || (int) $tableId <= 0) {
            throw new RuntimeException('Invalid POS table_id: expected Krypton table identifier.');
        }

        if (app()->runningUnitTests() || app()->environment('testing')) {
            return;
        }

        $existsInPos = Table::where('id', (int) $tableId)->exists();
        if (! $existsInPos) {
            throw new RuntimeException("POS table_id not found in krypton_woosoo.tables: {$tableId}");
        }
    }
}

// applied_taxes
// applied_discounts
// applied_menu_categories
