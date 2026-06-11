---
status: canonical
last_reviewed: 2026-06-10
scope: woosoo-nexus
---

# Order State Contract — `OrderStatus` enum

Authoritative mirror of `app/Enums/OrderStatus.php::canTransitionTo()`.
Every allowed transition is listed here. Any transition not listed is **rejected**.

## States

| Value | Type | Description |
|---|---|---|
| `pending` | transient | Created; auto-advances to `confirmed` on creation. |
| `confirmed` | kitchen-active | Accepted by kitchen; ready to start. |
| `in_progress` | kitchen-active | Kitchen is preparing the order. |
| `ready` | kitchen-active | All items done; staff delivering. |
| `served` | kitchen-active (non-terminal) | All items delivered to table. Recall permitted (KDS-driven only). |
| `completed` | **terminal** | POS-closed; bill paid. |
| `cancelled` | **terminal** | Cancelled before fulfillment. |
| `voided` | **terminal** | Voided at POS. Cannot be recalled — re-fire as a new ticket. |
| `archived` | **terminal** | Archived for historical reference. |

**Terminal states never transition.** (`completed`, `cancelled`, `voided`, `archived` → false for all targets)

## Allowed transitions

```
pending     → confirmed | voided | cancelled
confirmed   → in_progress | completed | voided
in_progress → ready | voided
ready       → served | voided
served      → in_progress | completed | voided   ← recall edge (KDS-driven only; see note below)
```

## Note: served → in_progress (recall edge)

Added in KDS P2 (2026-06-10, branch `agent/kds-p2-recall`).

- This edge is **KDS-driven only** — only `KdsController::recall()` may use it.
- Payment/POS paths (`PosController`, `ProcessOrderLogs`, `PosOrderStatusFinalizer`) must not use this edge.
- `VOIDED → IN_PROGRESS` is **rejected**. A voided order must create a new kitchen ticket; voided is terminal and cannot be un-voided.
- After recall, `device_orders.recalled` counter is incremented atomically inside the same DB transaction.

## Enforcement

`DeviceOrder::setStatusAttribute()` calls `canTransitionTo()` on every write.
An invalid transition throws `\InvalidArgumentException` before the row is saved.

## References

- `app/Enums/OrderStatus.php` — source of truth (enum cases + `canTransitionTo`)
- `app/Http/Controllers/Admin/KdsController.php` — `recall()` method (only consumer of recall edge)
- `docs/cases/kds-implementation-plan.md` § B5 — decision log (B5.1a/B5.1b)
