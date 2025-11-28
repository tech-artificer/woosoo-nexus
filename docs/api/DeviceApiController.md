# DeviceApiController

**File:** `app/Http/Controllers/Api/V1/DeviceApiController.php`

Purpose: Exposes basic device read endpoints and a helper to resolve a device's table by request IP.

Routes

- `GET /api/devices` (resource index)
  - Controller: `DeviceApiController@index`
  - Auth: `auth:device` (registered under `Route::resource('/devices', DeviceApiController::class)` in `routes/api.php` inside `auth:device` middleware)
  - Response: `200` array of `DeviceResource` objects

- `GET /api/devices/{device}` (resource show)
  - Controller: `DeviceApiController@show`
  - Auth: `auth:device`
  - Response: `200` `DeviceResource` (loads `table` relation)

- `GET /api/device/table` (authenticated)
  - Controller: `DeviceApiController@getTableByIp`
  - Auth: `auth:device` (route placed inside `auth:device` middleware).
  - Behavior: prefers the authenticated `Device` (`$request->user()`) to resolve the device's `table`. If no authenticated device is available, it falls back to resolving by request IP (`$request->ip()`).
  - Response (404): `{ success: false, message: 'No active device found for this IP', ip: '<ip>' }` (when lookup by IP fails)
  - Response (200): `{ success: true, device_id: <id>, table: { id, name } }`

Notes
- `getTableByIp` is intended for devices that connect without bearer tokens and need to discover which table they are assigned to based on their network IP. If you prefer this to require authentication, move the route into the `auth:device` group and adjust the behavior accordingly.

Example Request & Response

- Example `GET /api/devices` (authenticated)

Request headers:

  Authorization: `Bearer <device-token>`

Response (200) — array of `DeviceResource`:

```
[
  {
    "id": 1,
    "device_uuid": "e7a1f8d4-...",
    "branch": "Main Branch",
    "name": "Device 01",
    "table": "Table 5"
  }
]
```

- Example `GET /api/device/table` (authenticated)

Response (200):

```
{
  "success": true,
  "device_id": 1,
  "table": { "id": 5, "name": "Table 5" }
}
```

Response (404) when not found by IP:

```
{
  "success": false,
  "message": "No active device found for this IP",
  "ip": "192.168.1.50"
}
```
