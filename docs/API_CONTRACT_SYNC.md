# Woosoo Nexus - API Contract & Cross-App Sync Reference

**Generated:** 2026-05-10  
**Purpose:** Track API endpoint usage across all three applications to ensure sync.

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Implemented & In Sync |
| ⚠️ | Endpoint Exists But Consumer Unclear |
| ❌ | Endpoint Missing or Mismatch |
| 🔄 | WebSocket/Real-time |

---

## 1. Device Authentication & Management

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/devices/login` | POST | `api.php:122` | ✅ | - | Device authentication |
| `/api/devices/register` | POST | `api.php:131` | ✅ | ✅ | Device registration |
| `/api/devices/refresh` | POST | `api.php:188` | ✅ | - | Token refresh |
| `/api/devices/logout` | POST | `api.php:189` | ✅ | - | Device logout |
| `/api/token/verify` | GET | `api.php:150` | ✅ | - | Token validation |
| `/api/device/lookup-by-ip` | GET | `api.php:125` | - | ✅ | IP-based device lookup |
| `/api/config` | GET | `api.php:94` | ✅ | ✅ | Broadcasting config |
| `/api/deployment-info` | GET | `api.php:101` | - | - | Deployment metadata |
| `/api/device/ip` | GET | `api.php:45` | - | - | IP detection utility |

---

## 2. Session Management

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/sessions/current` | GET | `api.php:206` | - | - | Current session |
| `/api/sessions/join` | POST | `api.php:207` | ✅ | - | Join session |
| `/api/devices/latest-session` | GET | `api.php:208` | - | ✅ | Latest session lookup |
| `/api/session/latest` | GET | `api.php:257` | ✅ | - | **Alias** to `sessions/current` |
| `/api/sessions/{id}/reset` | POST | `api.php:228` | - | - | Admin session reset |

**Note:** PWA calls `/api/session/latest` which Nexus routes to the same controller as `/api/sessions/current`.

---

## 3. Order Lifecycle (Critical Path)

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/devices/create-order` | POST | `api.php:191` | ✅ | - | **Create order** (throttled) |
| `/api/device-orders` | GET | `api.php:197` | ✅ | - | List device orders |
| `/api/device-order/{order}` | GET | `api.php:196` | ✅ | - | Get specific order |
| `/api/device-order/by-order-id/{id}` | GET | `api.php:199` | ✅ | - | Lookup by external ID |
| `/api/order/{orderId}/refill` | POST | `api.php:202` | ✅ | - | **Refill order** |
| `/api/order/{orderId}/print-refill` | POST | `api.php:203` | - | - | Refill alias |
| `/api/order/{orderId}/printed` | POST | `api.php:214` | - | - | Mark as printed |
| `/api/order/{orderId}/print` | GET | `api.php:215` | - | - | Trigger print |
| `/api/order/{orderId}/dispatch` | GET | `api.php:105` | - | - | Dispatch print event |

**Sync Status:** ✅ All order endpoints are synchronized. PWA uses `create-order` and `refill` with idempotency keys.

---

## 4. Menu & Content (V1)

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/menus` | GET | `api.php:135` | ✅ | - | List menus |
| `/api/menus/with-modifiers` | GET | `api.php:136` | ⚠️ | - | With modifiers |
| `/api/menus/modifier-groups` | GET | `api.php:137` | - | - | Modifier groups |
| `/api/menus/modifiers` | GET | `api.php:138` | ✅ | - | All modifiers |
| `/api/menus/modifier-groups/{id}/modifiers` | GET | `api.php:139` | - | - | Modifiers by group |
| `/api/menus/course` | GET | `api.php:140` | - | - | By course |
| `/api/menus/group` | GET | `api.php:141` | - | - | By group |
| `/api/menus/group-raw` | GET | `api.php:142` | - | - | Raw groups |
| `/api/menus/modifiers-by-group` | GET | `api.php:143` | - | - | Grouped modifiers |
| `/api/menus/package-modifiers` | GET | `api.php:144` | - | - | Package modifiers |
| `/api/menus/category` | GET | `api.php:145` | - | - | By category |
| `/api/menus/bundle` | GET | `api.php:146` | - | - | Full bundle |

---

## 5. Tablet-Optimized Menu API (V2)

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/v2/tablet/packages` | GET | `api.php:233` | ✅ | - | Tablet packages |
| `/api/v2/tablet/packages/{id}` | GET | `api.php:234` | ✅ | - | Package details |
| `/api/v2/tablet/meat-categories` | GET | `api.php:235` | - | - | Meat categories |
| `/api/v2/tablet/categories` | GET | `api.php:236` | ✅ | - | All categories |
| `/api/v2/tablet/categories/{slug}/menus` | GET | `api.php:237` | ✅ | - | Category menus |

**Sync Status:** ✅ V2 tablet endpoints used by PWA for menu browsing.

---

## 6. Table & Service

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/tables/services` | GET | `api.php:193` | - | - | Table services |
| `/api/service/request` | POST | `api.php:194` | - | - | Service request |
| `/api/device/table` | GET/POST | `api.php:186` | ✅ | - | Device's table |

---

## 7. Printer Integration (Print-Bridge Only)

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/printer/unprinted-events` | GET | `api_printer_routes.php` | - | ✅ | Poll print jobs |
| `/api/printer/print-events/{id}/ack` | POST | `api_printer_routes.php` | - | ✅ | Acknowledge print |
| `/api/printer/print-events/{id}/failed` | POST | `api_printer_routes.php` | - | ✅ | Mark failed |
| `/api/printer/heartbeat` | POST | `api_printer_routes.php` | - | ✅ | Device heartbeat |

**Print Service File:** `routes/api_printer_routes.php`

**Sync Status:** ✅ Print-bridge properly implements full printer lifecycle.

---

## 8. Device Management API (V2 - Admin)

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/v2/devices` | GET | `api.php:242` | - | - | List devices |
| `/api/v2/devices` | POST | `api.php:243` | - | - | Create device |
| `/api/v2/devices/metadata` | GET | `api.php:244` | - | - | Branch metadata |
| `/api/v2/devices/statistics` | GET | `api.php:245` | - | - | Device stats |
| `/api/v2/devices/by-status` | GET | `api.php:246` | - | - | Online/offline |
| `/api/v2/devices/{device}` | GET | `api.php:247` | - | - | Show device |
| `/api/v2/devices/{device}/health` | GET | `api.php:248` | - | - | Health check |
| `/api/v2/devices/{device}/heartbeats` | GET | `api.php:249` | - | - | Heartbeat history |
| `/api/v2/devices/{device}/status` | POST | `api.php:250` | - | - | Toggle status |
| `/api/v2/devices/{device}/security-code` | POST | `api.php:251` | - | - | Regenerate code |
| `/api/v2/devices/{device}/rotate-security-code` | PATCH | `api.php:252` | - | - | Rotate code |

**Controller:** `DeviceV2ApiController.php` - Admin sanctum auth required.

---

## 9. Legacy V1 Device Endpoints

| Endpoint | Method | Nexus Route | PWA | Print-Bridge | Notes |
|----------|--------|-------------|-----|--------------|-------|
| `/api/v1/orders` | GET | `api.php:220` | - | - | List orders |
| `/api/v1/orders/{order}` | GET | `api.php:221` | - | - | Show order |
| `/api/v1/orders/{order}/status` | PATCH | `api.php:222` | - | - | Update status |
| `/api/v1/orders/status/bulk` | POST | `api.php:223` | - | - | Bulk update |

**Note:** V1 endpoints under prefix. Used by legacy integrations.

---

## 10. WebSocket / Broadcasting

| Channel | Event | PWA | Print-Bridge | Notes |
|---------|-------|-----|--------------|-------|
| `admin.print` | `order.printed` | - | ✅ | Print events |
| `orders.{id}` | `OrderStatusUpdated` | ✅ | - | Order updates |
| `table.{id}` | `TableStatusUpdated` | ✅ | - | Table updates |

**PWA Echo Config:** `plugins/echo.client.ts`  
**Print-Bridge WS:** `services/reverb_service.dart:157`

---

## Sync Summary by App

### Tablet Ordering PWA
- **Total Endpoints Used:** ~15
- **All Critical Paths:** ✅ In Sync
- **Key Files:**
  - `config/api.ts` - Endpoint definitions
  - `composables/useDeviceAuth.ts` - Auth calls
  - `stores/Order.ts` - Order lifecycle
  - `stores/Session.ts` - Session management
  - `stores/Menu.ts` - Menu fetching

### Woosoo Print Bridge
- **Total Endpoints Used:** ~8
- **All Critical Paths:** ✅ In Sync
- **Key Files:**
  - `services/api_service.dart` - HTTP client
  - `services/reverb_service.dart` - WebSocket
  - `state/app_controller.dart` - Business logic

### Woosoo Nexus (Backend)
- **Total Endpoints Defined:** ~45
- **Route File:** `routes/api.php`
- **Printer Routes:** `routes/api_printer_routes.php`

---

## Critical Integration Points

### Order Creation Flow
```
PWA → POST /api/devices/create-order (idempotent)
    → Nexus → DeviceOrderApiController
    → Broadcast OrderStatusUpdated
    → Print-Bridge receives via WebSocket admin.print
    → Print-Bridge ACKs via POST /api/printer/print-events/{id}/ack
```

### Session Sync
```
PWA → GET /api/session/latest
    → SessionApiController::current()
    → Returns session + server_time for clock sync
```

### Device Registration
```
PWA/Print-Bridge → POST /api/devices/register
    → DeviceAuthApiController::register()
    → Returns device + token + security_code
```

---

## Discrepancies / Action Items

| # | Issue | Severity | Action |
|---|-------|----------|--------|
| 1 | `/api/menus/with-modifiers` usage unclear | Low | Verify if PWA uses this or `menus/bundle` |
| 2 | V1 device endpoints may be deprecated | Low | Check if any consumers still use `/api/v1/*` |
| 3 | Print-bridge doesn't use session-scoped polling | Info | Confirmed intentional for printer-relay mode |

---

## Testing Recommendations

1. **Contract Tests:** Verify all endpoints in this document respond correctly
2. **Idempotency Tests:** Ensure `X-Idempotency-Key` works on order endpoints
3. **Auth Flow Tests:** Verify token lifecycle (create → verify → refresh → logout)
4. **Print Loop Tests:** Verify full print lifecycle (event → print → ack)

---

**Document Owner:** Backend/API Team  
**Review Schedule:** Monthly or after any endpoint changes
