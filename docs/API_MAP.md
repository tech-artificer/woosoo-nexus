API Map — woosoo-nexus

This document lists the API endpoints defined in `routes/api.php`, the controller method that handles them, request inputs (validation rules where available), and the typical response shape (resource classes or JSON structures).

Notes:
- `api` middleware group routes are listed under "API (no auth)".
- `auth:device` middleware group routes require device authentication (Sanctum tokens).
- Where a FormRequest is used, its `rules()` are used as the authoritative inputs.

---

## Route Organization

Routes are organized into three sections:
1. **PUBLIC** - No authentication required (CORS, health, debug, device IP)
2. **GUEST** - Unauthenticated device routes (login, register)
3. **AUTHENTICATED** - Requires `auth:device` middleware (Sanctum tokens)

---

## Public Routes (No Authentication)

### OPTIONS /{any}
- Purpose: CORS preflight / general options handler
- Response: 200 empty JSON

### GET /device/ip
- Controller: inline closure
- Request: none
- Response: `{ "ip": string, "user_agent": string }`

### GET /health
- Controller: inline closure
- Purpose: Health check for app and database connectivity
- Response: `{ "success": true, "data": { "app": boolean, "mysql": boolean, "pos": boolean }, "message": "Health check" }`

### GET /debug/pos/menus/course
- Controller: inline closure
- Purpose: Debug endpoint for POS stored procedure testing (local/debug only)
- Query param: `course` (required)
- Response: `{ "success": true, "data": { "course": string, "stored_proc_rows": array, "menu_rows": array } }`
- Note: Returns 403 if not in local/debug environment

---

## Guest Routes (Unauthenticated)

### GET /token/create
- Controller: `AuthApiController@createToken`
- Request: `email` (required, email), `password` (required), `device_name` (required)
- Response: `{ "token": string }`

### GET /devices/login
- Controller: `Auth\DeviceAuthApiController@authenticate`
- Request: optional `ip_address` (string) — uses `request->ip()` fallback
- Behaviour: prefers client-supplied private LAN IP addresses; searches `Device` by `ip_address` and `is_active=true`.
- Success response (200): `{ success: true, token: string, device: Device, table: {id,name} | null, expires_at: datetime, ip_used: string }`
- Failure response (404): `{ success: false, error: 'Device not found', ip_address }`

---

## Public API Routes (Menu Browsing - No Auth)

### POST /devices/register
- Controller: `Auth\DeviceAuthApiController@register`
- Request: validated by `DeviceRegisterRequest`
- Behaviour: prefers client-supplied private IP; calls `RegisterDevice::run(...)`, creates a token
- Success response (201): `{ success: true, token: string, device: Device, table: { id, name } | null, expires_at: datetime, ip_used: string }`
- Failure (409): when device already registered (includes existing device and ip_used)

### GET /menus
- Controller: `BrowseMenuApiController@getMenus`
- Query params: optional `menu_id` (integer)
- Behaviour:
  - If `menu_id` present, returns single `MenuResource` for that menu (with modifiers, image)
  - Otherwise returns collection `MenuResource::collection` of menus with `modifiers` and `image` loaded
- Response: JSON array of `MenuResource` objects

### GET /menus/with-modifiers
- Controller: `BrowseMenuApiController@getMenusWithModifiers`
- Query params: optional `menu_id` (integer)
- Behaviour: returns menus/packages with nested modifiers
- Response: collection of `MenuResource`

### GET /menus/modifier-groups
- Controller: `BrowseMenuApiController@getAllModifierGroups`
- Query params: optional `modifiers` boolean — if true includes modifiers per group
- Response: collection of `MenuResource` representing modifier groups

### GET /menus/modifiers
- Controller: `BrowseMenuApiController@getMenuModifiers`
- Response: `MenuModifierResource` collection

### GET /menus/modifier-groups/{id}/modifiers
- Controller: `BrowseMenuApiController@getMenuModifiersByGroup`
- Path param: `id` (modifier group id)
- Response: `MenuModifierResource` collection for that group

### GET /menus/course
- Controller: `BrowseMenuApiController@getMenusByCourse`
- Query param: `course` (required, string)
- Response: `MenuResource` collection

### GET /menus/group
- Controller: `BrowseMenuApiController@getMenusByGroup`
- Query param: `group` (required, string)
- Response: `MenuResource` collection

### GET /menus/group-raw
- Controller: `BrowseMenuApiController@getMenusByGroupRaw`
- Query param: `group` (required, string)
- Response: Raw menu data

### GET /menus/modifiers-by-group
- Controller: `BrowseMenuApiController@getModifiersGroupedByGroup`
- Response: Modifiers grouped by their group

### GET /menus/package-modifiers
- Controller: `BrowseMenuApiController@getPackageModifiers`
- Response: Package modifiers collection

### GET /menus/category
- Controller: `BrowseMenuApiController@getMenusByCategory`
- Query param: `category` (required, string)
- Response: `MenuResource` collection

### GET /menus/bundle
- Controller: `Menu\MenuBundleController->__invoke`
- Response: `{ packages: ... }`

---

## Authenticated Routes (Requires auth:device)

### Token Management

#### GET /token/verify
- Controller: `DeviceAuthApiController@verifyToken`
- Request: Bearer token required
- Response: `{ valid: boolean, message?: string, device?: { id, name }, created_at, expires_at }`

### Device Management

#### Resource /devices
Routes created by `Route::resource('/devices', DeviceApiController::class)`:

- **GET /devices** → `DeviceApiController@index`
  - Response: collection of `DeviceResource`
  
- **POST /devices** → `DeviceApiController@store`
  - Request: `StoreDeviceRequest` (name, ip_address, port?, table_id?)
  - Response (201): `DeviceResource`
  
- **GET /devices/{device}** → `DeviceApiController@show`
  - Response: `DeviceResource`
  
- **PUT/PATCH /devices/{device}** → `DeviceApiController@update`
  - Request: `UpdateDeviceRequest`
  - Response: `DeviceResource`
  
- **DELETE /devices/{device}** → `DeviceApiController@destroy`
  - Response: 204 empty

#### GET|POST /device/table
- Controller: `DeviceApiController@getTableByIp`
- Response: Table info for device by IP

#### POST /devices/refresh
- Controller: `Auth\DeviceAuthApiController@refresh`
- Request: Bearer token (current token will be revoked)
- Response: `{ success: true, token: newToken, device: Device, table: {id,name}, expires_at }`

#### POST /devices/logout
- Controller: `Auth\DeviceAuthApiController@logout`
- Request: Bearer token
- Response: `{ message: 'Successfully logged out' }`

#### POST /devices/create-order
- Controller: `DeviceOrderApiController::__invoke`
- Request: `StoreDeviceOrderRequest`
- Success response (201): `{ success: true, order: DeviceOrderResource }`
- Conflict response (409): `{ success: false, message: string, order: DeviceOrderResource }`

### Order Operations

All order routes are grouped under `/order/{orderId}/`:

#### POST /order/{orderId}/dispatch
- Controller: `OrderApiController@dispatch`
- Purpose: Dispatch a print job for the specified order
- Response: `{ success: true }` or `{ success: false, message: 'Order not found' }` (404)

#### GET /order/{orderId}/print
- Controller: `OrderApiController@print`
- Purpose: Get print-ready order data
- Response:
  ```json
  {
    "order": { "id", "order_id", "order_number", "device_id", "status", "created_at", "guest_count" },
    "tablename": string | null,
    "items": [{ "name": string, "quantity": number }, ...]
  }
  ```

#### POST /order/{orderId}/refill
- Controller: `OrderApiController@refill`
- Purpose: Persist refill items to POS and local database, dispatch print event
- Request body:
  ```json
  {
    "items": [
      { "name": string, "quantity": number, "menu_id?": number, "price?": number, "seat_number?": number, "note?": string }
    ]
  }
  ```
- Success response (200): `{ success: true, created: array }`
- Failure response (422): `{ success: false, message: "Menu item not found: {name}" }`

#### POST /order/{orderId}/printed
- Controller: `OrderApiController@markPrinted`
- Purpose: Mark a device order as printed and record the timestamp
- Behaviour: Sets `is_printed = true` and `printed_at = now()` on the DeviceOrder
- Success response (200): `{ success: true }`
- Failure response (404): `{ success: false, message: 'Order not found' }`

### Device Orders

#### GET /device-order/{order}
- Controller: `OrderApiController@show`
- Route model binds `DeviceOrder $order`
- Response: `{ success: true, order: DeviceOrder }`

#### GET /device-order/by-order-id/{orderId}
- Controller: `OrderApiController@showByExternalId`
- Path param: `orderId` (external order identifier stored in `device_orders.order_id`)
- Response (200): `{ success: true, order: DeviceOrder }`
- Response (404): `{ success: false, message: 'Order not found' }`

### Table Services

#### GET /tables/services
- Controller: `TableServiceApiController@index`
- Response: JSON array of `TableService` records

#### POST /service/request
- Controller: `ServiceRequestApiController@store`
- Request: `StoreServiceRequest` (table_service_id, order_id)
- Response (201): `{ success: true, message: 'Service sent successfully', service_request: ServiceRequestResource }`

### Sessions

#### GET /session/latest
- Controller: `Krypton\TerminalSessionApiController@getLatestSession`
- Response: `{ session: ... }`

---

## DeviceOrder Model Fields

The `DeviceOrder` model includes print tracking fields:

| Field | Type | Description |
|-------|------|-------------|
| `is_printed` | boolean | Whether the order has been printed (default: false) |
| `printed_at` | datetime | Timestamp when the order was marked as printed (nullable) |

---

## FormRequest definitions (authoritative request shapes)

- `StoreDeviceRequest` (`app/Http/Requests/StoreDeviceRequest.php`)
  - name: required|string|max:255
  - ip_address: required|ip
  - port: nullable|integer
  - table_id: nullable|integer|exists:tables,id

- `UpdateDeviceRequest` (same as `StoreDeviceRequest`)

- `StoreDeviceOrderRequest` (`app/Http/Requests/StoreDeviceOrderRequest.php`)
  - guest_count: required|integer|min:1
  - subtotal: required|numeric|min:0
  - tax: required|numeric|min:0
  - discount: required|numeric|min:0
  - total_amount: required|numeric|min:0
  - items: required|array
    - items.*.menu_id: required|integer
    - items.*.name: required|string
    - items.*.quantity: required|integer|min:1
    - items.*.price: required|numeric|min:0
    - items.*.note: nullable|string
    - items.*.subtotal: required|numeric|min:0
    - items.*.tax: nullable|numeric|min:0
    - items.*.discount: nullable|numeric|min:0

- `DeviceRegisterRequest` (`app/Http/Requests/DeviceRegisterRequest.php`)
  - (used by `DeviceAuthApiController@register`) — see file for exact rules (not expanded here). 

- `StoreServiceRequest` (`app/Http/Requests/StoreServiceRequest.php`)
  - table_service_id: required|integer
  - order_id: required|exists:device_orders,order_id|integer

---

## Response resources

- `DeviceResource` — fields: id, device_uuid, branch, name, table (name or table_id)
- `MenuResource` — fields: id, group, category, course, name, kitchen_name, receipt_name, price (formatted), cost, tax, tax_amount, modifiers (whenLoaded), img_url
- `MenuModifierResource` — used for modifier menus
- `DeviceOrderResource` — id, order_id, order_number, device (DeviceResource), order (raw order JSON), table (checkTableStatus()), status
- `ServiceRequestResource` — id, order_id, table_service_id, created_at

---

## Notes / Actionable items

- All order operations (dispatch, print, refill, printed) now require `auth:device` middleware
- Routes are organized with clear section headers: PUBLIC, GUEST, AUTHENTICATED
- Inline closures for token/verify moved to `DeviceAuthApiController@verifyToken`
- Order routes now properly grouped under `/order/{orderId}/` prefix

---

Generated on: 2025-12-12

