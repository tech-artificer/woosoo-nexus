# OrderApiController

**File:** `app/Http/Controllers/Api/V1/OrderApiController.php`

Purpose: General order endpoints; in the current controller many methods are placeholders but `show` returns a DeviceOrder payload.

Routes

- `GET /api/device-order/{order}`
  - Note: In `routes/api.php` there is a route `Route::get('/device-order/{order}', [OrderApiController::class, 'show']);` which maps to this controller's `show` method.
  - Controller: `OrderApiController@show`
  - Auth: public (as defined in `routes/api.php` top-level)
  - Response: `{ success: true, order: <DeviceOrder model JSON> }`

Notes
- Other CRUD methods (`index`, `store`, `update`, `destroy`) are present but empty — add implementations as needed.
- The `show` method returns the raw `DeviceOrder` model; for API stability consider returning a `DeviceOrderResource` or similar resource transformer to control fields.

Example Response

- Example `GET /api/device-order/{order}`

Response (200):

```
{
  "success": true,
  "order": {
    "id": 123,
    "order_id": 1001,
    "order_number": "ORD-1001",
    "device_id": 1,
    "status": "CONFIRMED",
    "guest_count": 2,
    "created_at": "2025-11-28T12:34:56Z",
    "items": [ { "name": "Pork Belly", "quantity": 1 } ],
    "tablename": "Table 5"
  }
}
```

Recommendation: convert this to return `DeviceOrderResource` for consistent API responses.
