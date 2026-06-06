# KDS Designer Specification — Woosoo Nexus v1.0

> **Audience:** Designer producing mockups, token decisions, and component inventory for the Kitchen Display System.
> **Source issues:** #137 (master plan), #143 (PR-0A status workflow), #144 (PR-0B design tokens).
> **Status:** Pre-implementation. All decisions below are locked with product unless marked ⚠️ open.

---

## 1. What Is the KDS?

A **wall-mounted kitchen-facing screen** that replaces paper slips with a live queue of order tickets. Staff tap directly on the screen to advance orders through three workflow stages. The admin's web session authenticates the device (v1 — device-token auth is a future iteration).

**Access route:** `/kds` — admin-only, same auth gate as all other admin pages.

---

## 2. Top-Level Layout

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ TOPBAR  [Woosoo logo / "Kitchen Display"]   [Density: Comfortable/Compact ▾]  │
│          [🔕 Mute / 🔔 Chime toggle]                          [Clock HH:MM]  │
├──────────────────────────────────────────────────────────────────────────────┤
│ FILTER CHIPS                                                                  │
│  [All Active N]  [⚠ Overdue N]  [Pending N]  [Preparing N]  [Served N]       │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                                │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐           │
│  │ Ticket  │  │ Ticket  │  │ Ticket  │  │ Ticket  │  │ Ticket  │           │
│  │ Card    │  │ Card    │  │ Card    │  │ Card    │  │ Card    │           │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘  └─────────┘           │
│                                                                                │
└──────────────────────────────────────────────────────────────────────────────┘
```

- **Full viewport.** No sidebar — KDS uses a stripped layout without the admin sidebar.
- **Default theme: dark.** Kitchen ambient light favors dark backgrounds. Admin can toggle; persisted in `localStorage`.
- **Target resolution:** 1440×900 minimum, optimized for 1920×1080 wall mount.
- **Card grid:** CSS `auto-fill` columns, min card width ~320 px (Comfortable) / ~260 px (Compact).
- **Card sort order (hardcoded):** Overdue → Preparing → Pending → Served, ties broken by descending elapsed time (oldest first within each group).

---

## 3. Workflow Stages

The backend has a 9-case `OrderStatus` enum. The KDS maps these to 3 kitchen-facing **WorkflowStages** (implemented in PR-0A / issue #143) — the canonical enum is never changed.

| Stage | Badge label | Backend statuses | Primary action button |
|---|---|---|---|
| **Pending** | "New" | `pending`, `confirmed` | "Start Preparing" |
| **Preparing** | "Preparing" | `in_progress`, `ready` | "Mark as Served" |
| **Served** | "Served" | `served`, `completed` | "Recall" (secondary) |
| Voided | — | `voided` | hidden from queue |
| Cancelled | — | `cancelled` | hidden from queue |

**Overdue** is not a stage — it is a derived **flag** applied on top of any active stage when `elapsed ≥ prep_target_sec` (default **840 s / 14 min**). An overdue ticket shows the pulsing left-edge treatment and sorts to rank #1.

---

## 4. Ticket Card Anatomy

Each ticket represents one `DeviceOrder`. Cards must be readable at ≥ 2 m distance.

```
┌─ [overdue pulse border-left] ─────────────────────────────┐
│  TABLE 7           [Stage badge: Preparing]   ⏱ 08:42     │
│  Ticket #1042 · 4 guests                                   │
├────────────────────────────────────────────────────────────┤
│  ☑  Wagyu Set A  ×2                                        │
│  ☑  Wagyu Set B  ×1                                        │
│  ☐  Refill: Tongs  ×1          (note: urgent)              │
│                                                            │
│  Items done: 2 / 3  [██████████░░░░░]                      │
├────────────────────────────────────────────────────────────┤
│  [Mark as Served]                           [⋯ Void]       │
└────────────────────────────────────────────────────────────┘
```

### 4.1 Header row

| Element | Token / minimum size | Notes |
|---|---|---|
| Table name | `--text-kds-table` (≥ 22 px) | Bold, primary amber color |
| Stage badge | WorkflowStage variant of existing `OrderStatusBadge` | Pending = muted, Preparing = amber, Served = green |
| Elapsed timer | `--text-kds-timer` (≥ 22 px) | `mm:ss`; turns red when overdue |
| Ticket number | Body text | `#<order_number>` |
| Guest count | Body text | `· N guests`; can be empty/`—` until async POS detail arrives |

### 4.2 Item list

| Element | Token / size | Notes |
|---|---|---|
| Item name | `--text-kds-item` (≥ 18 px) | Uses `kitchen_name` from POS when set, else `name` |
| Quantity | `--text-kds-qty` (≥ 18 px) | `×N` bold suffix |
| Note | `--text-kds-note` (≥ 14 px) | Muted italic; only shown if present |
| Checkbox / tap target | ≥ 44×44 px | ☑ done, ☐ undone; tap toggles; done items show struck-through name (keep legible, do not hide) |

### 4.3 Progress bar

- Thin horizontal bar below the item list.
- Fill ratio = `done_items / total_items`.
- Color: amber while Preparing, green when all items are done.
- Height: 6 px Comfortable / 4 px Compact.

### 4.4 Action area

| Button | When shown | Style | Min tap target |
|---|---|---|---|
| "Start Preparing" | Stage = Pending | Primary — amber fill, full width | 44 px tall |
| "Mark as Served" | Stage = Preparing | Primary — amber fill, full width | 44 px tall |
| "Recall" | Stage = Served | Secondary — outlined | 44 px tall |
| "Void" | Any active stage | Ghost / destructive, small, pinned to trailing corner | 44×44 px |

"Void" opens a confirmation modal (see §8).

---

## 5. Filter Chip Strip

A single-select chip row immediately below the topbar.

| Chip | Count source | Active fill color |
|---|---|---|
| All Active | All non-voided/cancelled | Amber |
| ⚠ Overdue | Tickets with elapsed ≥ `prep_target_sec` | Red |
| Pending | Stage = Pending | Amber |
| Preparing | Stage = Preparing | Amber |
| Served | Stage = Served | Green |

- Inactive chips: outlined border, muted text.
- Counts react to the same in-memory ticket map — no separate fetch.

---

## 6. Color Tokens

### 6.1 Existing tokens (do not modify)

```css
--color-woosoo-accent:        #f6b56d   /* amber — primary interactive */
--color-woosoo-red:           #dc2626   /* destructive / overdue base */
--color-woosoo-green:         #16a34a   /* served / success */
--color-woosoo-dark-gray:     #252525   /* darkest foreground */
```

Dark card background: `--card = hsl(20 8% 13%)`
Dark sidebar background: `hsl(20 5% 8%)`
Dark muted foreground: `--muted-foreground = hsl(30 6% 55%)`

### 6.2 New tokens to add in `resources/css/app.css` (PR-0B / issue #144)

```css
/* Overdue urgency */
--overdue:               hsl(0 84% 58%);    /* pulsing border-left + timer text */
--overdue-foreground:    hsl(0 0% 98%);
--overdue-border-width:  4px;

/* Distance-read type scale — kitchen ≥ 2 m */
--text-kds-table:        28px;   /* table number */
--text-kds-timer:        26px;   /* elapsed timer */
--text-kds-item:         20px;   /* item name */
--text-kds-qty:          20px;   /* ×N quantity */
--text-kds-note:         15px;   /* item note */

/* Overdue pulse animation */
@keyframes overdue-pulse {
    0%, 100% { border-left-color: var(--overdue); opacity: 1; }
    50%       { border-left-color: var(--overdue); opacity: 0.5; }
}
--animate-overdue: overdue-pulse 1.4s ease-in-out infinite;
```

> Suppress animation when the user prefers reduced motion:
> `@media (prefers-reduced-motion: reduce) { .overdue-card { animation: none; } }`

### 6.3 Sidebar dim-text contrast fix (PR-0B — sidebar-scoped, not global)

Current `--muted-foreground` dark (`hsl(30 6% 55%)`) fails WCAG AA against the dark sidebar bg. Use a **sidebar-scoped token** rather than changing the global value (which affects tables, captions, etc. app-wide):

```css
/* Inside [data-sidebar] or .dark scope on sidebar elements only */
--sidebar-section-label:  hsl(30 8% 68%);   /* target: ≥ 4.5:1 on hsl(20 5% 8%) */
```

> ⚠️ **Designer: verify actual contrast ratios** against the current merged brand-alignment palette before finalizing HSL values. The numbers above are targets, not final — run them through a contrast checker on the real sidebar background.

---

## 7. Typography

These fonts are already established in `resources/css/app.css`:

| Role | Font family | CSS variable |
|---|---|---|
| Display / headings | Raleway | `--font-header` |
| Body / labels | Kanit | `--font-sans` |
| Data / numbers | Roboto Flex | `--font-primary` |

For the KDS specifically:
- **Timer and quantity values** → Roboto Flex (monospaced feel improves number readability at distance).
- **Item names, table name, labels** → Kanit.

---

## 8. Void Modal

Tapping the "Void" button on any active ticket opens a confirmation dialog (uses existing shadcn `Dialog`).

```
┌────────────────────────────────────┐
│  Void Order — Table 7              │
│                                    │
│  Select a reason:                  │
│  ○  Guest cancelled                │
│  ○  Allergy conflict               │
│  ○  Wrong table                    │
│  ○  Kitchen error                  │
│  ○  Other                          │
│                                    │
│  [Cancel]         [Confirm Void]   │
└────────────────────────────────────┘
```

- Reason is **required** — "Confirm Void" is disabled until one radio is selected.
- "Confirm Void" button: destructive style (red).
- On confirm: POSTs to `/kds/orders/{id}/void` with `{ reason }`.
- Reason is persisted in `OrderUpdateLog.meta` for the audit trail.

---

## 9. Audio Chime & Mute Toggle

- **Trigger:** `order.created` broadcast → play a single short chime (one tone, ~0.5 s, non-intrusive).
- **No chime** on status transitions or item toggles — new-ticket arrival only.
- **Mute toggle** in the topbar: bell icon. Tapping toggles chime on/off; state persisted in `localStorage`.
- **Browser autoplay policy:** The first user interaction (any tap) unlocks audio context. The mute toggle itself serves as this unlock.

---

## 10. Density Toggle

Toggled by a button in the topbar. Persisted in `localStorage`.

| Mode | Card padding | Item font size | Progress bar height | Min card width |
|---|---|---|---|---|
| **Comfortable** | 20 px | `--text-kds-item` (20 px) | 6 px | ~320 px |
| **Compact** | 12 px | 16 px | 4 px | ~260 px |

---

## 11. Elapsed Timer Behavior

- **One shared `setInterval(1000)`** drives all ticket timers — not one per card (performance requirement NFR-P-03).
- `elapsed = now − order.issued_at` (the `created_at` timestamp surfaced as `issued_at` in the broadcast payload).
- Format: `mm:ss` under 60 min → `H:mm:ss` for long-running tickets.
- **Overdue threshold:** `prep_target_sec` (configurable server-side, default **840 s / 14 min**).
- When overdue: timer text → red (`--overdue`), card shows pulsing left border (`--overdue-border-width`), card re-sorts to rank #1.

---

## 12. Multi-Client Sync

All `/kds` tabs reconcile through the `admin.orders` Reverb channel. The KDS holds a reactive `Map<orderId, ticket>` and mutates in place on each event.

| Broadcast event | What triggers it | KDS response |
|---|---|---|
| `order.created` | New order placed from tablet | Add ticket; play chime |
| `order.updated` | Status transition (confirm/bump/recall) | Update stage badge + re-sort |
| `order.voided` | Void confirmed | Remove ticket from queue |
| `item.toggled` | Per-item check-off | Flip `done` on item + refresh progress bar |
| `order.details.updated` | Async POS detail sync (NEX-013) | Update `guest_count`, `subtotal`, `total` — treat as eventually consistent; show `—` until received |

---

## 13. Accessibility & Touch Requirements

| Requirement | Target value | Where applied |
|---|---|---|
| Minimum tap target | 44×44 CSS px | All buttons, item checkboxes |
| Timer + table number size | ≥ 22 px | `--text-kds-timer`, `--text-kds-table` |
| WCAG AA contrast | ≥ 4.5:1 | Sidebar section labels; overdue text on dark card |
| Keyboard navigation | Not required (v1 is touch-primary) | — |
| Reduced motion | Suppress overdue pulse | `@media (prefers-reduced-motion: reduce)` |

---

## 14. Out of Scope — v1 Deferred Items

Do **not** design these for the v1 handoff:

- Ticket flags (VIP, Rush, Allergy, Course-N)
- Safety-mod pink styling for allergen items
- Station routing (cold / hot / grill / bar columns)
- Refill ticker / floor announcement bar
- Device-token auth for wall display (no admin session)
- Auto-confirm, per-dish prep targets, recall-window limit
- Per-shift audio mute persistence (local storage is sufficient)
- Bulk "mark all done"
- Collapse served column

---

## 15. Prerequisites — Must Land Before KDS Code

1. **PR-0A (#143)** — `WorkflowStage` enum + `stage()` mapping on `OrderStatus`; `workflow_stage` added to broadcast payload; `primaryActionForStage()` on the frontend; `pending→in_progress` and `in_progress→served` transition shortcuts enabled.
2. **PR-0B (#144)** — All new CSS tokens from §6.2 + sidebar contrast fix. Must be rebased onto the merged brand-alignment theme and reconciled with the open redesign PR #161 before being applied.

---

## 16. Critical Files for Implementers

| File | Change needed |
|---|---|
| `app/Enums/OrderStatus.php:17` | Add `SERVED → IN_PROGRESS` to `canTransitionTo()` (Recall); PR-0A adds the other two shortcuts |
| `app/Models/DeviceOrderItems.php` | Add `done`, `done_at` to `$fillable` + `$casts` |
| `app/Helpers/OrderBroadcastPayload.php` | Add `done`, `done_at` per item; add `issued_at`, `confirmed_at`, `served_at` at order level |
| `app/Broadcasting/BroadcastEvent.php` | Add `case ItemToggled = 'item.toggled'` |
| `app/Broadcasting/OrderBroadcaster.php` | Pattern reference for new `ItemToggled` dispatch method |
| `app/Events/Order/OrderStatusUpdated.php` | Template for new `ItemToggled` event class |
| `routes/channels.php:30` | `admin.orders` auth (`user->is_admin`) — confirmed present, no changes needed |
| `routes/web.php:59` | Add `Route::prefix('kds')` group inside existing `can:admin` middleware group |
| `resources/css/app.css` | Add PR-0B tokens (§6.2 + §6.3) |

---

## 17. Designer Handoff Checklist

Before passing mockups to engineering, verify:

- [ ] All interactive elements (buttons, checkboxes) have ≥ 44×44 px tap targets in the layout
- [ ] Table name and elapsed timer are ≥ 22 px in both Comfortable and Compact density modes
- [ ] Overdue state is visually distinct from Preparing (pulse border + red timer, not color alone)
- [ ] Void modal requires reason selection before "Confirm Void" is enabled
- [ ] Mute toggle icon clearly communicates current state (muted vs. active)
- [ ] Density toggle button label reflects the **current** mode (not the mode it switches to)
- [ ] All text passes WCAG AA (≥ 4.5:1) on the dark card background `hsl(20 8% 13%)`
- [ ] Guest count and order totals have an empty/loading state (POS detail is eventually consistent)
- [ ] Button labels match exactly: "Start Preparing" / "Mark as Served" / "Recall" (no synonyms)
- [ ] Overdue pulse animation is absent when `prefers-reduced-motion` is active
