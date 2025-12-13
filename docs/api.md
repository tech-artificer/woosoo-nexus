**API Routes (Aggregated)**
- **File**: `routes/api.php` — consolidated list of active API routes, grouped by middleware.

**Public / Guest (no auth)**
- **GET**: `/device/ip` : Returns detected client IP and user-agent. Controller: inline closure. Query: none.
- **GET**: `/device-order/{order}` : Controller: `OrderApiController@show` — returns a `DeviceOrder` by route-model binding.
- **GET**: `/order/{orderId}/dispatch` : Inline route that dispatches a `PrintOrder` event for the given `orderId`.
- **GET**: `/order/{orderId}/print` : Inline route returning trimmed order + items for printing.
- **GET**: `/token/create` : `AuthApiController@createToken` (guest middleware).
- **GET**: `/devices/login` : `DeviceAuthApiController@authenticate` (guest middleware).

**API Group (middleware: `api`) — Public endpoints intended for clients**
- **POST**: `/devices/register` : `DeviceAuthApiController@register` — Request: `DeviceRegisterRequest` (see schema below).
- **GET**: `/menus` : `BrowseMenuApiController@getMenus` — Query params: `menu_id` (nullable, integer).
- **GET**: `/menus/with-modifiers` : `BrowseMenuApiController@getMenusWithModifiers` — no required params.
- **GET**: `/menus/modifier-groups` : `BrowseMenuApiController@getAllModifierGroups` — Query param: `modifiers` (nullable, boolean) to include modifiers inline.
- **GET**: `/menus/modifiers` : `BrowseMenuApiController@getMenuModifiers` — returns grouped modifiers (PORK/BEEF/CHICKEN etc.).
- **GET**: `/menus/modifier-groups/{id}/modifiers` : `BrowseMenuApiController@getMenuModifiersByGroup` — Path param `id` (int).
- **GET**: `/menus/course` : `BrowseMenuApiController@getMenusByCourse` — Query param: `course` (required, string).
- **GET**: `/menus/group` : `BrowseMenuApiController@getMenusByGroup` — Query param: `group` (required, string).
- **GET**: `/menus/category` : `BrowseMenuApiController@getMenusByCategory` — Query param: `category` (required, string).
- **GET**: `/menus/bundle` : `MenuBundleController` (single-action controller) — see controller for params/response.

**Device-authenticated Group (middleware: `auth:device`)**
- **GET**: `/token/verify` : Inline closure — verifies the personal access token and returns `device` info.
- **Resource**: `/devices` : `DeviceApiController` — standard resource endpoints (index, show, store, update, destroy). `show` returns `Device` with `table` relationship.
- **GET**: `/device/table` : `DeviceApiController@getTableByIp` — Uses authenticated device if present; otherwise uses request IP to look up active device. Response: `{ success, device_id, table }` or 404.
- **POST**: `/devices/refresh` : `DeviceAuthApiController@refresh` — refresh device token.
- **POST**: `/devices/logout` : `DeviceAuthApiController@logout` — revoke device token.
- **POST**: `/devices/create-order` : `DeviceOrderApiController` (single-action) — Request: `StoreDeviceOrderRequest` (see schema below).
- **POST**: `/devices/order/current` : `DeviceOrderManagementApiController@getCurrentOrder` — Request body: `{ sessionId: int }` (validated inline).
- **POST**: `/devices/check-update` : `DeviceOrderManagementApiController@checkOrderUpdate` — Request body: `{ orderId, sessionId }` (inline parameters, optional behaviour documented in controller).
- **GET**: `/tables/services` : `TableServiceApiController@index` — list of services for tables.
- **POST**: `/service/request` : `ServiceRequestApiController@store` — Request: `StoreServiceRequest` (see schema below). Broadcasts `ServiceRequestNotification`.
- **GET**: `/session/latest` : `TerminalSessionApiController@getLatestSession`
- **POST**: `/devices/heartbeat` : Inline closure — updates device last seen in cache. Body: `deviceID`, `timestamp`.

**Printer API Group (middleware: `auth:device`)** — For Flutter Printer App
- **POST**: `/orders/{orderId}/printed` : `PrinterApiController@markPrinted` — Mark single order as printed (idempotent). Requires Bearer token (`auth:device`). Response includes `printed_at` and `printed_by`.
- **GET**: `/orders/unprinted` : `PrinterApiController@getUnprintedOrders` — Polling fallback to fetch unprinted orders. Query: `session_id` (required), `since` (optional), `limit` (optional). Returns orders with `is_printed == 0` and excludes `CANCELLED`/`VOIDED` statuses.
- **POST**: `/orders/printed/bulk` : `PrinterApiController@markPrintedBulk` — Bulk mark orders as printed (optional). Response returns `{ updated, already_printed, not_found }`.
- **POST**: `/printer/heartbeat` : `PrinterApiController@heartbeat` — Track active printer devices (optional). Heartbeat cached for 2 minutes; response includes `session_active`.

**Admin-only (web) device token generation**
- **POST**: `/devices/{device}/token` : Admin-only web route, creates a personal access token for the specified `Device`. Returns JSON with `token` and `expires_at` when called via AJAX. Tokens are labeled `admin-issued` and default to 1 year expiry. Use this for pre-provisioning devices with static IPs.

- **Note:** There is a legacy route `POST /order/{orderId}/printed` used by the admin web dashboard — retain for compatibility but endorse plural `orders/*` for printer apps.

**FormRequest Schemas (exact validation rules)**
- **`DeviceRegisterRequest`** (`app/Http/Requests/DeviceRegisterRequest.php`)
  - `name`: required, string, max:255
  - `code`: required, string, must exist in `device_registration_codes.code`
  - `app_version`: nullable, string, max:255

- **`StoreDeviceOrderRequest`** (`app/Http/Requests/StoreDeviceOrderRequest.php`)
  - `guest_count`: required, integer, min:1
  - `subtotal`: required, numeric, min:0
  - `tax`: required, numeric, min:0
  - `discount`: required, numeric, min:0
  - `total_amount`: required, numeric, min:0
  - `items`: required, array
  - `items.*.menu_id`: required, integer
  - `items.*.ordered_menu_id`: nullable, integer
  - `items.*.name`: required, string
  - `items.*.quantity`: required, integer, min:1
  - `items.*.price`: required, numeric, min:0
  - `items.*.note`: nullable, string
  - `items.*.subtotal`: required, numeric, min:0
  - `items.*.tax`: nullable, numeric, min:0
  - `items.*.discount`: nullable, numeric, min:0

- **`StoreServiceRequest`** (`app/Http/Requests/StoreServiceRequest.php`)
  - `table_service_id`: required, integer
  - `order_id`: required, integer, must exist in `device_orders,order_id`

**Other Request Objects / Query Validators**
- **`FilterMenuRequest`** (`app/Http/Requests/FilterMenuRequest.php`) — useful for menu filtering endpoints (not directly wired in `routes/api.php`, but available):
  - `menu_category_id`: nullable, integer
  - `menu_course_type_id`: nullable, integer
  - `menu_group_id`: nullable, integer
  - `search`: nullable, string, max:255

- **`ReportQueryRequest`** (`app/Http/Requests/ReportQueryRequest.php`) — generic pagination/filter query schema used by reporting endpoints:
  - `page`: nullable, integer, min:1
  - `per_page`: nullable, integer, min:1, max:500
  - `sort_by`: nullable, string, max:64
  - `sort_dir`: nullable, in: `asc,desc`
  - `q`: nullable, string, max:255
  - `filters`: nullable, array
  - `filters.*`: nullable, string, max:255

- **`LoginRequest`** (`app/Http/Requests/Auth/LoginRequest.php`)
  - `email`: required, string, email
  - `password`: required, string

- **`ProfileUpdateRequest`** (`app/Http/Requests/Settings/ProfileUpdateRequest.php`)
  - `name`: required, string, max:255
  - `email`: required, string, lowercase, email, max:255, unique:users,email (ignores current user)

**Notes & Next Steps**
- **Auth groups**: Endpoints under `auth:device` require a valid device personal access token (see `/token/verify`).
- **Broadcasting**: `POST /service/request` broadcasts a `ServiceRequestNotification` event — ensure `BROADCAST_CONNECTION` is set to `reverb` (or intended driver) at runtime and config cache is cleared for Reverb to receive events.
- **Missing / Inline validations**: Several `BrowseMenuApiController` methods use inline `$request->validate()` calls — their parameter requirements are noted near each route above.
- If you want, I can:
  - Expand each controller's doc into its own detailed markdown with example request/response payloads (I have already created `docs/api/*.md` for several controllers).
  - Extract response resource field lists (e.g., `MenuResource`, `MenuModifierResource`, `DeviceOrderResource`) and include sample JSON for each endpoint.

**Admin / Printer Manuals**

- **Admin manual**: [docs/admin_manual.md](docs/admin_manual.md) — quick guide for administrators: device management, token generation, and troubleshooting.
- **Printer manual**: [docs/printer_manual.md](docs/printer_manual.md) — instructions and smoke-test commands for the print team (temporary guest-access routes noted).

Generated from `routes/api.php` and the FormRequest classes in `app/Http/Requests`.

**Sample Responses (from Resource classes)**

- **`DeviceResource`** (single item)

```
{
  "id": 1,
  "device_uuid": "e7a1f8d4-...",
  "branch": "Main Branch",
  "name": "Device 01",
  "table": "Table 5"
}
```

- **`DeviceOrderResource`** (trimmed example)

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
  "table": { "id": 5, "name": "Table 5" },
  "device": { "id": 1, "device_uuid": "...", "branch": "Main", "name": "Device 01", "table": "Table 5" },
  "created_at": "2025-11-28T12:34:56Z"
}
```

- **`MenuResource`** (single menu)

```
{
  "id": 46,
  "group": "BEEF",
  "category": "Mains",
  "course": "Main Course",
  "name": "Beef Rendang",
  "kitchen_name": "Rendang",
  "receipt_name": "BEEF REND",
  "price": "12.00",
  "is_taxable": true,
  "is_available": true,
  "is_discountable": true,
  "img_url": "/images/menus/46.jpg",
  "tax": null,
  "tax_amount": 0.00,
  "modifiers": []
}
```

- **`MenuModifierResource`** (single modifier)

```
{
  "id": 201,
  "menu_group_id": 46,
  "group": "BEEF",
  "name": "P1",
  "category": "Proteins",
  "kitchen_name": "Pork Slice",
  "receipt_name": "PORK S1",
  "price": "2.00",
  "description": "Extra pork",
  "is_available": true,
  "is_modifier": 1,
  "is_modifier_only": 0,
  "img_url": "/images/modifiers/201.jpg"
}
```

- **`MenuCategoryResource`**

```
{
  "id": 3,
  "name": "Beverages"
}
```

- **`TaxResource`**

```
{
  "name": "GST",
  "percentage": 7.00,
  "rounding": "nearest"
}
```

- **`MenuGroupResource`**

```
{
  "id": 36,
  "name": "BEEF"
}
```

- **`MenuCourseTypeResource`**

```
{
  "id": 2,
  "name": "Main Course"
}
```

**Examples: curl & TypeScript snippets**

- Register device (public API)

curl:

```bash
curl -X POST "http://localhost/api/devices/register" \
  -H "Content-Type: application/json" \
  -d '{ "name": "Device 01", "code": "REGCODE123", "app_version": "1.0.0" }'
```

TypeScript (fetch):

```ts
await fetch('/api/devices/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ name: 'Device 01', code: 'REGCODE123', app_version: '1.0.0' })
});
```

- Create device order (authenticated)

curl:

```bash
curl -X POST "http://localhost/api/devices/create-order" \
  -H "Authorization: Bearer <device-token>" \
  -H "Content-Type: application/json" \
  -d '{ "guest_count":2, "subtotal":18, "tax":1.8, "discount":0, "total_amount":19.8, "items":[{"menu_id":46,"name":"Pork Belly","quantity":1,"price":9.0,"subtotal":9.0}] }'
```

TypeScript (fetch):

```ts
await fetch('/api/devices/create-order', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
  body: JSON.stringify(orderPayload)
});
```

- Service request (authenticated) — broadcasts a notification

curl:

```bash
curl -X POST "http://localhost/api/service/request" \
  -H "Authorization: Bearer <device-token>" \
  -H "Content-Type: application/json" \
  -d '{ "table_service_id":2, "order_id":1001 }'
```

TypeScript (fetch):

```ts
const res = await fetch('/api/service/request', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
  body: JSON.stringify({ table_service_id: 2, order_id: 1001 })
});
const json = await res.json();
```

- Get menus (public)

curl:

```bash
curl "http://localhost/api/menus"
```

TypeScript (fetch):

```ts
const menus = await fetch('/api/menus').then(r => r.json());
```
