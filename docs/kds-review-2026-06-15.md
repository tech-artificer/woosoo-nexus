# KDS Feature Review — 2026-06-15

**Scope:** Full audit of KDS features, API contracts, events, and broadcast pipeline.  
**Files examined:** `KdsController`, `OrderBroadcastPayload`, `BroadcastEvent`, `OrderBroadcaster`, all Order/Kds event classes, `DeviceOrderObserver`, `DeviceOrder`, `DeviceOrderItems`, `OrderStatus`, `useKdsBoard`, `useKdsEcho`, `kdsHelpers`, `kdsTypes`, `kdsApi`, `Display.vue`, `KdsTicketCard.vue`, `KdsCommandBar.vue`, `KdsFilterChips.vue`, `KdsEmptyState.vue`, `contracts/order-state.contract.md`, `docs/kds-designer-spec.md`, all KDS test files.

---

## Summary Table

| # | Severity | Area | Title |
|---|---|---|---|
| 1 | **Bug** | Backend | Double-broadcast on `advance()` and `recall()` |
| 2 | **Bug** | Backend | `toTicket()` unsafe POS lazy-load when POS is down and `table_id` is set |
| 3 | **Bug** | Contract | `voidReason` is always `null` — never populated from backend or broadcast |
| 4 | **Missing** | Frontend | `order.details.updated` not subscribed in `useKdsEcho` |
| 5 | **Missing** | Feature | Void action route and modal not implemented |
| 6 | **Missing** | Feature | Audio chime on `order.created` not implemented |
| 7 | **Missing** | Feature | Item `notes` silently dropped — broadcast sends it, KDS never renders it |
| 8 | **Missing** | Feature | Item `safety` flag never sourced — KDS type and template exist but data never set |
| 9 | **Code** | Backend | `toKdsState()` duplicated verbatim in `KdsController` and `OrderBroadcastPayload` |
| 10 | **Code** | Frontend | `counts.ready` hardcoded to `0` in `Display.vue` |
| 11 | **Code** | Frontend | "Online" badge in `KdsCommandBar` is static — not wired to Echo connection state |
| 12 | **Docs** | Contract | `contracts/websocket-events.contract.md` referenced in 5 files but does not exist |
| 13 | **Info** | Design | Pending order advance triggers 3 broadcasts (2 from observer + 1 from controller) |

---

## Finding 1 — Double-broadcast on `advance()` and `recall()`

**Severity: Bug**  
**Files:** `app/Http/Controllers/Admin/KdsController.php:108,124,141`, `app/Observers/DeviceOrderObserver.php:37-54`

### What happens

`KdsController::advance()` and `recall()` both call `app(OrderBroadcaster::class)->statusChanged($order)` explicitly after the DB transaction. However, `DeviceOrderObserver::updated()` also registers a `DB::afterCommit` callback that dispatches `OrderStatusUpdated`. Since the `save()` call inside the transaction fires model events (unlike `saveQuietly()`), the observer fires first (after commit), then the controller fires again.

**Execution order for `advance()` (CONFIRMED → IN_PROGRESS path):**
1. `DB::transaction()` opens.
2. `$locked->save()` (line 124) → observer registers `DB::afterCommit` callback.
3. Transaction commits.
4. `afterCommit` fires → `OrderStatusUpdated::dispatch()` → **Broadcast #1**.
5. Control returns from `DB::transaction()`.
6. `app(OrderBroadcaster::class)->statusChanged($order)` → **Broadcast #2**.

**Execution order for `advance()` (IN_PROGRESS → READY → SERVED path):**
- `saveQuietly()` for READY suppresses the observer (correct).
- `save()` for SERVED fires the observer → `DB::afterCommit` → **Broadcast #1**.
- Controller calls `statusChanged()` → **Broadcast #2**.

**Result:** Every advance and recall causes two `order.updated` events on `admin.orders`. Clients receive and process the same payload twice. This is idempotent on the frontend but wastes bandwidth and could cause visible double-renders on slow connections.

### Root cause

`OrderBroadcaster.php` comments note that the observer is an "existing dispatch site" not yet migrated. The KDS controller was added as a new consumer calling the broadcaster directly — but no one suppressed the observer path for these new controller-driven saves.

### Fix

For the saves inside `KdsController` transactions, use `saveQuietly()` (which suppresses model events/observer) and rely solely on the controller's explicit `statusChanged()` call. The controller broadcast is preferable because it can load relations and has full context.

```php
// In advance() - CONFIRMED → IN_PROGRESS case
$locked->saveQuietly();  // suppress observer; controller broadcasts below
$next = $nextStatus;

// In advance() - IN_PROGRESS → SERVED case
$locked->status = OrderStatus::READY;
$locked->saveQuietly();
$locked->status = OrderStatus::SERVED;
$locked->saveQuietly();  // change save() → saveQuietly() here
$next = OrderStatus::SERVED;

// In recall()
$fresh->status = OrderStatus::IN_PROGRESS;
$fresh->recalled = ($fresh->recalled ?? 0) + 1;
$fresh->saveQuietly();  // change save() → saveQuietly() here
```

---

## Finding 2 — `toTicket()` unsafe POS lazy-load when POS is down and `table_id` is set

**Severity: Bug**  
**File:** `app/Http/Controllers/Admin/KdsController.php:266`

### What happens

`KdsController::index()` conditionally skips loading POS-backed relations when POS is unreachable:

```php
$with = $this->posConnection->isReachable()
    ? ['device.table', 'table', 'items.menu']
    : ['device', 'items'];
```

When POS is down, `device.table`, `table`, and `items.menu` are NOT eager-loaded. `toTicket()` then accesses:

```php
$table = $order->device?->table ?? $order->table;
```

`$order->device?->table` triggers a lazy load of `device.table` (POS-backed). `$order->table` triggers a lazy load of `table` (also POS-backed via `App\Models\Krypton\Table`). If the order has a `table_id` set AND POS is down, this throws.

**`OrderBroadcastPayload::make()` handles this correctly** with a try/catch around the POS load. `KdsController::toTicket()` has no such protection.

### Proof

The connectivity test (`KdsControllerConnectivityTest`) only tests with `table_id => null`:
```php
\App\Models\DeviceOrder::factory()->confirmed()->create(['table_id' => null]);
```
With `table_id = null`, the BelongsTo FK is null so no query fires. The bug only surfaces when `table_id` is populated and POS is unreachable.

### Fix

Wrap the POS-backed relation access in `toTicket()` in the same try/catch pattern as `OrderBroadcastPayload`, or null-check before any lazy-load. The safest fix is to pass `$posIsReachable` as context to `toTicket()` and only access those relations when POS is up.

---

## Finding 3 — `voidReason` always `null` — never populated

**Severity: Bug (silent data loss)**  
**Files:** `app/Http/Controllers/Admin/KdsController.php:295`, `app/Helpers/OrderBroadcastPayload.php`, `resources/js/components/KDS/useKdsBoard.ts:69`, `resources/js/components/KDS/KdsTicketCard.vue:88`

### Chain of evidence

**`KdsController::toTicket()` (initial page load):**
```php
'voidReason' => null,  // always null, hardcoded
```

**`OrderBroadcastPayload::make()` (broadcast payload):**
The field `void_reason` (or `voidReason`) is entirely absent from the payload.

**`useKdsBoard.ts` `payloadToTicket()`:**
```ts
voidReason: payload.void_reason ?? undefined,
```
Reads `void_reason` from the broadcast payload — which never has it → always `undefined`.

**`KdsTicketCard.vue`:**
```html
<p v-if="ticket.state === 'voided' && ticket.voidReason" class="kds-void-reason">
  {{ ticket.voidReason }}
</p>
```
The void reason block is rendered correctly but always hidden because the data is never provided.

The mock data (`kdsMockData.ts`) includes `voidReason: 'FOH voided duplicate refill'` confirming the intent, but no real data path delivers it.

### Fix

1. Add `void_reason` to `OrderBroadcastPayload::make()` from the order model (requires a `void_reason` column or relation).
2. Add `'voidReason' => $order->void_reason ?? null` to `KdsController::toTicket()`.
3. Add the `name` field mapping in `payloadToTicket()` if the backend uses snake_case.

Note: This is also blocked on the void route not existing (see Finding 5). The void reason has no write path yet.

---

## Finding 4 — `order.details.updated` not subscribed in `useKdsEcho`

**Severity: Missing (spec gap)**  
**File:** `resources/js/components/KDS/useKdsEcho.ts`

The designer spec (§12) explicitly specifies:

> `order.details.updated` → Update `guest_count`, `subtotal`, `total` — treat as eventually consistent; show `—` until received

`OrderDetailsUpdated` event exists, fires on `admin.orders`, and is consumed by `OrderBroadcaster::detailsUpdated()`. But `useKdsEcho.ts` subscribes to:

```ts
channel
  .listen('.order.created', handleOrderEvent)
  .listen('.order.updated', handleOrderEvent)
  .listen('.order.voided', handleOrderEvent)
  .listen('.order.completed', handleOrderEvent)
  .listen('.order.cancelled', handleOrderEvent)
  .listen('.item.toggled', handleItemToggle)
```

`.order.details.updated` is missing. 

Currently this has no visible impact because the KDS ticket card does not render `guest_count`, `subtotal`, or `total`. But if those fields are ever added to the UI, the subscription needs to be wired first.

### Fix

Add `.listen('.order.details.updated', handleOrderEvent)` to the channel subscription. The existing `handleOrderEvent` → `applyOrderUpdate` pipeline will handle the payload correctly since it already reads all `OrderBroadcastPayload` fields.

---

## Finding 5 — Void action route and modal not implemented

**Severity: Missing (planned feature)**  
**File:** `routes/web.php`, designer spec §8

The designer spec (§8) describes a void button on every active ticket, a confirmation modal with reason selection, and a `POST /kds/orders/{id}/void` route.

**Current state:**
- No `/kds/orders/{id}/void` route in `routes/web.php`.
- No void button in `KdsTicketCard.vue`.
- No void confirmation modal component.
- `voidReason` has no write path (see also Finding 3).
- The existing POS void path is `POST /pos/orders/{orderId}/void` (via `PosController`).

The spec notes: "The existing POS void path is `POST /pos/orders/{orderId}/void`; the KDS route will expose the same action under the `/kds` prefix."

---

## Finding 6 — Audio chime on `order.created` not implemented

**Severity: Missing (planned feature)**  
**File:** `resources/js/components/KDS/useKdsEcho.ts`, designer spec §9

The designer spec (§9):
> **Trigger:** `order.created` broadcast → play a single short chime (~0.5 s). **Mute toggle** in the topbar.

`useKdsEcho.ts` subscribes to `.order.created` and routes it through `handleOrderEvent` → `applyOrderUpdate` (adds the ticket), but plays no sound. `KdsCommandBar.vue` has no mute toggle button.

---

## Finding 7 — Item `notes` silently dropped

**Severity: Missing**  
**Files:** `app/Helpers/OrderBroadcastPayload.php:69`, `resources/js/components/KDS/useKdsBoard.ts:62-68`, `resources/js/components/KDS/kdsTypes.ts`

`OrderBroadcastPayload::make()` includes `notes` per item:
```php
'notes' => $it->notes ?? null,
```

`payloadToTicket()` in `useKdsBoard.ts` maps items but drops `notes`:
```ts
items: (payload.items ?? []).map((it) => ({
  id: String(it.id),
  qty: it.quantity ?? 1,
  name: it.name ?? '',
  done: (it.done ?? false),
})),
```

`KdsItem` type does not include `notes`. `KdsTicketCard.vue` does not render notes. The designer spec (§4.2) lists notes as a required field: *"Note — Muted italic; only shown if present."*

---

## Finding 8 — Item `safety` flag never sourced from real data

**Severity: Missing (incomplete stub)**  
**Files:** `resources/js/components/KDS/kdsTypes.ts:11`, `resources/js/components/KDS/KdsTicketCard.vue:124-129`, `resources/js/components/KDS/kdsMockData.ts:22`

`KdsItem` interface declares `safety?: boolean`. `KdsTicketCard.vue` branches on it:
```html
<template v-if="item.safety">
  <span>{{ splitSafetyName(item.name).base }}</span>
  <span class="kds-safety"> - {{ splitSafetyName(item.name).modifier }}</span>
</template>
```

Neither `KdsController::toTicket()` nor `payloadToTicket()` in `useKdsBoard.ts` populates `safety`. It is only present in mock data (`{ name: 'Beef Bulgogi - no garlic', done: false, safety: true }`). The template branch and `splitSafetyName()` function are dead code in production.

The designer spec (§14) lists "Safety-mod pink styling for allergen items" as **out of scope for v1**, so this is an accepted incomplete feature. The dead code is not a bug but should be noted.

---

## Finding 9 — `toKdsState()` duplicated in two classes

**Severity: Code quality**  
**Files:** `app/Http/Controllers/Admin/KdsController.php:299-310`, `app/Helpers/OrderBroadcastPayload.php:77-88`

Both classes have an identical private `toKdsState(OrderStatus): string` method. The mappings are currently in sync. If one is changed without updating the other, the initial page load (controller path) and real-time updates (payload path) will diverge silently.

### Fix

Extract to a static method on `OrderStatus` enum or a shared helper. `OrderBroadcastPayload` is the natural home since it's already the canonical broadcast shape builder.

---

## Finding 10 — `counts.ready` hardcoded to `0`

**Severity: Code smell**  
**File:** `resources/js/pages/KDS/Display.vue:61`

```ts
const counts = computed<Record<KdsFilter, number>>(() => ({
  ...
  ready: 0,  // hardcoded
  ...
}))
```

`KdsFilter` type includes `'ready'` and `STAGE_SORT` includes `ready: 0`. The backend never emits `kds_state: 'ready'` (it maps both `in_progress` and `ready` → `'preparing'`), and `normalizeKdsState()` in `useKdsBoard.ts` converts any incoming `'ready'` to `'preparing'`. So zero is effectively correct. However, hardcoding it hides the intent and will mislead if `'ready'` is ever separated from `'preparing'` in a future iteration. Should be computed like the others.

---

## Finding 11 — "Online" badge not wired to Echo connection state

**Severity: Code smell / UX**  
**File:** `resources/js/components/KDS/KdsCommandBar.vue:46-49`

```html
<span class="kds-online">
  <Wifi aria-hidden="true" />
  Online
</span>
```

The badge is always "Online" regardless of WebSocket connectivity. If Reverb disconnects, kitchen staff see no indicator. The designer spec doesn't explicitly require a disconnect indicator in v1, but the "Online" badge actively misleads.

---

## Finding 12 — `contracts/websocket-events.contract.md` does not exist

**Severity: Documentation gap**  
**Referenced in:** `app/Broadcasting/BroadcastEvent.php`, `app/Broadcasting/OrderBroadcaster.php`, `app/Events/Order/OrderDetailsUpdated.php`, `app/Events/Order/OrderStatusUpdated.php`, `tests/Feature/Broadcasting/OrderStatusChannelTest.php`

Five files include `@see contracts/websocket-events.contract.md`. The file does not exist — only `contracts/order-state.contract.md` is present. This is the contract document for all broadcast event names, channels, and payload shapes.

---

## Finding 13 — PENDING advance triggers 3 broadcasts (informational)

**Severity: Informational (amplifies Finding 1)**  
**File:** `app/Http/Controllers/Admin/KdsController.php:67-74`

When `advance()` is called on a PENDING order:
1. Inner `DB::transaction` auto-advances PENDING → CONFIRMED; `save()` triggers observer → `DB::afterCommit` → **Broadcast #1** (CONFIRMED).
2. Main `DB::transaction` advances CONFIRMED → IN_PROGRESS; `save()` triggers observer → `DB::afterCommit` → **Broadcast #2** (IN_PROGRESS).
3. Controller calls `statusChanged()` explicitly → **Broadcast #3** (IN_PROGRESS).

The client receives a CONFIRMED broadcast, then two IN_PROGRESS broadcasts. This is harmless (idempotent) but indicates the design needs resolution — either suppress observer for controller-driven saves (see Finding 1 fix) or remove the explicit controller broadcast.

---

## API Contract Verification

### Routes (confirmed correct)

| Route | Method | Name | Controller |
|---|---|---|---|
| `/kds` | GET | `kds.display` | `KdsController::index` |
| `/kds/orders/{order}/advance` | POST | `kds.advance` | `KdsController::advance` |
| `/kds/items/{item}/toggle` | POST | `kds.toggle-item` | `KdsController::toggleItem` |
| `/kds/orders/{order}/recall` | POST | `kds.orders.recall` | `KdsController::recall` |

All four routes are registered under `middleware(['auth', 'can:admin'])`. Route names match `kdsApi.ts` calls. ✓

### HTTP Response Shapes

**`advance()` and `recall()` — confirmed correct:**
```json
{ "status": "in_progress", "order": { ...OrderBroadcastPayload }, "server_now": 1234567890 }
```

**`toggleItem()` — confirmed correct:**
```json
{ "item_id": 1, "order_id": 2, "done": true, "done_at": "...", "server_now": 1234567890 }
```

Frontend types in `kdsApi.ts` (`KdsActionResponse`, `KdsToggleResponse`) match these shapes. ✓

### Broadcast Event Names

| Event class | `broadcastAs()` | `BroadcastEvent` enum | `useKdsEcho` listener | Match? |
|---|---|---|---|---|
| `OrderCreated` | `order.created` | `OrderCreated` | `.order.created` | ✓ |
| `OrderStatusUpdated` | `order.updated` | `OrderUpdated` | `.order.updated` | ✓ |
| `OrderCompleted` | `order.completed` | `OrderCompleted` | `.order.completed` | ✓ |
| `OrderVoided` | `order.voided` | `OrderVoided` | `.order.voided` | ✓ |
| `OrderCancelled` | `order.cancelled` | `OrderCancelled` | `.order.cancelled` | ✓ |
| `OrderDetailsUpdated` | `order.details.updated` | `OrderDetailsUpdated` | **missing** | ✗ (Finding 4) |
| `ItemToggled` | `item.toggled` | `ItemToggled` | `.item.toggled` | ✓ |

### Broadcast Channels

| Event | Channels | KDS listens on |
|---|---|---|
| `OrderStatusUpdated` | `orders.{order_id}`, `device.{device_id}`, `admin.orders` | `admin.orders` ✓ |
| `OrderCreated` | `admin.orders`, `orders.{order_id}`, `device.{device_id}` | `admin.orders` ✓ |
| `OrderCompleted` | `orders.{order_id}`, `admin.orders` | `admin.orders` ✓ |
| `OrderVoided` | `orders.{order_id}`, `admin.orders` | `admin.orders` ✓ |
| `OrderCancelled` | `orders.{order_id}`, `admin.orders` | `admin.orders` ✓ |
| `OrderDetailsUpdated` | `orders.{order_id}`, `admin.orders` | not subscribed ✗ |
| `ItemToggled` | `admin.orders`, `orders.{order_id}`, `device.{device_id}` | `admin.orders` ✓ |

### Broadcast Payload Shapes

All order events (`OrderStatusUpdated`, `OrderCreated`, `OrderCompleted`, `OrderVoided`, `OrderCancelled`, `OrderDetailsUpdated`) wrap in `{ "order": OrderBroadcastPayload }`. ✓  
`ItemToggled` sends flat `{ item_id, order_id, done, done_at }`. ✓  
Frontend `handleOrderEvent` in `useKdsEcho.ts` correctly unwraps `e.order ?? e`. ✓  
Frontend `handleItemToggle` validates `item_id`, `order_id`, `done` presence before applying. ✓

### State Machine — KDS vs OrderStatus

| Backend `OrderStatus` | `toKdsState()` output | Frontend `KdsTicketState` | Shown on board? |
|---|---|---|---|
| `pending` | `new` | `new` | Yes |
| `confirmed` | `new` | `new` | Yes |
| `in_progress` | `preparing` | `preparing` | Yes |
| `ready` | `preparing` | `preparing` | Yes (normalized) |
| `served` | `served` | `served` | Yes |
| `voided` | `voided` | `voided` | Yes |
| `completed` | — | — | Removed (HIDDEN) |
| `cancelled` | — | — | Removed (HIDDEN) |
| `archived` | — | — | Removed (HIDDEN) |

HIDDEN_STATUSES in PHP and TS are in sync: `[completed, cancelled, archived]`. ✓

### State Transition Enforcement

- `advance()` CONFIRMED → IN_PROGRESS: via `nextStatus()` → `canTransitionTo()`. ✓
- `advance()` IN_PROGRESS → (READY →) SERVED: direct double-save; READY→SERVED is allowed. ✓
- `advance()` PENDING auto-promoted to CONFIRMED before main transition. ✓
- `recall()` SERVED → IN_PROGRESS: re-checks `canTransitionTo()` under lock. ✓
- `recall()` VOIDED rejected with specific message. ✓
- `recall()` cap of 5 enforced pre- and post-lock (TOCTOU protection). ✓
- Item toggle blocked on `TERMINAL_ITEM_STATUSES`: `[SERVED, COMPLETED, CANCELLED, VOIDED, ARCHIVED]`. ✓

### Optimistic Update Paths

- `advance()`: HTTP response `order` passed directly to `applyOrderUpdate()` → same code path as Echo. ✓
- `recall()`: Same pattern. ✓
- `toggleItem()`: HTTP response fields passed to `applyItemToggle()` → same shape as `ItemToggled` broadcast. ✓
- Clock offset (`server_now`) set on all three HTTP action responses. ✓

---

## Test Coverage Assessment

| Area | Tests | Status |
|---|---|---|
| KDS display access (auth) | `KdsDisplayTest` — guests, non-admin, admin | ✓ |
| KDS `serverNow` prop | `KdsDisplayTest` | ✓ |
| POS-down resilience (index) | `KdsControllerConnectivityTest` — 3 scenarios | Partial (only `table_id=null` tested — see Finding 2) |
| advance: confirmed→in_progress | `KdsControllerTest` | ✓ |
| advance: in_progress→served gate | `KdsControllerTest` | ✓ |
| advance: in_progress→served (all done) | `KdsControllerTest` | ✓ |
| advance: no intermediate ready persists | `KdsControllerTest` | ✓ |
| advance: concurrent state change | `KdsControllerTest` | ✓ |
| advance: full broadcast payload in response | `KdsControllerTest` | ✓ |
| advance: `server_now` in response | `KdsControllerTest` | ✓ |
| toggleItem: done/undone | `KdsControllerTest` | ✓ |
| toggleItem: terminal state guard | `KdsControllerTest` | ✓ |
| toggleItem: full response shape | `KdsControllerTest` | ✓ |
| recall: served→in_progress | `KdsControllerTest` | ✓ |
| recall: cap enforcement | `KdsControllerTest` | ✓ |
| recall: wrong state guards | `KdsControllerTest` | ✓ |
| recall: full broadcast payload | `KdsControllerTest` | ✓ |
| Observer dispatch correctness | `OrderRealtimeBroadcastTest` | ✓ |
| Broadcast payload shape | `OrderRealtimeBroadcastTest` | ✓ |
| POS-down payload resilience | `OrderBroadcastPosResilienceTest` | ✓ |
| Double-broadcast (advance/recall) | — | **Not tested** |
| `toTicket()` POS-down with `table_id` set | — | **Not tested** |
| `void_reason` in payload/ticket | — | **Not tested** |
| Audio chime | — | **Not tested** |

---

## Quick-win Fixes (no spec changes required)

1. **Finding 9** — Extract `toKdsState()` to a shared location. 2-line change.
2. **Finding 10** — Change `ready: 0` to a real computed count. 1-line change.
3. **Finding 4** — Add `.listen('.order.details.updated', handleOrderEvent)` in `useKdsEcho.ts`. 1-line change.
4. **Finding 12** — Create `contracts/websocket-events.contract.md`. Documentation only.

## Requires Backend Work

- **Finding 1** — Change `save()` to `saveQuietly()` in KDS controller transaction bodies.
- **Finding 2** — Add try/catch or null-guard to `toTicket()` for POS-backed relations.
- **Finding 3** — Add `void_reason` field to model, payload, and `toTicket()`.

## Requires Full Feature Work

- **Finding 5** — Void route, modal, and reason-persistence pipeline.
- **Finding 6** — Audio chime + mute toggle.
- **Finding 7** — Item notes: add `notes` to `KdsItem` type, `payloadToTicket()`, and `KdsTicketCard.vue`.
- **Finding 8** — Item safety: source the flag from menu metadata and wire to payload.
