# Woosoo Nexus + Tablet Ordering PWA Long-Term Requirements

**Date:** 2026-05-09  
**Status:** Requirements Specification  
**Priority:** P0 - Foundation Requirements  
**Repos:** `woosoo-nexus`, `tablet-ordering-pwa`

---

## 1. Repository Boundaries

### 1.1 `woosoo-nexus` Ownership

- Laravel backend APIs
- Admin panel
- Device registration/authentication
- Session/order/refill validation
- POS/Krypton integration
- Reverb broadcasting
- Print/relay integration contracts
- Docker Compose orchestration
- Pi/on-prem deployment scripts
- Backend contract documentation

### 1.2 `tablet-ordering-pwa` Ownership

- Nuxt tablet client
- Customer-facing ordering UI
- Device kiosk flow
- PWA/service worker behavior
- Tablet-side Pinia stores
- Runtime API/Reverb client config
- Tablet-only UI tests
- Client contract tests

### 1.3 Forbidden Mixing (Hard Rules)

| Violation | Prevention |
|-----------|------------|
| Tablet UI inside Nexus | Code review check |
| Laravel backend in PWA | ESLint import restrictions |
| `woosoo-grillpad` references | Grep check in CI |
| Shared code copied blindly | Centralized package or API contract only |
| Backend/frontend in one commit | PR scope validation |

---

## 2. System Authority Rules

### 2.1 Backend Authority (Source of Truth)

| Domain | Backend Owns |
|--------|--------------|
| Identity | Device registration, table binding |
| Session | Validity, lifecycle |
| Business Logic | Package eligibility, refill eligibility |
| State | Order creation, order status |
| Enforcement | Idempotency, print state |
| Events | Realtime event truth |

### 2.2 PWA Authority (UI/UX Only)

| Domain | PWA Owns |
|--------|----------|
| UI State | Local cart draft, optimistic loading |
| UX | Retry UI, route presentation |
| Shell | PWA shell behavior, caching |

### 2.3 PWA Must NOT Own (Critical)

- Pricing
- Tax calculation
- Discount application
- Refill eligibility
- Package validity
- Order status authority
- Session status authority
- Table assignment authority
- Print status authority

---

## 3. Environment & Deployment Requirements

### 3.1 Current Problem

Multiple env sources causing drift:
```
.env.example
.env.docker.example
.env.docker
/etc/woosoo/woosoo.env
```

### 3.2 Long-Term Rule

**`compose.yaml` must require only:**
```
.env
```

**`.env.docker.example`** = profile/template file only.

### 3.3 Production Source of Truth Chain

```
/etc/woosoo/woosoo.env
    ↓ apply-woosoo-config.sh
    ↓ .env
    ↓ docker compose
```

### 3.4 Required Changes

#### Nexus Changes

- [ ] Remove `.env.docker` as required `env_file` from `compose.yaml`
- [ ] Ensure all services (app, queue, scheduler, reverb) use `.env` only
- [ ] Keep `.env.docker.example` as profile template
- [ ] Update CI: validate Compose using `.env.example → .env`
- [ ] Document `/etc/woosoo/woosoo.env` as production truth

### 3.5 Acceptance Criteria

```bash
cp .env.example .env
TABLET_DOCKERFILE=Dockerfile.prod docker compose config > /dev/null
# Must pass without error
```

**Nexus CI Pipeline:**
```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run lint:check
npm run typecheck
npm run build
docker compose config
./vendor/bin/pest
composer audit
npm audit
```

---

## 4. API Contract Requirements

### 4.1 Contract Ownership

**Nexus owns canonical contract:**
```
woosoo-nexus/docs/contracts/tablet-api.v1.yaml
```

**PWA consumes via:**
```
tablet-ordering-pwa/config/generatedEndpoints.ts
tablet-ordering-pwa/types/generated/tablet-api.ts
```

### 4.2 Required Tablet API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/config` | Runtime config |
| POST | `/api/devices/login` | Device auth |
| POST | `/api/devices/register` | Device registration |
| POST | `/api/devices/refresh` | Token refresh |
| GET | `/api/token/verify` | Token validation |
| GET | `/api/session/latest` | Session lookup |
| GET | `/api/sessions/current` | Current session |
| GET | `/api/v2/tablet/packages` | Available packages |
| GET | `/api/v2/tablet/packages/{id}` | Package details |
| GET | `/api/v2/tablet/meat-categories` | Menu categories |
| GET | `/api/v2/tablet/categories` | All categories |
| GET | `/api/v2/tablet/categories/{slug}/menus` | Category menus |
| POST | `/api/devices/create-order` | Create order |
| GET | `/api/device-order/by-order-id/{orderId}` | Order lookup |
| POST | `/api/order/{orderId}/refill` | Submit refill |
| POST | `/api/order/{orderId}/print-refill` | Print refill |
| POST | `/broadcasting/auth` | Reverb auth |

### 4.3 Stale Endpoint Cleanup (PWA)

**Remove or quarantine:**
```
/api/device/login        # Use /api/devices/login
/api/device/session      # Use /api/sessions/current
/api/menu/packages       # Use /api/v2/tablet/packages
/api/menu/modifiers      # Deprecated
```

### 4.4 Contract CI Requirements

**Nexus CI:**
- [ ] Validate `tablet-api.v1.yaml` syntax
- [ ] Validate all contract endpoints have routes
- [ ] Validate response fixtures match contract

**PWA CI:**
- [ ] Fail if referencing endpoint missing from contract
- [ ] Validate generated types match contract

---

## 5. Device Registration Requirements

### 5.1 Terminology (Critical)

**Code must use:**
```
security_code    # The 6-digit setup value
```

**UI may display:**
```
Setup Code
```

**Never call it:**
```
token    # Because Sanctum bearer is the actual auth token
```

### 5.2 Backend Requirements

- [ ] Accept `security_code` for device registration
- [ ] Treat `passcode` only as legacy compatibility
- [ ] Clear/invalidate setup code after successful registration
- [ ] Issue fresh Sanctum device token
- [ ] Delete/revoke old tokens during re-registration
- [ ] Return device + table data

### 5.3 PWA Requirements

- [ ] Validate exactly 6 numeric digits
- [ ] Send `security_code` (not `passcode`)
- [ ] Never persist setup code as reusable auth
- [ ] Persist only returned backend identity:
  - device ID, UUID, name
  - table ID, name
  - bearer token
- [ ] Handle invalid/expired/reused/conflict codes

### 5.4 Legacy Cleanup

**Delete/refactor:**
```
composables/useDeviceAuth.ts
```

**Device registration must happen only through:**
```
stores/Device.ts
```

---

## 6. Realtime / Reverb Broadcast Requirements

### 6.1 Current Problem

Backend uses public channels. PWA subscribes via `Echo.channel()`.
Authorization rules exist but aren't protecting sensitive streams.

### 6.2 Long-Term Rule

**All sensitive tablet realtime channels must be private.**

### 6.3 Backend: Private Channels Required

```php
new PrivateChannel("device.{$order->device_id}");
new PrivateChannel("orders.{$order->order_id}");
new PrivateChannel("session.{$order->session_id}");
new PrivateChannel("admin.orders");
new PrivateChannel("admin.service-requests");
new PrivateChannel("admin.print");
```

### 6.4 PWA: Private Subscription Required

```typescript
// CORRECT
Echo.private(`device.${deviceId}`)
Echo.private(`orders.${orderId}`)
Echo.private(`session.${sessionId}`)

// WRONG
Echo.channel(`device.${deviceId}`)
```

### 6.5 Channel Authorization Rules (Nexus)

```php
Broadcast::channel('device.{deviceId}', function (Device $device, int $deviceId) {
    return (int) $device->id === (int) $deviceId;
});

Broadcast::channel('orders.{orderId}', function (Device $device, int $orderId) {
    return $device->orders()->where('order_id', $orderId)->exists();
});

Broadcast::channel('session.{sessionId}', function (Device $device, int $sessionId) {
    return $device->orders()->where('session_id', $sessionId)->exists();
});
```

### 6.6 Service Request Channel Normalization

**Choose one naming convention:**
```
service-requests.{orderId}
```
OR
```
service-requests.{deviceId}
```

Backend and PWA must match exactly.

### 6.7 Event Envelope (Required Fields)

```json
{
  "event": "order.status.updated",
  "order_id": 19583,
  "session_id": 55,
  "device_id": 7,
  "table_id": 3,
  "status": "completed",
  "occurred_at": "2026-05-05T12:00:00+08:00",
  "version": 1
}
```

### 6.8 PWA Event Validation (Before State Mutation)

Reject event if:
- [ ] `device_id` ≠ current device
- [ ] `table_id` ≠ current table
- [ ] `session_id` ≠ current session
- [ ] `order_id` ≠ current active order
- [ ] Event version is stale

### 6.9 Acceptance Criteria

- [ ] Tablet cannot subscribe to another tablet's device channel
- [ ] Tablet cannot subscribe to another order channel
- [ ] Stale session reset cannot wipe new session
- [ ] Terminal events are idempotent
- [ ] Polling + Reverb cannot trigger duplicate session end

---

## 7. Order Creation Requirements

### 7.1 Long-Term: Intent-Only Payload

**Tablet sends:**
```json
{
  "guest_count": 3,
  "package_id": 46,
  "items": [
    { "menu_id": 10, "quantity": 2 },
    { "menu_id": 13, "quantity": 2 }
  ]
}
```

**Tablet must NOT send (server calculates):**
```
subtotal
tax
discount
total_amount
frontend-decided pricing
frontend-decided refillability
frontend-decided package rules
```

### 7.2 Current Compatibility

If existing Nexus endpoint requires pricing fields:
- Keep compatibility short-term
- Plan migration to server-calculated totals
- Document migration timeline

### 7.3 Idempotency Requirements

**Every initial order mutation must include:**
```http
X-Idempotency-Key: <uuid>
```

**Backend enforces:**
- Same device
- Same table
- Same session
- Same idempotency key
- Same request hash

### 7.4 Same Key, Different Payload = Reject

If same idempotency key reused with different payload:
```
HTTP 409 Conflict
{
  "error": "Idempotency key already used with different payload"
}
```

### 7.5 Idempotency Table Schema

```sql
idempotency_records
- id
- device_id
- table_id
- session_id
- idempotency_key (indexed)
- request_hash
- response_body
- status_code
- created_order_id
- expires_at
- created_at
- updated_at
```

### 7.6 409 Conflict = Safe Resume Only

`409` response must include resumable `order` object.

**409 may ONLY mean:**
```
Safe active-order resume conflict
```

Other conflicts use different error codes.

---

## 8. Refill Requirements

### 8.1 Current Problem

Refill eligibility relies on string group names:
```
"Sides" renamed to "Side Dish" → breaks refill validation
```

### 8.2 Long-Term: DB-Backed Refill Rules

**Add explicit fields:**
```
available_for_refill
refill_group
refill_limit_per_round
refill_requires_initial_order
```

**Possible tables:**
```
menu_refill_rules
package_refill_rules
tablet_category_menus
```

### 8.3 Refill Request Payload

```json
{
  "items": [
    { "menu_id": 101, "quantity": 1 }
  ]
}
```

### 8.4 Backend Validation Required

- [ ] Device owns active order
- [ ] Order not completed/cancelled/voided
- [ ] Session still active
- [ ] Menu item is refillable
- [ ] Package allows refill item
- [ ] Quantity within limits
- [ ] Idempotency key valid
- [ ] Same key + different payload = rejected

### 8.5 PWA: UX Guard Only

PWA may hide non-refillable items for UX.
**But backend remains final authority.**

PWA must not expose full initial menu during refill mode.

### 8.6 Acceptance Criteria

- [ ] Meat refill only when backend allows
- [ ] Side refill only when backend allows
- [ ] Drinks/desserts rejected unless explicitly refillable
- [ ] Unknown menu IDs rejected
- [ ] Completed order cannot receive refill
- [ ] Duplicate submit cannot create duplicate refill orders

---

## 9. Offline / PWA Mutation Requirements

### 9.1 MVP Recommendation

**Do NOT queue order/refill writes offline.**

### 9.2 Reason

Hidden queued order can replay after:
- Table changed
- Session ended
- POS terminal session changed
- Token expired
- Customer left

**Visible failure is better than hidden bad replay.**

### 9.3 MVP: Allowed Offline Behavior

- [ ] PWA shell loads offline after install
- [ ] Static assets cached
- [ ] Images may be cached
- [ ] Previously loaded menu may display read-only

### 9.4 MVP: NOT Allowed

- [ ] Background sync order creation
- [ ] Background sync refill creation
- [ ] Hidden replay of failed order writes

### 9.5 Required Submit Failure UX

If network fails during submit:
- [ ] Preserve the cart
- [ ] Preserve same idempotency key (while session active)
- [ ] Show clear retry UI
- [ ] Tell staff if connection does not recover
- [ ] Do NOT silently queue in service worker

### 9.6 Future Offline Mutation (Post-MVP)

Only support if backend has:
- [ ] Persisted idempotency table
- [ ] Request hash validation
- [ ] Session/table validation at replay time
- [ ] Expired-token handling
- [ ] Stale queued order rejection
- [ ] UI reconciliation channel

---

## 10. Tablet Flow State Machine

### 10.1 Current Problem

Multiple flags across stores:
```
hasPlacedOrder
isRefillMode
isSubmitting
isPolling
terminalHandled
session_active
```

### 10.2 Long-Term: Explicit Phase Machine

**Canonical Tablet Phases:**
```typescript
type TabletPhase =
  | 'unregistered'
  | 'registered_no_table'
  | 'ready'
  | 'guest_count'
  | 'package_selection'
  | 'menu_selection'
  | 'review'
  | 'submitting'
  | 'in_session'
  | 'refill'
  | 'ending'
```

### 10.3 Phase → Route Mapping

| Phase | Route |
|-------|-------|
| `unregistered` | `/settings` or registration screen |
| `registered_no_table` | waiting-for-table screen |
| `ready` | `/order/start` |
| `guest_count` | guest counter |
| `package_selection` | `/order/packageSelection` |
| `menu_selection` | `/menu` |
| `review` | `/order/review` |
| `submitting` | submit overlay |
| `in_session` | `/order/in-session` |
| `refill` | `/menu` or refill view |
| `ending` | `/order/session-ended` |

### 10.4 Store Responsibility Split

| Store | Responsibility |
|-------|----------------|
| `stores/Device.ts` | Device/table/token identity only |
| `stores/Session.ts` | Session id/timer only |
| `stores/Order.ts` | Cart/order payload/status only |
| `stores/Flow.ts` | Route phase + allowed transitions |

### 10.5 Supporting Composables

```
useSessionSync.ts
useSessionVisibilityRecovery.ts
useOrderPolling.ts
useTerminalEventHandler.ts
```

### 10.6 Acceptance Criteria

- [ ] Only one store owns route phase
- [ ] Terminal session cleanup runs once
- [ ] Cannot navigate to refill before initial order
- [ ] Cannot return to package selection after submission
- [ ] Refresh restores phase from backend where possible
- [ ] Local storage never overrides backend state

---

## 11. Security Requirements

### 11.1 Device Auth

- [ ] Registration requires valid `security_code`
- [ ] Setup code is one-time or expires
- [ ] Bearer token issued by backend
- [ ] Token refresh controlled by backend
- [ ] Failed auth returns PWA to re-registration

### 11.2 Broadcast Security

- [ ] Sensitive streams are private
- [ ] Channel auth validates device ownership
- [ ] Admin channels are admin-only
- [ ] Event payloads contain IDs for stale rejection

### 11.3 Settings PIN

Local settings PIN is **kiosk convenience lock only**.
Not real security.

**Long-term replacement:**
```
Backend-issued staff unlock PIN
```
OR
```
Admin/device-management unlock flow
```

### 11.4 Public Runtime Config (Allowed)

```
API base URL
Reverb host, port, scheme
Build SHA, build time
```

### 11.5 Protected Secrets (Never Expose)

```
Reverb secret
Laravel app key
DB credentials
POS credentials
Admin tokens
```

---

## 12. Print / Relay Requirements

### 12.1 Dispatch Guard (Critical)

**Nexus must add null guard:**
```php
$order = DeviceOrder::where(['order_id' => $orderId])->first();

if (! $order) {
    return response()->json([
        'success' => false,
        'message' => 'Order not found',
    ], 404);
}

PrintOrder::dispatch($order);
```

### 12.2 Print Event Schema

```
print_event_id
order_id
device_id
table_id
status
attempt_count
last_error
created_at
updated_at
```

### 12.3 Retry Requirements

- [ ] Failed print events are retryable
- [ ] Retry is idempotent
- [ ] Admin UI shows print status
- [ ] Tablet may show "order received" separately from "printed"

---

## 13. CI Requirements

### 13.1 Nexus CI Pipeline

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
npm ci
npm run lint:check
npm run typecheck
npm run build
docker compose config > /dev/null
./vendor/bin/pest
composer audit
npm audit
```

### 13.2 PWA CI Pipeline

```bash
npm ci
npm run lint
npm run typecheck
npm run test:run
npm run build
npm run generate
```

**Future:**
```bash
npx playwright test
```

### 13.3 Cross-Repo Contract CI

**Nexus:**
- Validate `tablet-api.v1.yaml` syntax
- Validate route existence
- Validate response fixtures

**PWA:**
- Validate no stale endpoints
- Validate generated types
- Validate event envelope handling
- Validate private channel subscription

---

## 14. Documentation Requirements

### 14.1 Required Nexus Docs

```
docs/contracts/tablet-api.v1.yaml
docs/contracts/tablet-events.v1.md
docs/deployment/production-docker.md
docs/deployment/tablet-update-contract.md
docs/standards/environment-profiles.md
docs/standards/broadcast-security.md
docs/standards/idempotency.md
```

### 14.2 Required PWA Docs

```
docs/technical-review/API_AND_EVENT_CONTRACTS.md
docs/technical-review/PWA_OFFLINE_AND_TESTABILITY.md
docs/technical-review/WORKFLOWS.md
docs/technical-review/HANDOVER_PROTOCOL.md
docs/technical-review/CASE_FILE.md
```

### 14.3 Documentation Status Labels

**Proposed architecture:**
```
Status: Proposed
```

**Implemented behavior:**
```
Status: Implemented
```

---

## 15. Implementation Roadmap

### Phase 0: Unblock Nexus Staging

**Repo:** `woosoo-nexus`  
**Branch:** `fix/nexus-compose-ci`

**Tasks:**
1. Remove `.env.docker` as required Compose env file
2. Ensure `docker compose config` passes with `.env`
3. Add print dispatch null guard
4. Rerun full CI
5. Ensure Pest tests actually run

**Acceptance:**
- Nexus CI green
- No skipped PHP tests
- PR #92 not merged until this is fixed

---

### Phase 1: Private Broadcast Contract

**Repos:** Both  
**Branch:** `feat/private-tablet-broadcasts`

**Nexus Tasks:**
1. Convert sensitive broadcasts to `PrivateChannel`
2. Add/repair `Broadcast::channel()` rules
3. Normalize service request channel naming
4. Add event envelope IDs
5. Add channel authorization tests

**PWA Tasks:**
1. Replace `Echo.channel()` with `Echo.private()`
2. Add stale-event rejection
3. Add tests for private subscription names
4. Add terminal-event idempotency tests

**Acceptance:**
- Unauthorized device cannot subscribe to other device/order
- Stale reset cannot wipe current session
- Terminal event applied once

---

### Phase 2: Contract-First API

**Repos:** Both

**Tasks:**
1. Add canonical `tablet-api.v1` contract in Nexus
2. Generate or centralize PWA endpoints from contract
3. Delete stale endpoint constants
4. Delete/refactor `useDeviceAuth.ts`
5. Add contract CI checks

**Acceptance:**
- No unknown PWA endpoints
- No undocumented Nexus tablet endpoints
- Response fixtures match PWA parsers

---

### Phase 3: Offline Mutation Simplification

**Repo:** `tablet-ordering-pwa`

**Tasks:**
1. Remove service worker Background Sync for order/refill writes
2. Keep shell/static/image caching
3. Add visible retry UI
4. Preserve cart on failed submit
5. Reuse idempotency key for same submit attempt

**Acceptance:**
- Offline order write does not silently queue
- Failed submit is visible
- Retry cannot create duplicates
- Session reset clears pending submit key

---

### Phase 4: Explicit Flow State Machine

**Repo:** `tablet-ordering-pwa`

**Tasks:**
1. Create `stores/Flow.ts`
2. Move route authority into flow store
3. Split session sync/recovery into composables
4. Convert route guards to phase-based checks
5. Add tests for every allowed transition

**Acceptance:**
- One canonical tablet phase
- No contradictory navigation flags
- Terminal cleanup runs once
- Refresh restores correct phase safely

---

### Phase 5: Refill Policy Hardening

**Repo:** `woosoo-nexus`

**Tasks:**
1. Add DB-backed refill rules
2. Stop relying on string menu group names
3. Return explicit refill flags in menu endpoints
4. Validate refill request against rules
5. Add tests for allowed/rejected refill items

**Acceptance:**
- Menu renames do not break refill rules
- Package-specific refill policies supported
- Backend rejects non-refillable items even if PWA sends them

---

## 16. Priority Matrix

| Priority | Repo | Requirement |
|----------|------|-------------|
| **P0** | `woosoo-nexus` | Fix Compose env model and CI |
| **P0** | `woosoo-nexus` | Ensure PHP tests run |
| **P0** | `woosoo-nexus` | Add print dispatch null guard |
| **P0/P1** | Both | Convert broadcasts to private channels |
| **P1** | Both | Contract-first API |
| **P1** | `tablet-ordering-pwa` | Remove orphan `useDeviceAuth.ts` |
| **P1** | `tablet-ordering-pwa` | Remove offline mutation replay for MVP |
| **P2** | `tablet-ordering-pwa` | Add explicit flow state machine |
| **P2** | `woosoo-nexus` | Add DB-backed refill rules |
| **P2** | `woosoo-nexus` | Split `routes/api.php` by domain |

---

## 17. Final Long-Term Standards

1. **Nexus owns backend truth.**
2. **PWA consumes typed contracts.**
3. **`.env` is the only Compose runtime env file.**
4. **`/etc/woosoo/woosoo.env` is production source of truth.**
5. **Device/order/session broadcasts are private.**
6. **Channel authorization is tested.**
7. **Write endpoints use idempotency and request hashes.**
8. **Offline mutation replay is disabled for MVP.**
9. **Refill eligibility is DB-policy driven.**
10. **PWA flow is driven by one explicit phase state.**
11. **Legacy constants and orphan composables are deleted.**
12. **Docs must label proposed vs implemented behavior.**
13. **Backend and frontend PRs are separate.**
14. **No repo-blender commits.**

---

## 18. Immediate Next Action

**Start with Nexus-only fix branch:**
```
fix/nexus-compose-ci-and-print-guard
```

**Scope:**
1. Remove `.env.docker` requirement from `compose.yaml`
2. Update CI to validate Compose using `.env.example → .env`
3. Add null guard to print dispatch endpoint
4. Rerun full Nexus CI
5. Confirm Pest tests run

**Do not start PWA private-channel work until Nexus CI is green.**

---

**Document Owner:** Architecture Team  
**Last Updated:** 2026-05-09  
**Next Review:** After Phase 0 completion  
**Status:** Requirements Ready for Implementation
