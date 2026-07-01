# Woosoo KDS — Implementation Spec

**Artifact:** Kitchen Display System (single-screen order queue)
**Reference prototype:** `Woosoo KDS.html` (+ `kds-app.jsx`, `kds-data.jsx`)
**Target device:** 10.7" landscape tablet, wall/rail-mounted, 1280 × 800 logical px
**Audience:** the engineer/agent building the production KDS

This spec describes **what to build and the rules that govern it**. The prototype is the visual + interaction source of truth; this document is the behavioral + data contract. Where they ever disagree, this document wins on *logic*, the prototype wins on *look*.

> **⚠ Authority correction (2026-07-01, NEX-001).** This spec is **subordinate to
> [`docs/CONTRACTS.md`](../../../../docs/CONTRACTS.md) §1**, which is the single source of truth for
> order state and recall. Per §1 and the shipped `KdsController::recall()`: **`voided` is terminal and
> is NOT recallable** — a void re-fires as a *new* kitchen ticket, and recall is `served → in_progress`
> **only** (rejected from any other state with HTTP 422). Any wording below that says a *voided* or
> *completed* card is recallable is stale and overridden by CONTRACTS §1; §3.4 and the state table have
> been corrected accordingly.

---

## 1. What this is (and is not)

A KDS is a **read-mostly board** for the line. It subscribes to orders from the POS and lets cooks advance **kitchen status only**. It is deliberately narrow.

**In scope**
- Display every active order as a card, sorted by urgency.
- Let the line advance an order through the kitchen state machine.
- Check off individual items as they're plated.
- Surface elapsed-time urgency (warning / overdue).
- Filter the board by stage, plus Completed / Voided history.
- **Recall** a **served** order back onto the line. (Voided/completed are terminal — see the
  authority note below and §3.4.)

**Out of scope (KDS must NOT do these)**
- Take payment, edit prices, add/remove items, or change quantities.
- Create or void orders. **Voids originate in the POS / front-of-house**, not here.
- Merge an initial order with its refills (see §3.2 — they are separate tickets by design).
- Table management, reservations, or any FOH concern.

The KDS **writes exactly one field** to the order: its kitchen `state` (plus per-item `done` flags and a `recalled` counter). Everything else is read-only mirror data from the POS.

---

## 2. Data model

### 2.1 Ticket

One ticket = one order instance = one `type`. Schema:

```ts
interface Ticket {
  id:        string;     // POS order id, displayed verbatim (e.g. "K-1182")
  table:     string;     // table label, e.g. "T-05"
  type:      'initial' | 'refill';
  issued:    string;     // human time the order was fired, e.g. "7:23 PM"
  elapsed:   number;     // SECONDS since issued; server-authoritative (see §6.2)
  state:     'new' | 'preparing' | 'ready' | 'served' | 'voided';
  items:     Item[];
  recalled?: number;     // times this ticket was recalled; absent/0 = never
  voidReason?: string;   // present only when state === 'voided'
}

interface Item {
  qty:     number;
  name:    string;       // safety modifier inlined after " — " (see §2.3)
  done:    boolean;      // checked off by the line
  safety?: boolean;      // true = allergy/diet modifier; render emphasized
}
```

### 2.2 Kitchen states

| state | label shown | forward action | gate | terminal | recallable |
|---|---|---|---|---|---|
| `new` | New | **Start Preparing** → `preparing` | — | no | no |
| `preparing` | Preparing | **Mark Ready** → `ready` | **all items checked** | no | no |
| `ready` | Ready | **Mark Served** → `served` | — | no | no |
| `served` | Served | — | — | no¹ | **yes** |
| `voided` | Voided | — | — | **yes** | **no**² |

¹ `served` is **non-terminal** per CONTRACTS §1 (recall `served → in_progress` is permitted).
² `voided` is **terminal** — not recallable. A void re-fires as a *new* kitchen ticket (CONTRACTS §1).

Happy path: `new → preparing → ready → served`. `voided` is entered **only** by a POS/FOH void event arriving over the feed — never by a button on this screen.

### 2.3 Safety modifiers

Allergy/diet modifiers are inlined into `name` after `" — "` and flagged `safety:true`
(e.g. `"Beef Bulgogi — no garlic"`). Render the base name normally and the modifier
emphasized (warning color) so the line can't miss it. Non-safety prep preferences are
out of scope for MVP.

---

## 3. Domain rules (the important part)

### 3.1 State machine & gating
- Transitions are **forward-only** via the card's primary button; there is no manual "back" except **Recall** (§3.4).
- **`preparing → ready` is gated:** the button is disabled until **every item's `done === true`**. If tapped while gated, show a toast ("Complete all checklist items first.") and do nothing. Transitions never auto-check items; checking is a separate, explicit act.
- `new → preparing` and `ready → served` are ungated.
- Tapping an item row toggles its `done`. **Items are not toggleable on terminal cards** (served/voided).

### 3.2 Initial vs. refill — never merge
An initial order and a later refill for the **same table** are **separate tickets with separate timers**. When the initial is served it leaves the active board; a refill arrives as its own new card. Do not group, stack, or roll them up. (Prototype shows this with T-05: an overdue initial `K-1182` and a fresh refill `K-1188` coexisting.)

### 3.3 Urgency (elapsed-time thresholds)
Urgency is read from the live timer, **not** a stored badge. Thresholds:

| elapsed | urgency | treatment |
|---|---|---|
| 0 – 15 min | normal | default timer |
| 15 – 25 min | **warning** | amber timer |
| ≥ 25 min | **overdue** | red timer + red card edge |

```
WARN_TARGET = 15 * 60   // seconds
OVER_TARGET = 25 * 60
```

Urgency applies to **active tickets only**. Completed and voided tickets are always `ok` (their timers are frozen — see §6.2).

> **Known limitation / decision pending:** thresholds are currently a **flat 25 min for every ticket** regardless of size or type. A 2-item refill and an 11-item party order both go red at exactly 25:00. Product has flagged making this **relative to order type/size** (e.g. refills warn ~8 / overdue ~15; large initials get more runway). Build the thresholds as **configurable per type** rather than two hard-coded constants so this can land without a refactor.

### 3.4 Recall
Recall pulls a **`served`** ticket back onto the active line as a **re-fire** — used when a runner
reports a missing/wrong item, a dish comes back, or someone bumped too early. **`voided` is terminal
and is NOT recallable** (CONTRACTS §1); a void re-fires as a *new* kitchen ticket, not by un-voiding
the original.

- A **`served`** card has **no forward button**; it shows a **"Recall to Line"** action instead.
  `voided`/`completed` cards show **no** recall action.
- Recall sets `state → 'preparing'` (backend `served → in_progress`) and increments `recalled` (1, 2, 3…).
  The shipped `KdsController::recall()` rejects any non-`served` source with HTTP 422 and enforces a
  `MAX_RECALLS` cap.
- The re-fired card shows a `RECALLED ×n` marker so the line knows it's not a fresh order.
- **Resolved (CONTRACTS §1):** recall lands on `preparing` (`in_progress`); it is not configurable and
  never targets `ready` or an un-void.

### 3.5 Sort order
Within the current filter, sort:

1. **Overdue first** (any `over` ticket floats to top)
2. then by stage: **Preparing → Ready → New → Served → Voided** (`STAGE_SORT`)
3. ties broken by **oldest elapsed first** (longest-waiting on top)

### 3.6 Filters
Chip bar, grouped with dividers:

`All Active` · `Overdue` ‖ `New` · `Preparing` · `Ready` ‖ `Completed` · `Voided`

- **All Active** = `new | preparing | ready` (excludes terminal states).
- **Overdue** = urgency `over` only.
- **Completed** = `served`; **Voided** = `voided`.
- Each chip shows a live count of matching tickets.
- Empty filter result → "Queue clear" empty state.

### 3.7 Voided cards
- Entered only from the feed (§2.2). Greyed/dimmed, table number struck through, with the `voidReason` shown.
- Item checklist is non-interactive.
- Carries the **Recall to Line** action (a void can be reversed if FOH made a mistake).

---

## 4. Screen layout

Single screen, three stacked regions inside a fixed 1280 × 800 canvas (letterbox/scale to the physical viewport; controls live outside the scaled canvas):

1. **Command bar** (top): brand, live counts (Active / New / Preparing / Ready / Overdue), Online-or-Rush indicator, wall clock.
2. **Filter chips** (under command bar): §3.6.
3. **Queue grid** (fills remaining height): responsive card grid, `comfortable` / `compact` density.

### 4.1 Ticket card anatomy (top → bottom)
- **Header, two balanced columns of equal height:**
  - Left: `TABLE` kicker · **table number (large, Kanit)** · `Order {id}` subline.
  - Right: `ELAPSED` kicker · **live mm:ss timer** (colored by urgency) · `Issued {time}` subline.
- **Type row:** `Initial Order` or `Refill` pill; `RECALLED ×n` marker if applicable.
- **Void reason** (voided only).
- **Items:** header with `done/total checked` count, then tappable item rows (qty × name, check box, safety modifier emphasized).
- **Footer:** status badge + primary action button (or Recall / done-note on terminal cards).

---

## 5. Visual design tokens

Lift exact values from `Woosoo KDS.html` `:root`. Summary:

- **Fonts:** Raleway (display/labels `--font-d`), **Kanit** (UI + table numbers `--font-s`), JetBrains Mono (timers/ids `--font-m`).
- **Surface:** near-black layered greys `--bg0…bg4`; warm off-white text `--fg0…fg3`; amber accent `#F6B56D`.
- **Stage colors:** New = slate-blue, Preparing = orange, Ready = green, Served = muted grey.
- **Urgency:** warning amber `#d8a440`, overdue red `#d65540`.
- **Type:** refill = teal `#5cb6b0`. **Void:** muted mauve `#a892ad` (deliberately distinct from urgency red).
- Radii `4 / 8 / 12 / 16`; tabular-nums on all numeric/timer text.

> **No per-table color coding.** Considered and explicitly declined: the card's color budget is spent on **stage** (left edge) and **urgency** (timer + edge). A third table-keyed hue would dilute the urgency read and require a learned legend. If table grouping is ever needed, use a quiet low-chroma table chip/dot in the header only — never a full card tint.

---

## 6. Production integration (not in the prototype)

The prototype is fully client-side with mock data and a local 1 Hz timer. Production needs:

### 6.1 Order feed
- Subscribe to the POS order stream (websocket / SSE preferred; poll as fallback).
- Inbound events to handle: **order created** (`new`), **order updated** (items/qty changed upstream before cook starts), **order voided** (→ `voided` with `voidReason`).
- KDS publishes back only: `state` changes, item `done` toggles, `recalled` increments. Treat these as commands to the order service; reflect optimistically, reconcile on ack.

### 6.2 Time authority
- `elapsed` must be **server-authoritative** — derive from a server `issuedAt` timestamp, not a client counter, so multiple stations agree and a tablet reboot doesn't reset timers. The client only renders `now − issuedAt`, ticking locally between syncs.
- Freeze elapsed when `state ∈ {served, voided}` (timer stops at the bump/void moment).

### 6.3 Multi-station sync
- Several tablets may show the same board. State changes from one must broadcast to all (the feed is the single source of truth; no station owns local state).
- Handle conflict: if two stations advance the same ticket, last-write-wins on a monotonic version is acceptable for MVP; surface nothing to the user.

### 6.4 Resilience
- Offline/disconnected: keep showing last-known board, queue outbound commands, show a connection indicator, replay on reconnect.
- Persist nothing sensitive locally beyond the command queue.

---

## 7. Acceptance checklist

- [ ] Cards render with balanced two-column headers; table number in Kanit; `Order {id}` visible.
- [ ] `Start Preparing` and `Mark Served` advance immediately; `Mark Ready` is gated until all items checked, with a toast when blocked.
- [ ] Tapping items toggles `done`; terminal cards are non-interactive.
- [ ] Timers tick live, server-authoritative, frozen on served/voided.
- [ ] Warning at 15 min, overdue at 25 min (thresholds configurable per type).
- [ ] Sort = overdue → stage → oldest-first.
- [ ] Filters incl. grouped Completed / Voided with live counts; empty state renders.
- [ ] Voided cards arrive only from the feed, greyed + struck + reason, recallable.
- [ ] Recall returns served/voided → preparing (configurable), increments and shows `RECALLED ×n`.
- [ ] Initial + refill for one table never merge.
- [ ] No per-table color coding.

---

## 8. Open decisions for product (carry forward)

1. **Overdue basis** — flat 25 min vs. relative to order type/size. *Build configurable.*
2. **Recall target** — `preparing` (re-cook) vs. `ready` (re-plate). *Build configurable.*
3. Sound/visual alert on new overdue? (Not in prototype.)
4. Bump-bar / hardware-button mapping for kitchens that don't touch the screen.
