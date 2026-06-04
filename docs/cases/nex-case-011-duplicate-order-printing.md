---
status: canonical
last_reviewed: 2026-06-04
scope: app
---

# CASE: nex-case-011-duplicate-order-printing

## Run State
- task_slug: nex-case-011-duplicate-order-printing
- tier: 1
- branch: fix/nex-011-duplicate-print
- status: IMPLEMENTED
- last_completed_agent: executioner
- next_agent: reviewer
- active_runner: claude
- interrupted: false
- interrupt_reason: none
- updated: 2026-06-04

## Handoff
- Phase in progress: intake complete; ready for implementation.
- Done so far: Root causes fully isolated with file + line citations. Three distinct duplicate-print vectors identified; two are in nexus, one is a bridge-side amplifier. Fix is surgical — no schema changes, no new abstractions.
- Exact next action: Executioner to (1) remove `PrintOrder::dispatch()` from all `markPrinted` paths (both controllers), (2) add `is_printed` idempotency guard to `OrderApiController::markPrinted`, (3) verify the bridge receives only `OrderPrinted` on ack and no spurious re-print. nex-case-005 (legacy non-idempotent path) is addressed by action (2) and can be closed together.
- Working-tree state: no app edits applied yet.
- Risks / do-not-redo: Do not remove `PrintOrder::dispatch()` from `OrderService::processOrder()` — that is the correct initial print trigger. Only remove it from the ack/mark-printed paths. Do not touch the `OrderPrinted` dispatches — those are the correct post-print notifications. Do not enable `NEXUS_PRINT_EVENTS_ENABLED` as part of this fix; the PrintEvent system is separately gated.

## Tier
1 — operational P1. Duplicate kitchen tickets cause double-cooking, waste, and staff confusion. Gates dev→staging promotion.

## Branch
fix/nex-011-duplicate-print (off `dev`)

## Problem

Kitchen orders print more than once for the same order. Observed on the live
Pi setup: a single tablet order submission produces 2+ kitchen tickets.
Impacts every order during a session; not intermittent.

The print-bridge (`woosoo-print-bridge`) is the active print executor in MVP
(`NEXUS_PRINT_EVENTS_ENABLED=false`). It listens on the Reverb WebSocket
`admin.orders` channel for `order.printed` events and sends the ESC/POS job
to the thermal printer.

## Contrarian Review

1. Correct scope? Yes — the duplicate signals originate in nexus (both controllers dispatch `PrintOrder` on the ack path). Bridge-side filtering is a second layer of defense but not the durable fix.
2. Already fixed / handled? No. The `PrintEvent` reserve/ack mutex in `PrintEventService` would prevent multi-bridge races, but it is feature-gated off. The existing `is_printed` check in `PrinterApiController::markPrinted` (line 49) is correct, but `OrderApiController::markPrinted` (line 619) lacks it, and both still dispatch `PrintOrder` after marking.
3. Scope exactly as described? Yes — two controllers, three dispatch sites. No schema changes needed.
4. What breaks if wrong? If `PrintOrder::dispatch()` is removed from the *initial* fire (OrderService), nothing gets printed at all. The scalpel must target only the ack-path dispatches.
5. Simpler path? Yes: stop firing the "print command" event from the "ack" path. One-liner removal × 4 sites, plus one guard block.
6. Touches contract/auth/state/payment/print? Print path yes. No auth/payment/order state changes. Tier 1.
7. Split required? No. Fix is self-contained in two controllers. nex-005 is the idempotency guard sub-task and is addressed inline.

## Investigation

### Active print architecture (MVP)

```
Tablet → POST /api/devices/create-order
           └─ OrderService::processOrder() [app/Services/Krypton/OrderService.php:165]
                └─ DB::afterCommit → PrintOrder::dispatch($deviceOrder)   ← CORRECT initial fire
                        │
                        ▼ Reverb WS: admin.orders / order.printed
                   woosoo-print-bridge (Flutter/Android)
                        │
                        └─ prints ESC/POS to thermal printer
                        └─ calls POST /api/order/{orderId}/printed  ← ack path
```

`PrintEvent` system (`PrintEventService`, `PrinterApiController` printer routes) is
**disabled** in MVP (`NEXUS_PRINT_EVENTS_ENABLED=false`). The printer routes return 503.
The active ack path is `OrderApiController::markPrinted` or
`PrinterApiController::markPrinted` (via the feature-gated printer routes if somehow
reachable — but those 503 in MVP).

### Vector 1 — markPrinted re-dispatches PrintOrder (PRIMARY)

Both `markPrinted` implementations fire `PrintOrder::dispatch()` after acknowledging print:

**`PrinterApiController::markPrinted`** — `app/Http/Controllers/Api/V1/PrinterApiController.php:72`
```php
$deviceOrder->is_printed = true;
// ...
PrintOrder::dispatch($deviceOrder);   // ← re-fires print command
OrderPrinted::dispatch($deviceOrder); // ← correct notification
```

**`OrderApiController::markPrinted`** — `app/Http/Controllers/Api/V1/OrderApiController.php:640`
```php
$deviceOrder->is_printed = true;
// ...
PrintOrder::dispatch($deviceOrder);   // ← re-fires print command
OrderPrinted::dispatch($deviceOrder); // ← correct notification
```

`PrintOrder` broadcasts `order.printed` on `admin.orders` — the same channel the
bridge listens on for print commands. When the bridge calls markPrinted to ack a
print, it receives another `order.printed` command and tries to print again.

### Vector 2 — Dual-event double-fire on same channel

When markPrinted runs, it dispatches BOTH `PrintOrder` AND `OrderPrinted`.
Both implement `ShouldBroadcastNow` and both broadcast as `order.printed` on
`admin.orders` (`PrintOrder::broadcastAs()` and `OrderPrinted::broadcastAs()`
both return `'order.printed'`). The bridge receives two events almost
simultaneously on the same channel — both look like print commands.

Sources:
- `PrintOrder` broadcasts on: `admin.orders` — `app/Events/PrintOrder.php:34`
- `OrderPrinted` broadcasts on: `admin.orders`, `orders.{order_id}` — `app/Events/Order/OrderPrinted.php:33`

### Vector 3 — OrderApiController::markPrinted missing idempotency guard (nex-case-005)

`PrinterApiController::markPrinted` has an early-return guard (line 49):
```php
if ($deviceOrder->is_printed) {
    return response()->json(['success' => true, 'message' => 'Order was already printed', ...]);
}
```

`OrderApiController::markPrinted` (line 619) **does not**. It unconditionally writes
`is_printed=true`, `printed_at=now()`, and dispatches both events, even on a retry
of an already-printed order. If the bridge retries this endpoint (network error,
timeout), it gets another `PrintOrder` broadcast → another print job.

### No per-print mutex in MVP (amplifier)

`PrintEventService::reserve()` provides a `lockForUpdate` mutex to prevent two bridge
devices from printing the same event. It is gated behind `NEXUS_PRINT_EVENTS_ENABLED`
and currently disabled. If more than one bridge instance is running, both receive
the same `order.printed` WS event and both print before either acks.

## Root Cause

**Primary:** `PrintOrder::dispatch()` — a "print this order now" command — is fired
from the ack path (`markPrinted`) in both controllers. This turns every acknowledgment
into a new print command delivered to the listening bridge.

**Secondary:** Both `PrintOrder` and `OrderPrinted` broadcast as `order.printed` on
`admin.orders`, so a single `markPrinted` call fires two indistinguishable events on
the bridge's listen channel — two print commands from one ack.

**Tertiary (nex-case-005):** `OrderApiController::markPrinted` has no `is_printed`
guard, so any retry (bridge or network) causes an unconditional re-dispatch.

## Proposed Fix

### 1. Remove `PrintOrder::dispatch()` from ack paths (primary fix)

`PrintOrder` is a print command. It must only fire from the initial order-creation
path (`OrderService::processOrder` afterCommit) and from explicit re-dispatch
(admin manual action). It must never fire from an ack/mark-printed path.

**`app/Http/Controllers/Api/V1/PrinterApiController.php` — `markPrinted` (line ~72)**
```php
// Remove:
PrintOrder::dispatch($deviceOrder);
// Keep:
OrderPrinted::dispatch($deviceOrder);
```

**`app/Http/Controllers/Api/V1/PrinterApiController.php` — `markPrintedBulk` (line ~195)**
```php
// Remove:
PrintOrder::dispatch($order);
// Keep:
OrderPrinted::dispatch($order);
```

**`app/Http/Controllers/Api/V1/OrderApiController.php` — `markPrinted` (line ~640)**
```php
// Remove:
PrintOrder::dispatch($deviceOrder);
// Keep:
OrderPrinted::dispatch($deviceOrder);
```

### 2. Add idempotency guard to `OrderApiController::markPrinted` (nex-case-005 fix)

Mirror the pattern from `PrinterApiController::markPrinted:49`:
```php
if ($deviceOrder->is_printed) {
    return response()->json(['success' => true, 'message' => 'Order was already printed', 'data' => [
        'order_id' => $deviceOrder->order_id,
        'is_printed' => $deviceOrder->is_printed,
        'printed_at' => $deviceOrder->printed_at,
    ]]);
}
```
Add this block before the `$deviceOrder->is_printed = true` assignment in
`app/Http/Controllers/Api/V1/OrderApiController.php:markPrinted` (line ~634).

### 3. Do not change

- `OrderService::processOrder` afterCommit `PrintOrder::dispatch()` — the legitimate initial trigger.
- `OrderApiController::dispatch()` (`GET /api/order/{orderId}/dispatch`) — explicit admin re-print.
- `DispatchPrintOrder` artisan command — explicit manual re-dispatch.
- `OrderPrinted::dispatch()` calls — correct post-print state notification.
- `PrintEventService` / `RetryUnacknowledgedPrintEvents` — gated behind feature flag; unchanged.

## Files Changed

- `app/Http/Controllers/Api/V1/PrinterApiController.php` — remove `PrintOrder::dispatch()` from `markPrinted` (line ~72) and `markPrintedBulk` (line ~195).
- `app/Http/Controllers/Api/V1/OrderApiController.php` — remove `PrintOrder::dispatch()` from `markPrinted` (line ~640); add `is_printed` idempotency guard before write (nex-005).
- `docs/cases/nex-case-011-duplicate-order-printing.md` — this case.

## Verification

1. Full print flow — order creation:
   - Submit a new order from the tablet.
   - Confirm exactly ONE kitchen ticket prints.
   - Call `GET /api/order/{orderId}/dispatch` as a separate manual re-print — confirm one more ticket, not two.

2. Ack path — no re-print:
   - After printing, call `POST /api/order/{orderId}/printed`.
   - Confirm NO additional ticket is produced.
   - Confirm the WS `admin.orders` channel emits only `OrderPrinted` (no `PrintOrder`) after ack. (Check Reverb logs or Laravel telescope.)

3. Idempotency (nex-005 guard):
   - Call `POST /api/order/{orderId}/printed` twice on the same order.
   - Second call returns `{ success: true, message: 'Order was already printed' }` with the original `printed_at`.
   - No additional WS events fired.

4. Bulk mark-printed:
   - Call `POST /api/orders/printed/bulk` with a mix of already-printed and unprinted order IDs.
   - Confirm only unprinted orders generate a `PrintOrder` event; no already-printed order fires one.
   - (Note: bulk route is feature-gated; verify by enabling `NEXUS_PRINT_EVENTS_ENABLED=true` in `.env.testing` for this test only.)

5. Regression:
   - `php artisan test --filter=Print` stays green.
   - Existing `PrintEvent` ack/fail/reserve paths (in `PrinterApiController`) are unchanged in behaviour.

## Executioner Verdict

Applied 2026-06-04. Three sites changed:
- `PrinterApiController::markPrinted` — removed `PrintOrder::dispatch()`, removed unused import.
- `PrinterApiController::markPrintedBulk` — removed `PrintOrder::dispatch()`.
- `OrderApiController::markPrinted` — removed `PrintOrder::dispatch()`; added `is_printed` early-return guard (closes nex-005).
`PrintOrder` import removed from `PrinterApiController` (now unused). No other changes.

## Remaining Risks

- The bridge may still receive `order.printed` from `OrderPrinted::dispatch()` on `admin.orders` and interpret it as a print command if it does not check `order.is_printed` in the payload. The nexus fix is necessary; bridge-side `is_printed` filtering is the complementary defense. File a bridge-side follow-up if duplicates persist after this fix is deployed.
- `markPrintedBulk` (PrinterApiController) is behind `NEXUS_PRINT_EVENTS_ENABLED`. Verify the route actually returns 503 in MVP before including it in the PR diff scope.
- `nex-case-005` is fully addressed by fix item 2 above and can be closed when this PR merges.
