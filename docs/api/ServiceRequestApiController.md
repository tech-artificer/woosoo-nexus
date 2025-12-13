# ServiceRequestApiController

**File:** `app/Http/Controllers/Api/V1/ServiceRequestApiController.php`

Purpose: Accepts service requests (e.g., call waiter, request bill) from device clients and broadcasts them.

Routes

- `POST /api/service/request`
  - Controller: `ServiceRequestApiController@store`
  - Auth: `auth:device`
  - Request: `StoreServiceRequest` (validation class) — expected to include at least `order_id` and `table_service_id`.
  - Behavior:
    - Validates request.
    - Finds the `DeviceOrder` by `order_id`.
    - Creates a `ServiceRequest` record via `$deviceOrder->serviceRequests()->create([...])`.
    - Broadcasts `ServiceRequestNotification` via `broadcast(new ServiceRequestNotification($serviceRequest))->toOthers()`.
    - Note: the controller currently does not return an explicit success JSON (commented out), but a successful broadcast will occur; consider returning `{ success: true }`.

Notes
- Ensure broadcasting configuration routes events to Reverb (or the configured broadcaster) and that events implement `ShouldBroadcastNow` if you want synchronous delivery without a queue.
- The request class `StoreServiceRequest` should be inspected for exact validation rules and shape.

Example Request & Response

- Example `POST /api/service/request` (authenticated)

Request headers:

  Authorization: `Bearer <device-token>`

Request body (example, matches `StoreServiceRequest`):

```
{
  "table_service_id": 2,
  "order_id": 1001
}
```

Controller behavior:

- Creates a `ServiceRequest` row attached to the `DeviceOrder`.
- Broadcasts `ServiceRequestNotification` with the newly created `ServiceRequest` payload.

Suggested response (controller currently does not return this, but this is a recommended shape):

```
{
  "success": true,
  "message": "Service request sent",
  "service_request": {
    "id": 321,
    "order_id": 1001,
    "table_service_id": 2,
    "created_at": "2025-11-28T12:40:00Z"
  }
}
```

Broadcast payload (example) — what listeners may receive via Reverb/Echo:

```
{
  "type": "ServiceRequestNotification",
  "data": {
    "id": 321,
    "order_id": 1001,
    "table_service_id": 2,
    "created_at": "2025-11-28T12:40:00Z"
  }
}
```
