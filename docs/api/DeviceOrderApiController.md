# DeviceOrderApiController

**File:** `app/Http/Controllers/Api/V1/DeviceOrderApiController.php`

Purpose: Handles incoming order creation requests from devices (invokable controller).

Route

- `POST /api/devices/create-order`
  - Controller: `DeviceOrderApiController` (invokable `__invoke`)
  - Auth: `auth:device`
  - Request: `StoreDeviceOrderRequest` (request validation class)
  - Behavior:
    - Validates request via `StoreDeviceOrderRequest`.
    - Retrieves the calling device via `$request->user()`.
    - If the device exists and has a `table_id`, calls `OrderService::processOrder($device, $validatedData)` to create internal order models, adds order items with `addOrderItems`, and returns JSON `{ success: true, order: DeviceOrderResource }` with HTTP 201.
    - If the device is not assigned to a table, returns `{ success: false, message: 'Order processing failed.', errors: [...] }` with HTTP 500.

- Response: `201` on success with `DeviceOrderResource` payload.

Notes
- The controller relies on `App\Services\Krypton\OrderService` to perform domain logic and on the `Device` model being the authenticated actor via Sanctum.
- The controller currently has commented-out dispatches for `OrderCreated` and `PrintOrder` — review if broadcasting/printing should be triggered here.

Example Request & Response

- Example `POST /api/devices/create-order` (authenticated)

Request headers:

  Authorization: `Bearer <device-token>`

Request body (example):

```
{
  "guest_count": 2,
  "subtotal": 18.00,
  "tax": 1.80,
  "discount": 0.00,
  "total_amount": 19.80,
  "items": [
    {
      "menu_id": 46,
      "ordered_menu_id": null,
      "name": "Pork Belly",
      "quantity": 1,
      "price": 9.00,
      "note": "No onions",
      "subtotal": 9.00,
      "tax": 0.90,
      "discount": 0.00
    },
    {
      "menu_id": 47,
      "name": "Chicken Rice",
      "quantity": 1,
      "price": 9.00,
      "subtotal": 9.00
    }
  ]
}
```

Response (201) — sample `DeviceOrderResource` wrapper:

```
{
  "success": true,
  "order": {
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
    "order_items": [
      {
        "id": 1,
        "order_id": 123,
        "menu_id": 46,
        "price": 9.00,
        "quantity": 1,
        "tax": 0.90,
        "subtotal": 9.00,
        "discount": 0.00,
        "notes": "No onions",
        "total": 9.90,
        "created_at": "2025-11-28T12:34:56Z"
      }
    ],
    "table": {
      "id": 5,
      "name": "Table 5",
      "status": "occupied"
    },
    "device": {
      "id": 1,
      "device_uuid": "e7a1f8d4-...",
      "branch": "Main Branch",
      "name": "Device 01",
      "table": "Table 5"
    },
    "created_at": "2025-11-28T12:34:56Z"
  }
}
```
