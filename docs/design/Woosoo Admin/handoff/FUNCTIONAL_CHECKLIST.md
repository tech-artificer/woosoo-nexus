# Woosoo Nexus — Functional Implementation Checklist

> **Source of truth:** `Woosoo Nexus - Functional Guide.html`
> **App:** `Woosoo Admin.html` · **Source:** `admin-app.jsx`, `admin-core.jsx`, `admin-screens.jsx`
> **Generated:** 2026-06-13

**Status key:** 🔴 missing · 🟡 partial/cosmetic · 🟢 working
**Priority:** `HIGH` core to service · `MED` important · `LOW` nice-to-have

Tick each box when its **Verify** condition passes.

---

## 🔴 HIGH priority — implement first

### Orders (02 · Kitchen Dispatch)
- [ ] **Void / Cancel / Mark Complete** mutate session status & move the card between columns. — *Verify: Mark Complete moves the card to Completed.*
- [ ] **Print Receipt** on completed orders triggers a print job + toast.
- [ ] **Retry** on failed print jobs re-queues and updates status.

### POS (03 · Live Table View)
- [ ] **Terminal selector** (Krypton Main / Bar) switches the table set.
- [ ] **Void Session** & **Mark Paid** in the table drawer close out the table.

### Roles & Permissions (11 / 12)
- [ ] **Create / Edit / Delete Role** with permission-assignment UI.
- [ ] Role **Permissions** action manages granted abilities.
- [ ] **Add / Edit / Delete Permission** on the registry.

### Users (10 · Staff)
- [ ] **Invite User** (form + create) adds to the roster.
- [ ] **Save Changes** persists permission toggles; **Remove** deletes.

### Packages (07 · Dining Tiers)
- [ ] **New Package / Edit / Preview** for tiers.

### Tablet Categories (08 · Menu Sync)
- [ ] **Drag-to-reorder** with persistence — the advertised core feature.

---

## 🟡 MEDIUM priority

### Orders
- [ ] Toolbar **Filter** panel (status / table / time range).
- [ ] **Refresh** reloads order data (not just the chime).

### POS
- [ ] **New Order** and **Sync POS** actions.

### Users
- [ ] Roster **Search** box filters the list.

### Packages
- [ ] **New Config** + row editing on the Configs tab.

### Tablet Categories
- [ ] **New Category / Edit** and **Attach / Detach Menu**.

### Devices (09)
- [ ] **Add Device**, **Sync All**, **APK Download**.
- [ ] Per-device **View** and **Restart**.

### Monitoring (04)
- [ ] Wire **Refresh**, **View Jobs**, **Purge Failed**, **Print Audit**.
- [ ] **Purge All** confirm actually clears print events.

### Reports (13)
- [ ] **CSV / PDF export**.
- [ ] **Custom date-range** picker beyond week/month.

### Settings (15)
- [ ] Persist **all** settings fields (not just theme & sound).

### Menus (06)
- [ ] **Delete** inside the item editor.

### Dashboard (01)
- [ ] **Today / Week / Month** toggle changes the chart.

### Global chrome (00)
- [ ] **Global search** + **⌘K** shortcut.
- [ ] Real **branch switcher** (+ enable Branches page).

---

## 🟢 LOW priority

- [ ] Settings: **Reset to Defaults**.
- [ ] Menus: real image **upload / picker** (replace filename field).
- [ ] Menus: persist added/edited items (session-only today).
- [ ] Dashboard: wire **"View all"** on Recent Sessions.
- [ ] Global: topbar **refresh** + **notification bell**.
- [ ] Global: **sidebar collapse** toggle.

---

## Working today — do not regress 🟢
Navigation, theme toggle (persisted), Orders board + drawer + event filter, POS floor + drawer, Monitoring read-outs, **Reverb start/stop/restart** (fully working), Menus search/filter/availability/add/edit, Reports trend toggle, Configuration tiles, Settings theme/sound.

## Rules
1. **No restyle.** Match existing components, amber accent, fonts, toasts, modals. Visual rules live in `handoff/`.
2. **Don't break green items.** Re-run their Verify lines after each change.
3. **Persist state** within the session; reflect changes in the UI immediately.
4. Keep data flowing through existing structures; no backend unless asked.
