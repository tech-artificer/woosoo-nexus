# Printer App API Requirements

Backend endpoints required for the Flutter Printer App to manage printed orders and implement polling fallback.

**Date:** December 12, 2025  
**Version:** 1.1  

---

## Important Implementation Notes

### Existing Infrastructure

| Item | Status | Details |
|------|--------|--------|
| `device_orders` table | ✅ Exists | Laravel app database (`woosoo_api`) |
| `is_printed` column | ✅ Exists | `tinyint(1)` boolean |
| `printed_at` column | ✅ Exists | Migration `2025_12_12_191603` |
| `printed_by` column | ❌ Needs migration | For printer device tracking |
| `session_id` column | ✅ Exists | References POS terminal session |
| WebSocket (Reverb) | ✅ Configured | Port 6001, channel `admin.print` |

### Existing Endpoint - DO NOT MODIFY

An existing endpoint already marks orders as printed (used by web dashboard):

| Field | Value |
|-------|-------|
| **Route** | `POST /api/order/{orderId}/printed` (singular) |
| **Middleware** | `auth:sanctum` |
| **Controller** | `OrderApiController@markPrinted` |
| **Response** | `{ "success": true }` |

**⚠️ This endpoint must remain unchanged** for backward compatibility.

### New Endpoints for Printer App

Create new endpoints under `/api/orders/` (plural) prefix:

| Priority | Endpoint | Method | Status |
|----------|----------|--------|--------|
| **Required** | `/api/orders/{orderId}/printed` | POST | New - idempotent version |
| **Required** | `/api/orders/unprinted` | GET | New |
| Existing | `/api/session/latest` | GET | ✅ Works - see response format below |
| Optional | `/api/orders/printed/bulk` | POST | New |
| Optional | `/api/printer/heartbeat` | POST | New |

### Session Endpoint Response Format

The existing `GET /api/session/latest` returns:

```json
{
  "session": {
    "id": 555,
    "date_time_opened": "2025-12-12 08:00:00",
    "date_time_closed": null
  }
}
```

**Note:** Data is wrapped in `session` key (not `data`). Access via `response.session.id`.

### Authentication

Use `auth:device` middleware (device token auth via Sanctum):

1. Register printer as a device: `POST /api/devices/register` with registration code
2. Store returned token
3. Include token in all requests: `Authorization: Bearer <token>`

### WebSocket Channel

Listen on channel `admin.print` for event `.order.printed`:

```dart
// Pseudo-code for Flutter Pusher client
channel.bind('order.printed', (data) {
  // data contains DeviceOrder with items array
  printOrderTicket(data);
});
```

Reverb WebSocket connection details:
- Host: Same as API host
- Port: 6001 (or configured REVERB_PORT)
- App Key: See `.env` REVERB_APP_KEY

---

## Table of Contents

1. [Mark Order as Printed](#1-mark-order-as-printed)
2. [Get Unprinted Orders by Session](#2-get-unprinted-orders-by-session)
3. [Get Latest Session (Confirmation)](#3-get-latest-session-confirmation)
4. [Bulk Mark Orders as Printed](#4-bulk-mark-orders-as-printed-optional)
5. [Printer Heartbeat](#5-printer-heartbeat-optional)
6. [Database Changes](#database-changes)
7. [FormRequest Validation Schemas](#formrequest-validation-schemas)

---

## 1. Mark Order as Printed

Called by the printer app after successfully printing an order to update the `is_printed` flag.

| Field | Value |
|-------|-------|
| **Method** | `POST` |
| **Route** | `/api/orders/{orderId}/printed` |
| **Middleware** | `api` |
| **Controller** | `PrinterApiController@markPrinted` |

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `orderId` | integer | Yes | The `order_id` field from `device_orders` table |

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `printed_at` | datetime (ISO8601) | No | Timestamp when printed. Defaults to server time if not provided |
| `printer_id` | string | No | Identifier of the printer device (for logging/debugging) |

### Example Request

```bash
curl -X POST "http://localhost/api/orders/1001/printed" \
  -H "Content-Type: application/json" \
  -d '{
    "printed_at": "2025-12-12T14:30:00Z",
    "printer_id": "kitchen-printer-01"
  }'
```

### Response - Success (200)

```json
{
  "success": true,
  "message": "Order marked as printed",
  "data": {
    "order_id": 1001,
    "is_printed": 1,
    "printed_at": "2025-12-12T14:30:00Z"
  }
}
```

### Response - Already Printed (200)

```json
{
  "success": true,
  "message": "Order was already printed",
  "data": {
    "order_id": 1001,
    "is_printed": 1,
    "printed_at": "2025-12-12T14:25:00Z"
  }
}
```

### Response - Order Not Found (404)

```json
{
  "success": false,
  "message": "Order not found",
  "data": null
}
```

### Backend Implementation Notes

```php
// PrinterApiController.php

public function markPrinted(MarkOrderPrintedRequest $request, int $orderId)
{
    $order = DeviceOrder::where('order_id', $orderId)->first();

    if (!$order) {
        return response()->json([
            'success' => false,
            'message' => 'Order not found',
            'data' => null
        ], 404);
    }

    // Idempotent - return success even if already printed
    if ($order->is_printed) {
        return response()->json([
            'success' => true,
            'message' => 'Order was already printed',
            'data' => [
                'order_id' => $order->order_id,
                'is_printed' => $order->is_printed,
                'printed_at' => $order->printed_at
            ]
        ]);
    }

    $order->update([
        'is_printed' => 1,
        'printed_at' => $request->input('printed_at', now()),
        'printed_by' => $request->input('printer_id')
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Order marked as printed',
        'data' => [
            'order_id' => $order->order_id,
            'is_printed' => 1,
            'printed_at' => $order->printed_at
        ]
    ]);
}
```

---

## 2. Get Unprinted Orders by Session

Polling fallback endpoint to fetch all orders in the current session that haven't been printed yet.

| Field | Value |
|-------|-------|
| **Method** | `GET` |
| **Route** | `/api/orders/unprinted` |
| **Middleware** | `api` |
| **Controller** | `PrinterApiController@getUnprintedOrders` |

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `session_id` | integer | Yes | - | Current terminal session ID |
| `since` | datetime (ISO8601) | No | null | Only return orders created after this timestamp |
| `limit` | integer | No | 50 | Maximum number of orders to return (max: 100) |

### Example Request

```bash
curl "http://localhost/api/orders/unprinted?session_id=555&since=2025-12-12T12:00:00Z&limit=20"
```

### Response - Success with Orders (200)

```json
{
  "success": true,
  "session_id": 555,
  "count": 2,
  "orders": [
    {
      "id": 123,
      "order_id": 1001,
      "order_number": "ORD-1001",
      "session_id": 555,
      "tablename": "Table 5",
      "guest_count": 2,
      "status": "CONFIRMED",
      "is_printed": 0,
      "created_at": "2025-12-12T14:30:00Z",
      "order": {
        "order_id": 1001,
        "order_number": "ORD-1001",
        "guest_count": 2,
        "created_at": "2025-12-12T14:30:00Z"
      },
      "items": [
        {
          "id": 1,
          "menu_id": 46,
          "name": "Beef Rendang",
          "quantity": 2,
          "price": 12.00,
          "subtotal": 24.00,
          "note": "No onions"
        },
        {
          "id": 2,
          "menu_id": 52,
          "name": "Iced Tea",
          "quantity": 2,
          "price": 3.00,
          "subtotal": 6.00,
          "note": null
        }
      ]
    },
    {
      "id": 124,
      "order_id": 1002,
      "order_number": "ORD-1002",
      "session_id": 555,
      "tablename": "Table 3",
      "guest_count": 4,
      "status": "CONFIRMED",
      "is_printed": 0,
      "created_at": "2025-12-12T14:32:00Z",
      "order": {
        "order_id": 1002,
        "order_number": "ORD-1002",
        "guest_count": 4,
        "created_at": "2025-12-12T14:32:00Z"
      },
      "items": [
        {
          "id": 3,
          "menu_id": 10,
          "name": "Lunch Set B",
          "quantity": 4,
          "price": 15.00,
          "subtotal": 60.00,
          "note": null
        }
      ]
    }
  ]
}
```

**Note:** The response format for each order should match the WebSocket `order.printed` event payload structure so the printer app can use the same `printOrderTicket()` function.

### Response - No Unprinted Orders (200)

```json
{
  "success": true,
  "session_id": 555,
  "count": 0,
  "orders": []
}
```

### Response - Invalid Session (404)

```json
{
  "success": false,
  "message": "Session not found or expired",
  "session_id": 999,
  "count": 0,
  "orders": []
}
```

### Response - Missing session_id (422)

```json
{
  "success": false,
  "message": "The session_id field is required.",
  "errors": {
    "session_id": ["The session_id field is required."]
  }
}
```

### Backend Implementation Notes

```php
// PrinterApiController.php

public function getUnprintedOrders(GetUnprintedOrdersRequest $request)
{
    $sessionId = $request->input('session_id');
    $since = $request->input('since');
    $limit = min($request->input('limit', 50), 100);

    // Verify session exists
    $session = TerminalSession::find($sessionId);
    if (!$session) {
        return response()->json([
            'success' => false,
            'message' => 'Session not found or expired',
            'session_id' => $sessionId,
            'count' => 0,
            'orders' => []
        ], 404);
    }

    $orders = DeviceOrder::where('session_id', $sessionId)
        ->where('is_printed', 0)
        ->whereNotIn('status', ['CANCELLED', 'VOIDED'])
        ->when($since, fn($q) => $q->where('created_at', '>', $since))
        ->with(['items', 'table', 'device'])
        ->orderBy('created_at', 'asc')
        ->limit($limit)
        ->get();

    // Transform to match WebSocket event payload format
    $formattedOrders = $orders->map(function ($order) {
        return [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'order_number' => $order->order_number,
            'session_id' => $order->session_id,
            'tablename' => $order->table?->name ?? 'Unknown Table',
            'guest_count' => $order->guest_count,
            'status' => $order->status,
            'is_printed' => $order->is_printed,
            'created_at' => $order->created_at->toIso8601String(),
            'order' => [
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'guest_count' => $order->guest_count,
                'created_at' => $order->created_at->toIso8601String()
            ],
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'menu_id' => $item->menu_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->subtotal,
                'note' => $item->notes
            ])
        ];
    });

    return response()->json([
        'success' => true,
        'session_id' => $sessionId,
        'count' => $formattedOrders->count(),
        'orders' => $formattedOrders
    ]);
}
```
## 2a. PrintEvent API (recommended)

The PrintEvent API provides an event-driven alternative to polling raw `device_orders`. Servers create PrintEvents (for example on order creation) and printers consume them. This is the recommended flow for relay-style printer apps since it decouples printing from order persistence and preserves device-local session semantics.

**Authentication:** `auth:device` (Sanctum device token)

### Endpoints (PrintEvent workflow)

- **GET** `/api/printer/unprinted-events` — List unacknowledged PrintEvents.
  - Query: `session_id` (optional integer), `since` (optional ISO8601 datetime), `limit` (optional integer, default 100, max 200).
  - Returns: `{ success, count, events: [ { id, device_order_id, event_type, meta, created_at, order } ] }` where `order` contains `order_id`, `order_number`, and `items` (menu_id, quantity, name).

- **POST** `/api/printer/print-events/{id}/ack` — Acknowledge a PrintEvent.
  - Body: `{ printer_id?: string, printed_at?: ISO8601 }`.
  - Response: `200` `{ success: true, message: 'Acknowledged', data: { id, was_updated: bool } }`.
  - Notes: This is a concurrency-safe conditional update. `was_updated` is `true` only if the event was unacknowledged when this request ran.

- **POST** `/api/printer/print-events/{id}/failed` — Mark a PrintEvent as failed.
  - Body: `{ error?: string }` (max 1000 chars).
  - Response: `200` `{ success: true, message: 'Marked failed', data: { id, attempts, was_updated } }`.

### Authorization & Errors

- Devices are authorized at branch-level: the authenticated device's `branch_id` must match the `device_order.branch_id` for the event. Otherwise `403 Forbidden`.
- `404 Not Found` if the PrintEvent id does not exist.
- `422 Unprocessable Entity` for invalid inputs (e.g., malformed `printed_at` or too-long `error`).

### Example: GET unprinted events

```bash
curl "http://localhost/api/printer/unprinted-events?limit=50&since=2025-12-15T00:00:00Z" \
  -H "Authorization: Bearer <device-token>"
```

Example item in `events` array:

```json
{
  "id": 42,
  "device_order_id": 123,
  "event_type": "INITIAL",
  "meta": {},
  "created_at": "2025-12-15T01:00:00Z",
  "order": {
    "order_id": 1001,
    "order_number": "ORD-1001",
    "items": [ { "menu_id": 46, "quantity": 2, "name": "Beef Rendang" } ]
  }
}
```

### Notes for implementers

- Servers create PrintEvents via `PrintEventService::createForOrder($deviceOrder, 'INITIAL')` when an order is created. The printer app should prefer PrintEvents when available and fall back to `/api/orders/unprinted` only if needed.
- Keep `limit` server-side capped at `200` to avoid large responses. The default is `100`.
- Acknowledgements are idempotent — clients should call ack and treat `was_updated === false` as an already-handled event.

## 3. Get Latest Session (Confirmation)
---

## 3. Get Latest Session (Confirmation)

**Existing Endpoint:** `GET /api/session/latest`  
**Controller:** `TerminalSessionApiController@getLatestSession`

### Confirmation Required

Please confirm the response includes the following fields:

```json
{
  "success": true,
  "data": {
    "id": 555,
    "branch_id": 1,
    "started_at": "2025-12-12T08:00:00Z",
    "ended_at": null,
    "status": "ACTIVE",
    "created_at": "2025-12-12T08:00:00Z"
  }
}
```

The printer app needs:
- `id` - Session ID for polling unprinted orders
- `status` - To verify session is active

---

## 4. Bulk Mark Orders as Printed (Optional)

Efficiency improvement for marking multiple orders as printed in a single request.

| Field | Value |
|-------|-------|
| **Method** | `POST` |
| **Route** | `/api/orders/printed/bulk` |
| **Middleware** | `api` |
| **Controller** | `PrinterApiController@markPrintedBulk` |

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `order_ids` | array of integers | Yes | List of order IDs to mark as printed |
| `printed_at` | datetime (ISO8601) | No | Timestamp when printed. Defaults to server time |
| `printer_id` | string | No | Identifier of the printer device |

### Example Request

```bash
curl -X POST "http://localhost/api/orders/printed/bulk" \
  -H "Content-Type: application/json" \
  -d '{
    "order_ids": [1001, 1002, 1003],
    "printed_at": "2025-12-12T14:30:00Z",
    "printer_id": "kitchen-printer-01"
  }'
```

### Response - Success (200)

```json
{
  "success": true,
  "message": "3 orders marked as printed",
  "data": {
    "updated": [1001, 1002, 1003],
    "already_printed": [],
    "not_found": []
  }
}
```

### Response - Partial Success (200)

```json
{
  "success": true,
  "message": "2 orders marked as printed",
  "data": {
    "updated": [1001, 1002],
    "already_printed": [1003],
    "not_found": [1004]
  }
}
```

### Backend Implementation Notes

```php
// PrinterApiController.php

public function markPrintedBulk(MarkOrderPrintedBulkRequest $request)
{
    $orderIds = $request->input('order_ids');
    $printedAt = $request->input('printed_at', now());
    $printerId = $request->input('printer_id');

    $updated = [];
    $alreadyPrinted = [];
    $notFound = [];

    foreach ($orderIds as $orderId) {
        $order = DeviceOrder::where('order_id', $orderId)->first();

        if (!$order) {
            $notFound[] = $orderId;
            continue;
        }

        if ($order->is_printed) {
            $alreadyPrinted[] = $orderId;
            continue;
        }

        $order->update([
            'is_printed' => 1,
            'printed_at' => $printedAt,
            'printed_by' => $printerId
        ]);

        $updated[] = $orderId;
    }

    return response()->json([
        'success' => true,
        'message' => count($updated) . ' orders marked as printed',
        'data' => [
            'updated' => $updated,
            'already_printed' => $alreadyPrinted,
            'not_found' => $notFound
        ]
    ]);
}
```

---

## 5. Printer Heartbeat (Optional)

Track active printer devices for monitoring and debugging.

| Field | Value |
|-------|-------|
| **Method** | `POST` |
| **Route** | `/api/printer/heartbeat` |
| **Middleware** | `api` |
| **Controller** | `PrinterApiController@heartbeat` |

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `printer_id` | string | Yes | Unique identifier for the printer device |
| `printer_name` | string | No | Human-readable printer name |
| `bluetooth_address` | string | No | Bluetooth MAC address of connected printer |
| `app_version` | string | No | Printer app version |
| `session_id` | integer | No | Current session ID being monitored |
| `last_printed_order_id` | integer | No | Last successfully printed order ID |
| `timestamp` | datetime (ISO8601) | No | Client timestamp |

### Example Request

```bash
curl -X POST "http://localhost/api/printer/heartbeat" \
  -H "Content-Type: application/json" \
  -d '{
    "printer_id": "kitchen-printer-01",
    "printer_name": "Kitchen Thermal Printer",
    "bluetooth_address": "AA:BB:CC:DD:EE:FF",
    "app_version": "1.0.0",
    "session_id": 555,
    "last_printed_order_id": 1001,
    "timestamp": "2025-12-12T14:30:00Z"
  }'
```

### Response - Success (200)

```json
{
  "success": true,
  "message": "Heartbeat received",
  "data": {
    "server_time": "2025-12-12T14:30:01Z",
    "session_active": true
  }
}
```

### Backend Implementation Notes

```php
// PrinterApiController.php

public function heartbeat(PrinterHeartbeatRequest $request)
{
    $printerId = $request->input('printer_id');

    // Store in cache for monitoring (expires after 2 minutes)
    Cache::put("printer:heartbeat:{$printerId}", [
        'printer_id' => $printerId,
        'printer_name' => $request->input('printer_name'),
        'bluetooth_address' => $request->input('bluetooth_address'),
        'app_version' => $request->input('app_version'),
        'session_id' => $request->input('session_id'),
        'last_printed_order_id' => $request->input('last_printed_order_id'),
        'last_seen' => now()
    ], now()->addMinutes(2));

    // Check if session is still active
    $sessionActive = false;
    if ($sessionId = $request->input('session_id')) {
        $session = TerminalSession::find($sessionId);
        $sessionActive = $session && $session->status === 'ACTIVE';
    }

    return response()->json([
        'success' => true,
        'message' => 'Heartbeat received',
        'data' => [
            'server_time' => now()->toIso8601String(),
            'session_active' => $sessionActive
        ]
    ]);
}
```

---

## Database Changes

### Migration: Add print tracking columns to device_orders

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            // Add printed_at column after is_printed if it doesn't exist
            if (!Schema::hasColumn('device_orders', 'printed_at')) {
                $table->timestamp('printed_at')->nullable()->after('is_printed');
            }

            // Add printed_by column to track which printer device printed the order
            if (!Schema::hasColumn('device_orders', 'printed_by')) {
                $table->string('printed_by', 100)->nullable()->after('printed_at');
            }

            // Add index for efficient polling queries
            $table->index(['session_id', 'is_printed', 'status'], 'idx_unprinted_orders');
        });
    }

    public function down(): void
    {
        Schema::table('device_orders', function (Blueprint $table) {
            $table->dropIndex('idx_unprinted_orders');
            $table->dropColumn(['printed_at', 'printed_by']);
        });
    }
};
```

### Run Migration

```bash
php artisan make:migration add_print_tracking_to_device_orders_table
# Paste the above migration code
php artisan migrate
```

---

## FormRequest Validation Schemas

### MarkOrderPrintedRequest

**File:** `app/Http/Requests/MarkOrderPrintedRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkOrderPrintedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printed_at' => ['nullable', 'date'],
            'printer_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

### GetUnprintedOrdersRequest

**File:** `app/Http/Requests/GetUnprintedOrdersRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetUnprintedOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer', 'exists:terminal_sessions,id'],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
```

### MarkOrderPrintedBulkRequest

**File:** `app/Http/Requests/MarkOrderPrintedBulkRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkOrderPrintedBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer'],
            'printed_at' => ['nullable', 'date'],
            'printer_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

### PrinterHeartbeatRequest

**File:** `app/Http/Requests/PrinterHeartbeatRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrinterHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['required', 'string', 'max:100'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'bluetooth_address' => ['nullable', 'string', 'max:17'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'session_id' => ['nullable', 'integer'],
            'last_printed_order_id' => ['nullable', 'integer'],
            'timestamp' => ['nullable', 'date'],
        ];
    }
}
```

---

## Routes Configuration

**File:** `routes/api.php`

```php
// Printer API Routes (Public)
Route::prefix('orders')->group(function () {
    Route::post('/{orderId}/printed', [PrinterApiController::class, 'markPrinted']);
    Route::get('/unprinted', [PrinterApiController::class, 'getUnprintedOrders']);
    Route::post('/printed/bulk', [PrinterApiController::class, 'markPrintedBulk']);
});

Route::post('/printer/heartbeat', [PrinterApiController::class, 'heartbeat']);
```

---

## Summary

| Priority | Endpoint | Method | Status |
|----------|----------|--------|--------|
| **Required** | `/api/orders/{orderId}/printed` | POST | New |
| **Required** | `/api/orders/unprinted` | GET | New |
| Existing | `/api/session/latest` | GET | Confirm response format |
| Optional | `/api/orders/printed/bulk` | POST | New |
| Optional | `/api/printer/heartbeat` | POST | New |

---

## Questions for Backend Team — ANSWERED

1. **Is the `device_orders` table name correct?**  
   ✅ Yes, table is `device_orders` in the Laravel app database (`woosoo_api`).

2. **Does `is_printed` column already exist?**  
   ✅ Yes, exists as `tinyint(1)` boolean. `printed_at` timestamp also exists.

3. **What is the exact table name for terminal sessions?**  
   Sessions are in the POS database (`krypton_woosoo`), accessed via stored procedure `CALL get_latest_session()`. The `session_id` in `device_orders` references this.

4. **Should these endpoints require device authentication?**  
   ✅ Yes, use `auth:device` middleware. Register the printer as a device first.

5. **What statuses should exclude orders from unprinted list?**  
   Exclude: `CANCELLED`, `VOIDED`. Only `CONFIRMED` orders with `is_printed = 0` should be returned.

---

## Migration Required

Before implementing, create this migration:

```bash
php artisan make:migration add_printed_by_and_index_to_device_orders_table
```

```php
public function up(): void
{
    Schema::table('device_orders', function (Blueprint $table) {
        // printed_at already exists from migration 2025_12_12_191603
        
        // Add printed_by column for printer device tracking
        if (!Schema::hasColumn('device_orders', 'printed_by')) {
            $table->string('printed_by', 100)->nullable()->after('printed_at');
        }

        // Add composite index for efficient polling queries
        $table->index(['session_id', 'is_printed', 'status'], 'idx_unprinted_orders');
    });
}

public function down(): void
{
    Schema::table('device_orders', function (Blueprint $table) {
        $table->dropIndex('idx_unprinted_orders');
        $table->dropColumn('printed_by');
    });
}
```
