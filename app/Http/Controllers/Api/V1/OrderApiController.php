<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Order\CreateOrderedMenu;
use App\Enums\OrderStatus;
use App\Events\Order\OrderPrinted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\PrintOrder;
use App\Events\PrintRefill;
use App\Http\Controllers\Controller;
use App\Http\Requests\RefillOrderRequest;
use App\Http\Resources\DeviceOrderResource;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\Krypton\Menu;
use App\Models\Krypton\Menu as KryptonMenu;
use App\Models\Krypton\OrderedMenu;
use App\Services\DurableRefillGuard;
use App\Services\Krypton\KryptonContextService;
use App\Services\PrintTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Support basic filtering and pagination for device/admin UIs.
        $request = request();

        $query = DeviceOrder::with(['device', 'table', 'items.menu'])->orderBy('created_at', 'desc');

        // Optional status filter: comma-separated list of status values
        if ($statuses = $request->query('status')) {
            $statusArr = array_filter(array_map('trim', explode(',', $statuses)));
            if (! empty($statusArr)) {
                $query->whereIn('status', $statusArr);
            }
        }

        if ($sessionId = $request->query('session_id')) {
            $query->where('session_id', $sessionId);
        }

        // If authenticated as a device, restrict to that specific device.
        // Branch scoping alone can leak sibling tablet orders and break
        // device-local active-order recovery in the PWA.
        $device = $request->user();
        if ($device && isset($device->id)) {
            $query->where('device_id', $device->id);
        }

        if ($device && isset($device->branch_id)) {
            $query->where('branch_id', $device->branch_id);
        }

        $perPage = (int) $request->query('per_page', 25);

        $paginated = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => DeviceOrderResource::collection($paginated)->response()->getData(true),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, DeviceOrder $order)
    {
        $device = $request->user();

        // Branch-level authorization: devices may only access orders for their branch
        if ($device && isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Optional session scoping: if client supplied a session_id, ensure it matches the order
        $sessionId = $request->input('session_id');
        if ($sessionId && $order->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        // Eager-load relationships to prevent N+1 queries
        $order->loadMissing(['items.menu', 'table', 'device']);

        return response()->json([
            'success' => true,
            'order' => new DeviceOrderResource($order),
        ]);
    }

    /**
     * Show the specified resource by external order_id field.
     */
    public function showByExternalId(Request $request, string $orderId)
    {
        $device = $request->user();

        $query = DeviceOrder::with(['items.menu', 'table', 'device'])->where('order_id', $orderId);

        // Scope to the authenticated device so we never return another device's row
        if ($device) {
            $query->where('device_id', $device->id);
        }

        // Use latest() so if somehow duplicates exist, the newest row wins
        $order = $query->latest('id')->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($device && isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $sessionId = $request->input('session_id');
        if ($sessionId && $order->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        return response()->json([
            'success' => true,
            'order' => new DeviceOrderResource($order),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Persist refill items and dispatch print event.
     *
     * Validates that items are refillable (meats/sides only).
     *
     * State Machine: NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED
     * Durable idempotency via RefillSubmission model prevents duplicate POS inserts on retry.
     */
    public function refill(RefillOrderRequest $request, int $orderId)
    {
        $clientSubmissionId = $request->input('client_submission_id');
        $refillGuard = app(DurableRefillGuard::class);

        Log::info('[REFILL] Received refill request', [
            'order_id' => $orderId,
            'ip' => $request->ip(),
            'items_count' => count($request->input('items', [])),
            'client_submission_id' => $clientSubmissionId,
        ]);

        // Validate authenticated device
        $device = $request->user();
        if (! $device || ! isset($device->id)) {
            return response()->json(['success' => false, 'message' => 'Device authentication required'], 401);
        }

        // RefillOrderRequest automatically validates items
        $validatedData = $request->validated();

        $deviceOrder = DeviceOrder::where('order_id', $orderId)
            ->where('device_id', $device->id)
            ->first();
        if (! $deviceOrder) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Authorization: ensure device is in the same branch as the order
        if (isset($device->branch_id) && isset($deviceOrder->branch_id) && $device->branch_id !== $deviceOrder->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Session scoping: if client provided session_id, ensure it matches
        $sessionId = $request->input('session_id');
        if ($sessionId && $deviceOrder->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        // Reject refill against terminal orders (completed, cancelled, voided).
        $terminalStatuses = [OrderStatus::COMPLETED, OrderStatus::CANCELLED, OrderStatus::VOIDED, OrderStatus::ARCHIVED];
        if (in_array($deviceOrder->status, $terminalStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Refill not allowed: order is '.$deviceOrder->status->value,
                'error' => ['code' => 'ORDER_NOT_ACTIVE', 'status' => $deviceOrder->status->value],
            ], 409);
        }

        // Generate a server-side submission id if the tablet did not provide one,
        // so the durable idempotency guard always runs.
        if (! $clientSubmissionId) {
            $clientSubmissionId = (string) Str::uuid();
            Log::info('[REFILL] Generated server-side client_submission_id', [
                'device_id' => $device->id,
                'order_id' => $orderId,
                'client_submission_id' => $clientSubmissionId,
            ]);
        }

        // === DURABLE IDEMPOTENCY GUARD ===
        // Check for existing submission or acquire lock
        $guardResult = $refillGuard->guard($device, $deviceOrder, $clientSubmissionId);

        // Already completed - return cached response
        if (! $guardResult['proceed'] && $guardResult['response']) {
            Log::info('[REFILL] Returning cached completed response', [
                'device_id' => $device->id,
                'order_id' => $orderId,
                'client_submission_id' => $clientSubmissionId,
            ]);

            return $guardResult['response'];
        }

        // Currently processing - return 409
        if (! $guardResult['proceed'] && ! $guardResult['response']) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate refill request already processing',
                'error' => [
                    'code' => 'REFILL_IN_PROGRESS',
                ],
            ], 409);
        }

        $submission = $guardResult['submission'];
        $isNewSubmission = $guardResult['is_new'] ?? true;

        // === STATE: PROCESSING → POS_CREATED ===
        // Map incoming items
        $incomingItems = $validatedData['items'] ?? [];
        $mappedItems = [];

        foreach ($incomingItems as $i => $it) {
            $name = trim(strval($it['name'] ?? ''));
            $quantity = intval($it['quantity'] ?? 1);

            $menu = null;
            // Priority 1: Use menu_id to lookup from POS
            if (! empty($it['menu_id'])) {
                try {
                    $menu = KryptonMenu::find($it['menu_id']);
                } catch (\Throwable $_e) {
                    $menu = null;
                }
            }

            // Priority 2: Fallback to name-based lookup if menu_id lookup failed or not provided
            if (! $menu && ! empty($name)) {
                try {
                    $menu = KryptonMenu::whereRaw('LOWER(receipt_name) = ?', [strtolower($name)])->first()
                        ?? KryptonMenu::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                } catch (\Throwable $_e) {
                    $menu = null;
                }
            }

            if (! $menu) {
                $menuRef = $it['menu_id'] ?? $name ?? 'unknown';
                $refillGuard->markFailed($submission, "Menu item not found: {$menuRef}");

                return response()->json(['success' => false, 'message' => "Menu item not found: {$menuRef}"], 422);
            }

            $mappedItems[] = [
                'menu_id' => $menu->id,
                'quantity' => $quantity,
                'index' => $it['index'] ?? ($i + 1),
                'seat_number' => $it['seat_number'] ?? 1,
                'note' => $it['note'] ?? 'Refill',
                'price' => $menu->price,
            ];
        }

        $kctx = app(KryptonContextService::class);
        $kdata = $kctx->getData();
        $orderCheckId = $this->resolvePosOrderCheckId($orderId);

        $attrs = [
            'order_id' => $orderId,
            'order_check_id' => $orderCheckId,
            // employee_log_id originates from Krypton (POS), not the local app user
            'employee_log_id' => $kdata['employee_log_id'] ?? null,
            'device_order_id' => $deviceOrder->id,
            'items' => $mappedItems,
        ];

        // Check if we already have POS_CREATED state (retry scenario)
        if ($submission->status === 'POS_CREATED') {
            Log::info('[REFILL] Resuming from POS_CREATED state', [
                'submission_id' => $submission->id,
                'device_id' => $device->id,
                'order_id' => $orderId,
            ]);
            // Use stored POS IDs for verification
            $posOrderedMenuIds = $submission->pos_ordered_menu_ids ?? [];
        } else {
            // Run POS-side inserts
            $attrs['mirror_local'] = false;

            try {
                $created = CreateOrderedMenu::run($attrs);
            } catch (\Throwable $e) {
                $refillGuard->markFailed($submission, $e->getMessage());
                Log::error('[REFILL] POS insert failed', [
                    'device_id' => $device->id,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['success' => false, 'message' => 'POS insert failed', 'error' => $e->getMessage()], 500);
            }

            // Extract POS ordered_menu IDs for idempotency tracking
            $posOrderedMenuIds = collect($created)->map(fn ($it) => is_array($it) ? ($it['id'] ?? null) : ($it->id ?? null))->filter()->values()->all();

            // Mark POS_CREATED immediately. If this fails the submission stays in
            // PROCESSING; the next retry will re-enter this branch and attempt another
            // POS insert. We therefore treat a marking failure as a hard error so the
            // client retries — at which point the DB unique constraint will prevent a
            // second POS insert only if the row was committed. Log loudly so ops can
            // reconcile manually if the state write fails after POS write succeeded.
            try {
                $refillGuard->markPosCreated($submission, $posOrderedMenuIds);
            } catch (\Throwable $e) {
                Log::critical('[REFILL] CRITICAL: POS insert succeeded but state mark failed — manual reconciliation may be required', [
                    'submission_id' => $submission->id,
                    'pos_ordered_menu_ids' => $posOrderedMenuIds,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'POS insert succeeded but state could not be recorded — please retry',
                    'pos_created' => true,
                    'submission_id' => $submission->id,
                ], 500);
            }
        }

        // Normalize created items for downstream processing
        // For retries, we need to fetch the POS items again
        if (! isset($created)) {
            // Retry scenario: fetch existing POS ordered_menu records
            $created = $this->fetchPosOrderedMenus($posOrderedMenuIds);
        }

        $posItems = collect($created)->map(function ($it) {
            if (is_array($it)) {
                return (object) $it;
            }

            return $it;
        })->values()->all();

        // Guard: if no items created in POS, nothing to mirror locally
        if (empty($posItems)) {
            $refillGuard->markCompleted($submission, ['success' => true, 'created' => []]);

            return response()->json(['success' => true, 'created' => []]);
        }

        try {
            // Build metadata for broadcast (menu names, quantities).
            $menuIds = collect($posItems)->pluck('menu_id')->filter()->unique()->values()->all();
            $menuNames = ! empty($menuIds)
                ? Menu::whereIn('id', $menuIds)->pluck('name', 'id')
                : collect();

            $metaItems = collect($posItems)->map(function ($item) use ($menuNames) {
                $menuId = $item->menu_id;
                $menuName = $menuNames->get($menuId) ?? $item->name ?? $item->receipt_name;

                return [
                    'menu_id' => $menuId,
                    'quantity' => $item->quantity ?? 1,
                    'name' => $menuName ?? "Menu #{$menuId}",
                ];
            })->values()->all();

            $refillEventMeta = [
                'items' => $metaItems,
                'refill_count' => count($mappedItems),
                'refilled_at' => now()->toIso8601String(),
            ];

            // Build local payload for each POS item.
            $localPayloads = [];
            foreach ($posItems as $pos) {
                $menuId = $pos->menu_id;
                $quantity = $pos->quantity ?? 1;
                $price = $pos->price ?? ($pos->unit_price ?? 0.00);
                $subtotal = $pos->sub_total ?? ($pos->subtotal ?? ($price * $quantity));
                $tax = $pos->tax ?? 0.00;
                $total = $pos->total ?? $subtotal;

                $localPayloads[] = [
                    'order_id' => $deviceOrder->id,
                    'ordered_menu_id' => $pos->id,
                    'menu_id' => $menuId,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'notes' => isset($pos->note) && trim((string) $pos->note) !== '' ? $pos->note : 'Refill',
                    'seat_number' => $pos->seat_number ?? 1,
                    'index' => $pos->index ?? 1,
                    'is_refill' => true,
                ];
            }

            // === STATE: POS_CREATED → MIRRORED → PRINT_EVENT_CREATED ===
            // Check if already mirrored (retry scenario)
            if ($submission->status === 'MIRRORED' || $submission->status === 'PRINT_EVENT_CREATED' || $submission->status === 'COMPLETED') {
                Log::info('[REFILL] Resuming from MIRRORED/PRINT_EVENT_CREATED state', [
                    'submission_id' => $submission->id,
                    'current_status' => $submission->status,
                ]);
            } else {
                // Mirror POS rows into local device_order_items with retry logic.
                $maxAttempts = 3;
                $lastError = null;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    try {
                        DB::transaction(function () use ($localPayloads) {
                            if (! empty($localPayloads)) {
                                DeviceOrderItems::query()->insert($localPayloads);
                            }
                        });

                        // Mark MIRRORED state
                        $refillGuard->markMirrored($submission);
                        $lastError = null;
                        break;
                    } catch (\Throwable $e) {
                        $lastError = $e->getMessage();
                        if ($attempt >= $maxAttempts) {
                            Log::error('[REFILL] Local mirror failed after max retries', [
                                'order_id' => $orderId,
                                'device_order_id' => $deviceOrder->id,
                                'attempt' => $attempt,
                                'error' => $e->getMessage(),
                            ]);
                            break;
                        }
                        Log::warning('[REFILL] Local mirror retry', [
                            'order_id' => $orderId,
                            'device_order_id' => $deviceOrder->id,
                            'attempt' => $attempt,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Even if local mirror failed, we still have POS_CREATED state
                // Client can retry and we'll resume from MIRRORED step
                if ($lastError) {
                    // Mark as failed so client can retry
                    $refillGuard->markFailed($submission, "Local mirror failed: {$lastError}");

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to persist refill locally',
                        'error' => $lastError,
                        'pos_created' => true,
                        'submission_id' => $submission->id,
                    ], 500);
                }
            }

            // Create print event if not already done
            $printEvent = null;
            if ($submission->status !== 'PRINT_EVENT_CREATED' && $submission->status !== 'COMPLETED') {
                try {
                    DB::transaction(function () use ($deviceOrder, $posItems, $clientSubmissionId, &$printEvent) {
                        $printTicketService = app(PrintTicketService::class);
                        $printEvent = $printTicketService->createRefillPrintEvent(
                            $deviceOrder,
                            $posItems,
                            $clientSubmissionId
                        );
                        // device_orders.print_event_id does not exist in the schema;
                        // print_events owns the FK (device_order_id). Use the
                        // DeviceOrder::printEvent() relation to look up the latest.
                    });

                    // Mark PRINT_EVENT_CREATED state
                    if ($printEvent) {
                        $refillGuard->markPrintEventCreated($submission, $printEvent);
                    }
                } catch (\Throwable $e) {
                    Log::error('[REFILL] Print event creation failed', [
                        'submission_id' => $submission->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Non-fatal: can continue without print event
                }
            }

            // Dispatch events — only on first-time submission to prevent broadcast
            // replay when a retry resumes from MIRRORED or PRINT_EVENT_CREATED state.
            $deviceOrder->refresh();
            $freshOrder = $deviceOrder->fresh(['items.menu', 'device.table', 'table', 'serviceRequests']);

            if ($isNewSubmission) {
                try {
                    PrintRefill::dispatch($deviceOrder, $metaItems);
                    if ($freshOrder) {
                        OrderStatusUpdated::dispatch($freshOrder);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // === STATE: COMPLETED ===
            $responseBody = [
                'success' => true,
                'order' => $freshOrder ? DeviceOrderResource::make($freshOrder) : null,
                'created' => $created,
            ];

            $refillGuard->markCompleted($submission, $responseBody);

            return response()->json($responseBody);
        } catch (\Throwable $th) {
            report($th);
            $refillGuard->markFailed($submission, $th->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to persist refill', 'error' => $th->getMessage()], 500);
        }
    }

    /**
     * Fetch POS ordered_menu records by IDs (for retry scenarios)
     */
    private function fetchPosOrderedMenus(array $orderedMenuIds): array
    {
        if (empty($orderedMenuIds)) {
            return [];
        }

        try {
            return OrderedMenu::whereIn('id', $orderedMenuIds)->get()->all();
        } catch (\Throwable $e) {
            Log::warning('[REFILL] Failed to fetch POS ordered_menus for retry', [
                'ordered_menu_ids' => $orderedMenuIds,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function resolvePosOrderCheckId(int $orderId): ?int
    {
        try {
            $orderCheckId = DB::connection('pos')
                ->table('order_checks')
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('id');

            return $orderCheckId !== null ? (int) $orderCheckId : null;
        } catch (\Throwable $e) {
            Log::warning('Unable to resolve POS order_check_id for refill', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mark a device order as printed.
     */
    public function markPrinted(Request $request, int $orderId)
    {
        $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
        if (! $deviceOrder) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $device = $request->user();
        if ($device && isset($device->branch_id) && isset($deviceOrder->branch_id) && $device->branch_id !== $deviceOrder->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $sessionId = $request->input('session_id');
        if ($sessionId && $deviceOrder->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        if ($deviceOrder->is_printed) {
            return response()->json([
                'success' => true,
                'message' => 'Order was already printed',
                'data' => [
                    'order_id' => $deviceOrder->order_id,
                    'is_printed' => $deviceOrder->is_printed,
                    'printed_at' => $deviceOrder->printed_at,
                ],
            ]);
        }

        $deviceOrder->is_printed = true;
        $deviceOrder->printed_at = now();
        $deviceOrder->save();

        try {
            OrderPrinted::dispatch($deviceOrder);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Dispatch a print job for the specified order.
     */
    public function dispatch(int $orderId)
    {
        $order = DeviceOrder::where('order_id', $orderId)->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        PrintOrder::dispatch($order);

        return response()->json(['success' => true, 'status' => 'dispatched']);
    }

    /**
     * Get print-ready order data for the specified order.
     */
    public function print(int $orderId)
    {
        $order = DeviceOrder::where('order_id', $orderId)->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $items = $order->items()->with('menu')->orderBy('index')->get();

        return response()->json([
            'order' => $order->only([
                'id',
                'order_id',
                'order_number',
                'device_id',
                'status',
                'created_at',
                'guest_count',
            ]),
            'tablename' => $order->table->name ?? null,
            'items' => $items->map(fn ($it) => [
                'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? null,
                'quantity' => $it->quantity ?? null,
            ])->values()->all(),
        ]);
    }
}
