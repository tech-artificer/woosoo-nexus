# Print Events Contract (Relay Device)

## Scope
Defines the required payload, endpoints, and polling rules so woosoo-nexus and relay-device-v2 stay aligned.

## WebSocket Broadcast
- Channel: admin.print (Option A: client-side filtering)
- Event: order.printed (relay also accepts .order.printed)

Payload (required fields):
```json
{
  "print_event_id": 12345,
  "device_id": "DEV-001",
  "order_id": 1001,
  "session_id": 555,
  "print_type": "INITIAL",
  "refill_number": null,
  "tablename": "Table 5",
  "created_at": "2025-01-22T12:30:00Z",
  "payload": {
    "order_number": "ORD-1001",
    "guest_count": 4,
    "items": [
      {
        "menu_id": 46,
        "name": "PORK BELLY",
        "quantity": 2,
        "price": "9.00",
        "subtotal": "18.00",
        "note": null
      }
    ]
  }
}
```

Accepted field variants from backend:
- print_event_id OR printEventId
- order_id OR orderId
- device_id OR deviceId
- session_id OR sessionId
- print_type OR printType
- refill_number OR refillNumber

Client filter rule:
- If print_event_id missing: ignore.
- If device_id missing or != config.deviceId: ignore.
- If print_event_id already exists in queue store: ignore.

## Polling Endpoint (Hybrid)
- Primary: GET /api/printer/unprinted-events
- Alias: GET /api/print-events/unprinted
- Query params: session_id (optional), since (optional ISO8601), limit (optional, max 200)

Response schema (current backend):
```json
{
  "success": true,
  "count": 3,
  "events": [
    {
      "id": 12345,
      "device_order_id": 456,
      "event_type": "INITIAL",
      "meta": {},
      "created_at": "2025-01-22T12:30:00Z",
      "order": {
        "order_id": "ORD-1001",
        "order_number": "ORD-000001-1001",
        "items": []
      }
    }
  ]
}
```

Client parsing:
- Accepts list from "events" or "print_events".

Server filter logic (current):
```
WHERE is_acknowledged = false
  AND (since IS NULL OR created_at > since)
ORDER BY created_at ASC
LIMIT 200
```

Polling watermark (client):
- Initial: since = null
- If response is non-empty: since = max(created_at) from response
- If response is empty or error: do not advance since
- Dedup by print_event_id prevents duplicates.

## Acknowledgments
Success:
- Path: POST /api/printer/print-events/{id}/ack
- Body:
```json
{
  "printer_id": "PB-58H-001",
  "printer_name": "Kitchen Printer",
  "bluetooth_address": "00:11:22:33:44:55",
  "printed_at": "2025-01-22T12:35:00Z",
  "app_version": "2.0.0"
}
```
- Idempotent: repeat calls are safe.

Failure:
- Path: POST /api/printer/print-events/{id}/failed
- Body:
```json
{
  "error": "Bluetooth connection timeout",
  "attempt_count": 2,
  "failed_at": "2025-01-22T12:36:00Z",
  "printer_name": "Kitchen Printer",
  "app_version": "2.0.0"
}
```
- Increments attempts, does not mark acknowledged.

## Heartbeat
- Path: POST /api/printer/heartbeat
- Body:
```json
{
  "device_id": "DEV-001",
  "printer_id": "PB-58H-001",
  "printer_name": "Kitchen Printer",
  "bluetooth_address": "00:11:22:33:44:55",
  "app_version": "2.0.0",
  "session_id": 555,
  "last_print_event_id": 12345,
  "last_printed_order_id": 1001,
  "timestamp": "2025-01-22T12:40:00Z",
  "status": {
    "printer_connected": true,
    "queue_pending": 0,
    "queue_failed": 0
  }
}
```

## Channel Scope Options
Option A (current): admin.print + client-side device_id filter.
Option B (future): device.{deviceId} channel with server-side isolation.

## Idempotency and Dedup
- Server must include print_event_id in WS and polling payloads.
- Relay deduplicates strictly by print_event_id in Sembast.
