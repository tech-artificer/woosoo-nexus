# Printer App Integration README

This README explains how to integrate the Flutter (or other) printer app with the Woosoo API and real-time event system (Reverb/Echo). It covers device registration, authentication, available endpoints, example payloads, and sample integration snippets for both the printer app and frontend.

Version: 1.0 (Dec 13, 2025)

---

## Quick Overview
- Printers should register as devices using `POST /api/devices/register` or `GET /api/devices/login` to obtain a Sanctum `Bearer` token. Tokens must be used on all printer API calls.
- Printer endpoints are protected by `auth:device` middleware (device token). Do not expose these endpoints publicly.
- Main endpoints available to printers:
  - `GET /api/orders/unprinted` – polling fallback for unprinted orders in a session
  - `POST /api/orders/{orderId}/printed` – mark an order as printed (idempotent)
  - `POST /api/orders/printed/bulk` – mark multiple orders as printed at once
  - `POST /api/printer/heartbeat` – report printer heartbeat
- Realtime events (WebSocket): Listen on `admin.print` channel for `.order.printed` events. Note: two events share the alias — `PrintOrder` (admin.print) for print clients, and `OrderPrinted` (admin.orders / orders.{orderId}) for admin UI; their payloads differ slightly (see Event section below).
- Legacy route `POST /api/order/{orderId}/printed` remains for compatibility; prefer the new plural endpoints.

## 1) Register Printer as a Device / Obtain Token
You must register the printer as a `Device` to receive a token. Either obtain a pre-generated registration code from admin or create one using the admin UI.

### Admin-registered devices
Administrators can pre-provision devices via the admin dashboard using the `Devices` page. When creating a device, set its static `ip_address`, `port` and optionally assign a `table`. After creating a device, the admin can generate a personal access token for the device and copy it into the printer application (this token is only shown once). This is useful for devices that should never call the `POST /api/devices/register` endpoint.

- **Web route**: `POST /devices/{device}/token` — available in admin web UI and returns a Bearer token when requested via AJAX. The token is generated with `admin-issued` label and default expiration of 1 year.


Example request (register):
```bash
curl -X POST "http://localhost/api/devices/register" \
  -H "Content-Type: application/json" \
  -d '{"name":"Kitchen Printer 01","code":"REGCODE123","app_version":"1.0.0","ip_address":"192.168.1.10"}'
```

Response sample:
```json
{
  "success": true,
  "token": "<token>",
  "device": { "id": 2, "name": "Kitchen Printer 01" },
  "expires_at": "2025-12-20 00:00:00",
}
```

Use the returned token in the `Authorization: Bearer <token>` header.

## 2) Polling Fallback: Fetch Unprinted Orders
Endpoint: `GET /api/orders/unprinted`
Query params: `session_id` (required), `since` (optional ISO8601), `limit` (optional, default 50)

Example:
```bash
curl -H "Authorization: Bearer <token>" "http://localhost/api/orders/unprinted?session_id=555&since=2025-12-13T05:00:00Z&limit=20"
```

Sample response:
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
      "order": { "order_id": 1001, "order_number": "ORD-1001", "guest_count": 2 },
      "items": [
        { "id": 1, "menu_id": 46, "name": "Beef Rendang", "quantity": 2, "price": 12.00, "subtotal": 24.00, "note": "No onions" }
      ]
    }
  ]
}
```

Processing logic: the printer app should print the ticket and then call `POST /api/orders/{orderId}/printed` to mark it printed. The call is idempotent; include `printer_id` for audit. The response includes `printed_at` and `printed_by`.

## 3) Mark Order as Printed
Endpoint: `POST /api/orders/{orderId}/printed` (idempotent)
Payload (optional): `{ "printed_at": "ISO8601", "printer_id": "kitchen-printer-01" }` — the server saves `printed_by` and returns `printed_at`/`is_printed` in the response and broadcast events.

Example (curl):
```bash
curl -X POST "http://localhost/api/orders/1001/printed" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"printed_at":"2025-12-13T11:20:00Z","printer_id":"kitchen-printer-01"}'
```

Success response:
```json
{
  "success": true,
  "message": "Order marked as printed",
  "data": { "order_id": 1001, "is_printed": 1, "printed_at": "2025-12-13T11:20:00Z" }
}
```

If the order is already printed, you will receive a 200 response with "Order was already printed"; this is idempotent and safe to call multiple times.

Legacy route: `POST /api/order/{orderId}/printed` exists; use new `orders/{orderId}` endpoints going forward.

## 4) Bulk Mark Orders as Printed
Endpoint: `POST /api/orders/printed/bulk` (array of order IDs)
Payload sample:
```json
{ "order_ids": [1001, 1002, 1003], "printed_at": "2025-12-13T11:30:00Z", "printer_id": "kitchen-printer-01" }
```

Response contains `updated`, `already_printed`, `not_found` arrays for partial status reporting.

## 5) Printer Heartbeat
Endpoint: `POST /api/printer/heartbeat` (optional)
Payload:
```json
{
  "printer_id": "kitchen-printer-01",
  "printer_name": "Kitchen Printer 1",
  "bluetooth_address": "AA:BB:CC:DD:EE:FF",
  "app_version": "1.0.0",
  "session_id": 555,
  "last_printed_order_id": 1001,
  "timestamp": "2025-12-13T11:35:00Z"
}
```

This is stored in the cache for quick monitoring and used by the `heartbeat` endpoint response to confirm `session_active` status.

## 6) WebSocket (Reverb) Integration
- Channel: `admin.print` (printer clients) — `PrintOrder` broadcasts on this channel with a print-focused payload (summary + items).
- Channel: `admin.orders` and `orders.{orderId}` (admin UI) — `OrderPrinted` broadcasts on these channels with the full order payload (includes `is_printed`, `printed_at`, `printed_by`, `total`, `device`, `table`).

Example `PrintOrder` payload (admin.print):
```json
{
  "order": { "order_id": 1001, "order_number": "ORD-1001", "guest_count": 2 },
  "tablename": "Table 5",
  "items": [{ "id": 1, "menu_id": 46, "name": "Beef Rendang", "quantity": 2 }]
}
```

Example `OrderPrinted` payload (admin.orders):
```json
{
  "order": {
    "id": 123, "order_id": 1001, "order_number": "ORD-1001",
    "status": "completed", "is_printed": true, "printed_at": "2025-12-13T11:20:00Z",
    "printed_by": "kitchen-printer-01", "total": 24.00,
    "device": { "id": 1, "name": "Device 01" },
    "table": { "id": 5, "name": "Table 5" }
  }
}
```

Example JS listener (using Echo):
```js
window.Echo.channel('admin.print').listen('.order.printed', (e) => {
  const order = e.order;
  // e.items array or e.order.order_items depending on event
  printOrderTicket(order, e.items); // Your printing logic
});
```

For Flutter/Pusher client, subscribe to `admin.print` and handle `order.printed` events similarly.

## 7) Payloads & Type Changes (Frontend changes required)
- `DeviceOrderResource` now contains additional fields used by printer frontend:
  - `is_printed` (boolean)
  - `printed_at` (ISO8601 string | null)
  - `printed_by` (string | null)
  - `tablename` (string)
  - `items` includes `{id, menu_id, name, quantity, price, subtotal, note}`

Update TypeScript models accordingly to consume these fields for UI or printer logic.

## 8) Idempotency & Race Conditions
- Marking an order printed is idempotent — calling `POST /api/orders/{orderId}/printed` multiple times is safe.
- If the order becomes `VOIDED` or `CANCELLED` before printing, it will be filtered out by the new `getUnprintedOrders` endpoint.

## 9) Recommendations
- Use `auth:device` tokens and secure them safely on the device.
- Keep polling and event listeners in sync: listen to `admin.print` for immediate triggers and fallback to poll `GET /api/orders/unprinted` if events are missed.
- Add a small retry policy for marking printed (e.g., 3 retries with exponential backoff) to recover from transient network issues.
- Add feature tests for new paths to CI to verify behavior.

---

If you want, I can add a small `printer-integration-sample` in resources/js/ or a `README-printer.md` with Flutter snippets and a minimal integration example for printers.
