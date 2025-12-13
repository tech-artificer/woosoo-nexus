# DeviceOrderManagementApiController

**File:** `app/Http/Controllers/Api/V1/DeviceOrderManagementApiController.php`

Purpose: Endpoints for devices to poll or check for order state updates.

Routes

- `POST /api/devices/order/current`
  - Controller: `DeviceOrderManagementApiController@getCurrentOrder`
  - Auth: `auth:device`
  - Request body: `{ sessionId: int }` (validated)
  - Behavior: finds the device's current order with status `CONFIRMED` for the provided `sessionId`. If not found, returns `{ success: false, data: <request data>, device: <device> }`. If found, returns `DeviceOrderResource`.

- `POST /api/devices/check-update`
  - Controller: `DeviceOrderManagementApiController@checkOrderUpdate`
  - Auth: `auth:device`
  - Request fields: `orderId`, `sessionId` (not explicitly validated in code)
  - Behavior: checks `OrderUpdateLog` for a matching unprocessed update for the order+session. If found, applies status changes to the `DeviceOrder` (COMPLETED or VOIDED), deletes the log, and returns `{ success: true, data: DeviceOrderResource }`. Otherwise returns `{ success: false, data: null }`.

Notes
- The controller references `App\Enums\OrderStatus` and uses `DeviceOrder`/`OrderUpdateLog` models.
- Consider adding stricter validation for `checkOrderUpdate` inputs and documenting expected request shapes in a shared API spec.

Example Requests & Responses

- `POST /api/devices/order/current` (authenticated)

Request body:

```
{ "sessionId": 555 }
```

Success response (DeviceOrderResource):

```
{
  "id": 123,
  "branch_id": 1,
  "device_id": 1,
  "order_id": 1001,
  "order_number": "ORD-1001",
  "session_id": 555,
  "tax": 1.80,
  "subtotal": 18.00,
  "guest_count": 2,
  "notes": null,
  "total": 19.80,
  "is_printed": 0,
  "status": "CONFIRMED",
  "order_items": [],
  "table": { "id": 5, "name": "Table 5" },
  "device": { "id": 1, "device_uuid": "...", "branch": "Main", "name": "Device 01", "table": "Table 5" },
  "created_at": "2025-11-28T12:34:56Z"
}
```

- `POST /api/devices/check-update` (authenticated)

Request body example:

```
{ "orderId": 1001, "sessionId": 555 }
```

Success response (when an update is applied):

```
{ "success": true, "data": { /* DeviceOrderResource payload similar to above */ } }
```

If no update found:

```
{ "success": false, "data": null }
```
