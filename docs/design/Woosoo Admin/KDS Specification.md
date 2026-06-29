# Kitchen Display System — Specification

> **Module:** Woosoo Admin · KDS
> **Surface:** Wall display · 1440 × 900+
> **Audience:** Line cooks, Kitchen Manager
> **Version:** 1.0 — 26 May 2026
> **Owner:** Floor Ops

A wall-mounted, kitchen-facing screen that surfaces the live order queue, lets cooks acknowledge and bump tickets with a tap, and keeps every active table visible at a glance — no router slips, no shouting across the line.

---

## Table of Contents

1. [Context](#1-context)
2. [Goal & Objective](#2-goal--objective)
3. [Scope](#3-scope)
4. [Functional Requirements](#4-functional-requirements)
   - 4.1 [Queue & Tickets](#41-queue--tickets)
   - 4.2 [State Lifecycle](#42-state-lifecycle)
   - 4.3 [Filtering & Sorting](#43-filtering--sorting)
   - 4.4 [Item-level Actions](#44-item-level-actions)
   - 4.5 [Alerts & Flags](#45-alerts--flags)
5. [Non-functional Requirements](#5-non-functional-requirements)
6. [State Machine](#6-state-machine)
7. [Use Cases](#7-use-cases)
8. [Ideal Schema](#8-ideal-schema)
9. [Assumptions](#9-assumptions)
10. [Open Questions](#10-open-questions)

---

## 1. Context

Woosoo is a Korean BBQ restaurant that fires tickets from tablet-side ordering at each table. At peak hours (Fri / Sat 7–10 pm) the kitchen handles **40+ concurrent tables**, each pacing through 2–3 courses with frequent banchan and meat refills.

Today, orders arrive as printer slips. Slips pile, get misread, and there is no shared signal for elapsed time or table priority. When a refill is forgotten the floor finds out from the guest, not the kitchen. The KDS replaces the slip printer with a single live view of every active ticket.

> **Why now:** ticketing was migrated to the new POS in Q1, which exposes a firehose of order events. The KDS is the first screen that consumes that stream end-to-end.

---

## 2. Goal & Objective

Give the line a calm, glanceable view of *what to cook next* and *what is running late*, with one-tap acknowledgements that move tickets through the kitchen without ever needing a keyboard or printer.

### Success Metrics — v1.0

| Metric                 | Target          | Definition                                  |
| ---------------------- | --------------- | ------------------------------------------- |
| **Ticket → Confirm**   | < 30 sec        | From fire to cook acknowledgment            |
| **Avg Prep Time**      | ≤ 14 min        | Confirm → Bump · Served                     |
| **Overdue Rate**       | < 5 %           | Tickets exceeding target time               |
| **Printer Slips**      | 0 / shift       | Fully replaces paper at the pass            |
| **Kitchen Uptime**     | 99.9 %          | Service-hour availability                   |
| **Cook Onboarding**    | < 5 min         | Trainee productive on first shift           |

---

## 3. Scope

### In Scope

- A single, read-and-act queue of kitchen tickets, sorted by urgency.
- Three ticket states — **Pending → Preparing → Served** — plus a derived **Overdue** flag.
- Status filter chips with live counters.
- Per-item check-off and progress bar on each ticket.
- Per-ticket flags: VIP, Rush, Refill, Allergy, Course-N.
- Live elapsed-time counter, with warn / over thresholds.
- Recall: served tickets can be reopened back to preparing.
- Density toggle (Comfortable / Compact) for screen size.

### Out of Scope — v1.0

- Station routing (cold / hot / grill / bar) — single kitchen view only.
- Refill ticker and announcement bar — moved to a future floor-facing screen.
- Server messaging back to floor — handled in the FOH app.
- Payment, comps, and check splitting — Admin module.
- Inventory 86-ing — Admin module.

---

## 4. Functional Requirements

Requirement priority follows MoSCoW. **MUST** = blocking for v1.0; **SHOULD** = expected; **COULD** = nice-to-have, deferred if needed.

### 4.1 Queue & Tickets

| ID        | Priority | Requirement                                                                                                                                              |
| --------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `FR-Q-01` | MUST     | **Display every active ticket** (any non-served state) on a single screen, with queue rank, table, ticket id, course, guest count, and issue time.       |
| `FR-Q-02` | MUST     | **Update the elapsed-time counter every second** for every visible ticket.                                                                               |
| `FR-Q-03` | MUST     | **Surface each line item** with quantity, name, and optional modifier (e.g. "no garlic — allergy").                                                      |
| `FR-Q-04` | SHOULD   | Show an **items-complete progress bar** on every preparing ticket (e.g. 3/6 items).                                                                      |
| `FR-Q-05` | SHOULD   | Offer a **density toggle** (Comfortable = 3-col, Compact = 4-col) so a smaller wall TV still fits the load.                                              |
| `FR-Q-06` | COULD    | Allow an operator to **collapse the served column** to free vertical space during rush.                                                                  |

### 4.2 State Lifecycle

| ID        | Priority | Requirement                                                                                                                                       |
| --------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `FR-S-01` | MUST     | Every ticket arrives in **Pending**. A cook taps *Confirm · Start* to advance it to **Preparing**.                                                |
| `FR-S-02` | MUST     | A cook taps *Bump · Served* on a preparing ticket to move it to **Served**. Marking served auto-checks all remaining items.                       |
| `FR-S-03` | MUST     | A served ticket exposes a *Recall* action that returns it to **Preparing** with all items reset to un-checked.                                    |
| `FR-S-04` | SHOULD   | An active ticket whose elapsed time crosses the **prep target** (default 14 min) is flagged **Overdue** and re-sorted to the top of the queue.    |
| `FR-S-05` | SHOULD   | An active ticket may be **voided**. Voids require a reason code (FR-A-04) and remove the ticket from the queue.                                   |
| `FR-S-06` | MUST     | State transitions write an immutable **event log entry** (ticket id, from, to, actor, timestamp).                                                 |

### 4.3 Filtering & Sorting

| ID        | Priority | Requirement                                                                                                                                  |
| --------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `FR-F-01` | MUST     | Provide a filter chip strip with: **All Active · Overdue · Pending · Preparing · Served**, each showing a live count.                        |
| `FR-F-02` | MUST     | Default sort: **Overdue first, then Preparing, then Pending, then Served**, breaking ties by descending elapsed time.                        |
| `FR-F-03` | COULD    | Allow operators to **pin a ticket** so it stays at rank #1 regardless of state.                                                              |

### 4.4 Item-level Actions

| ID        | Priority | Requirement                                                                                                                          |
| --------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `FR-I-01` | MUST     | A cook can **tap any item** to toggle its *done* state. The progress bar and bump button update immediately.                         |
| `FR-I-02` | SHOULD   | Per-item modifiers must be visually distinguishable — standard mods in amber, **allergy / safety mods in pink**.                     |
| `FR-I-03` | COULD    | Bulk action: **Mark all items done** on a single ticket from the ticket header.                                                      |

### 4.5 Alerts & Flags

| ID        | Priority | Requirement                                                                                                                                   |
| --------- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `FR-A-01` | MUST     | Render flags as colored badges on each ticket: **VIP, Rush, Refill, Allergy, Course-N**.                                                      |
| `FR-A-02` | MUST     | **Allergy tickets are visually elevated**: red side-rail, persistent allergy note, mods highlighted.                                          |
| `FR-A-03` | SHOULD   | An overdue ticket pulses its left edge until acknowledged (i.e. bumped or recalled).                                                          |
| `FR-A-04` | SHOULD   | Void action presents a dialog with reason codes: *guest cancelled, allergy conflict, wrong table, kitchen error, other.*                      |
| `FR-A-05` | COULD    | An ambient audio chime announces every **newly fired** ticket; mute-by-shift in the Tweaks panel.                                             |

---

## 5. Non-functional Requirements

| ID           | Category          | Requirement                                                                                                              |
| ------------ | ----------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `NFR-P-01`   | **Performance**   | UI input → visual response **under 100 ms** for all tap actions (confirm, bump, item check).                             |
| `NFR-P-02`   | **Performance**   | End-to-end **fire → display under 2 s** from the POS event being emitted.                                                |
| `NFR-P-03`   | **Performance**   | Maintain **60 fps** while running 50 active tickets with per-second timers.                                              |
| `NFR-R-01`   | **Reliability**   | **Offline-tolerant**: last-known queue cached on device, syncs on reconnect. No data loss across reconnects of ≤30 min.  |
| `NFR-R-02`   | **Reliability**   | Auto-recover from network disconnects within **10 s** using exponential backoff.                                         |
| `NFR-R-03`   | **Reliability**   | Service-hour availability of **99.9 %** measured monthly.                                                                |
| `NFR-U-01`   | **Usability**     | All actionable controls must hit **≥ 44 × 44 px** for greasy-finger operation.                                           |
| `NFR-U-02`   | **Usability**     | Text must be **readable from 2.5 metres**: minimum 14 px body, 22 px+ for timer / table number.                          |
| `NFR-U-03`   | **Usability**     | **No login** on the kitchen device — opens straight into the queue. Device-level pairing handled in Admin.               |
| `NFR-A-01`   | **Accessibility** | Status is never communicated by **color alone** — every state pairs a color with a label.                                |
| `NFR-A-02`   | **Accessibility** | WCAG **AA contrast (≥ 4.5:1)** for all body text and labels.                                                             |
| `NFR-S-01`   | **Security**      | All ticket events transmitted over **TLS 1.3**; device tokens rotate every 24 h.                                         |
| `NFR-S-02`   | **Security**      | Action audit log (FR-S-06) is **append-only, 90-day retention** at minimum.                                              |
| `NFR-C-01`   | **Compatibility** | Target: **Chrome 110+** on a 1080p/4K wall display; iPad Pro 12.9" as portable fallback.                                 |
| `NFR-O-01`   | **Operability**   | App must **self-recover** from a stale tab after 5 min of inactivity (live reload).                                      |

---

## 6. State Machine

Three primary states plus a derived *Overdue* flag. The kitchen only ever moves a ticket forward by tapping the primary action button on its card.

```
   ┌───────────┐  Confirm   ┌─────────────┐   Bump    ┌──────────┐
   │  PENDING  │ ─────────▶ │  PREPARING  │ ────────▶ │  SERVED  │
   │ Just fired│            │ On the line │           │  Plated  │
   └───────────┘            └─────────────┘           └──────────┘
                                  ▲                         │
                                  └────── Recall ───────────┘

   Overdue (derived):  elapsed ≥ PREP_TARGET (14 min)
                       applies to Pending & Preparing
```

### Transition Rules

1. **Pending → Preparing** · Triggered by Confirm tap. All `item.done` remain `false`.
2. **Preparing → Served** · Triggered by Bump tap. All items auto-marked done. `servedAt` timestamp set.
3. **Served → Preparing** · Triggered by Recall. Items reset; `servedAt` cleared.
4. **Any → Void** · Triggered by Void with reason code. Ticket removed from active queue but retained in audit log.
5. **Overdue flag** · Recomputed every tick from `elapsed ≥ PREP_TARGET`. Not a stored state.

---

## 7. Use Cases

The primary actor in every case is **Cook**, working at the kitchen display. The secondary actor is **Kitchen Manager**, who supervises and intervenes for voids & recalls.

### UC-01 — Confirm a freshly fired ticket

| Field             | Detail                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------- |
| **Actor**         | Cook                                                                                                                  |
| **Pre-condition** | A new ticket has arrived from POS and is rendered in the queue in *Pending* state.                                    |
| **Main flow**     | 1. Cook scans the queue and identifies the next ticket to start.<br>2. Cook taps **Confirm · Start** on the ticket card.<br>3. The ticket transitions to *Preparing*; the badge color, action button, and queue position update immediately.<br>4. Elapsed timer continues from the original fire time (not reset). |
| **Post-condition**| Ticket state = Preparing. An audit entry is appended.                                                                 |
| **Alt: refused**  | If allergy info is missing, cook taps the allergy badge to request floor confirmation before starting.                |

### UC-02 — Check items off during prep

| Field             | Detail                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------- |
| **Actor**         | Cook                                                                                                                  |
| **Pre-condition** | Ticket is in *Preparing*.                                                                                             |
| **Main flow**     | 1. As each dish leaves the line, cook taps that line item.<br>2. Item flips to *done* — strikethrough, green checkbox.<br>3. Progress bar fills; the bump button label updates to `Bump · 5/6`. |
| **Post-condition**| Item progress reflects line state in real time.                                                                       |

### UC-03 — Bump a complete ticket

| Field             | Detail                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------- |
| **Actor**         | Cook                                                                                                                  |
| **Pre-condition** | All items on the ticket are checked done (or KM authorises early bump).                                               |
| **Main flow**     | 1. Cook taps **Bump · Served**.<br>2. Ticket transitions to *Served*, all items mark done, `servedAt` timestamp recorded.<br>3. Card drops from active filters; visible in Served filter. |
| **Post-condition**| Ticket retired from active sort; can be recalled.                                                                     |

### UC-04 — React to an overdue ticket

| Field             | Detail                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------- |
| **Actor**         | Cook · Kitchen Manager                                                                                                |
| **Pre-condition** | A ticket has crossed the 14-minute prep target.                                                                       |
| **Main flow**     | 1. The ticket's left edge pulses red and an *Overdue* badge appears.<br>2. Ticket auto-sorts to rank #1 regardless of state.<br>3. KM glances at the screen, identifies the bottleneck, and re-prioritises the line. |
| **Post-condition**| Overdue flag clears automatically once ticket is bumped.                                                              |

### UC-05 — Recall a served ticket

| Field             | Detail                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------- |
| **Actor**         | Kitchen Manager                                                                                                       |
| **Pre-condition** | A guest reports an issue with a recently served ticket.                                                               |
| **Main flow**     | 1. KM filters to *Served*.<br>2. Locates the ticket by table number; taps **Recall**.<br>3. Ticket returns to *Preparing*, items cleared, queue re-sorts. |
| **Post-condition**| Audit log records the recall; original `servedAt` preserved in event history.                                         |

### UC-06 — Void a ticket

| Field             | Detail                                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------------------------- |
| **Actor**         | Kitchen Manager                                                                                                       |
| **Pre-condition** | Ticket needs to be removed (allergy conflict, wrong table, etc.).                                                     |
| **Main flow**     | 1. KM taps **Void** on the affected ticket.<br>2. Dialog prompts for a reason code (FR-A-04).<br>3. KM selects reason; ticket is removed from the active queue and persisted to the audit log. |
| **Post-condition**| FOH app receives a void event so the server can communicate with the guest.                                           |

---

## 8. Ideal Schema

Three core entities. Tickets are the unit of work; items live under tickets; events are the immutable audit trail. Tables and menu items are referenced by id only (owned by other modules).

Legend: ◆ primary key  ·  → foreign key

### Entity: `Ticket` *(aggregate root)*

| Field           | Type                              | Notes                                            |
| --------------- | --------------------------------- | ------------------------------------------------ |
| ◆ `id`          | `string` · K-####                 |                                                  |
| → `tableId`     | `string` · T-##                   |                                                  |
| `guests`        | `int`                             |                                                  |
| `course`        | `int` · 1–N                       |                                                  |
| `state`         | `enum`                            | `pending \| preparing \| served`                 |
| `flags`         | `string[]`                        | VIP, Rush, Refill, Allergy, Course-N             |
| `note`          | `string` · nullable               |                                                  |
| `allergy`       | `boolean`                         |                                                  |
| `issuedAt`      | `timestamp`                       |                                                  |
| `confirmedAt`   | `timestamp` · nullable            |                                                  |
| `servedAt`      | `timestamp` · nullable            |                                                  |
| `voidedAt`      | `timestamp` · nullable            |                                                  |
| `voidReason`    | `enum` · nullable                 | `guestCancelled \| allergyConflict \| wrongTable \| kitchenError \| other` |

### Entity: `TicketItem` *(owned by Ticket)*

| Field           | Type                              | Notes                                            |
| --------------- | --------------------------------- | ------------------------------------------------ |
| ◆ `id`          | `uuid`                            |                                                  |
| → `ticketId`    | `→ Ticket.id`                     |                                                  |
| → `menuItemId`  | `string`                          |                                                  |
| `name`          | `string`                          | Denormalised for display                         |
| `qty`           | `int` · ≥ 1                       |                                                  |
| `modifier`      | `string` · nullable               |                                                  |
| `isSafetyMod`   | `boolean`                         | Allergy / dietary — renders pink                 |
| `done`          | `boolean`                         |                                                  |
| `doneAt`        | `timestamp` · nullable            |                                                  |
| `position`      | `int`                             | Display order within ticket                      |

### Entity: `TicketEvent` *(append-only · 90d retention)*

| Field           | Type                              | Notes                                            |
| --------------- | --------------------------------- | ------------------------------------------------ |
| ◆ `id`          | `uuid`                            |                                                  |
| → `ticketId`    | `→ Ticket.id`                     |                                                  |
| `type`          | `enum`                            | `fired \| confirmed \| itemToggled \| bumped \| recalled \| voided` |
| `fromState`     | `enum` · nullable                 |                                                  |
| `toState`       | `enum` · nullable                 |                                                  |
| `payload`       | `json`                            | e.g. `{itemId, done}` or `{voidReason}`          |
| `actorId`       | `string`                          | Device id or staff id                            |
| `at`            | `timestamp`                       |                                                  |

### Entity: `KdsConfig` *(singleton · per device)*

| Field           | Type                              | Notes                                            |
| --------------- | --------------------------------- | ------------------------------------------------ |
| ◆ `deviceId`    | `uuid`                            |                                                  |
| `prepTargetSec` | `int` · default 840               | Overdue threshold (14 min)                       |
| `density`       | `enum` · `'comfy' \| 'compact'`   |                                                  |
| `audioOnFire`   | `boolean`                         |                                                  |
| `defaultFilter` | `enum`                            |                                                  |
| `lastSyncedAt`  | `timestamp`                       |                                                  |

### Derived & Computed Fields

- **`elapsedSec`** — `now − issuedAt` while not served; frozen at `servedAt − issuedAt` once served.
- **`urgency`** — `ok | warn | over` derived from `elapsedSec / prepTargetSec` at 0.75 and 1.0.
- **`itemsDone` / `itemsTotal`** — sum of `qty` over completed items.
- **`queueRank`** — 1-indexed position after sort; transient (recomputed every render).

---

## 9. Assumptions

- A single kitchen — no station routing in v1.0.
- POS already emits an order-fired event with all items resolved (no draft tickets).
- Wall display has touch input or is paired with a single floor tablet for action input.
- Network is mostly stable on-prem; offline tolerance is required but not the primary mode.
- Refills and floor-side announcements live in a separate FOH surface, not the KDS.
- Cooks are not authenticated per-action; device-level pairing is enough.

---

## 10. Open Questions

1. **Sound design.** Do we ship audible alerts in v1.0, or rely on visual pulse only? KM preference is split.
2. **Course gating.** Should Course-2 only become visible once Course-1 is served, or always visible?
3. **Auto-confirm.** Should a pending ticket auto-confirm after N seconds to avoid forgotten acknowledgements?
4. **Prep target per item.** Per-dish prep times instead of a flat 14-minute target — viable for v1.1?
5. **Recall window.** How long after served should a ticket remain recallable — full shift or 30 minutes?

---

*Woosoo Admin · KDS Specification · v1.0 — 26 May 2026 · Floor Ops*
