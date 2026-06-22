# Woosoo Nexus — Step 4 Implementation Instructions
## Page-by-Page Alignment

> **For:** Claude Code agent or developer implementing Step 4 of the Woosoo Nexus design alignment.
> **Scope:** Visual-only changes. No business logic, no API changes, no data model changes.
> **Risk:** Low. All changes are CSS class strings and template markup only.
> **Prerequisite:** Steps 1, 2, and 3 must be applied first.
> **Time estimate:** 5–10 minutes including build and QA.

---

## Context

Steps 1–3 fixed the global shell (sidebar, topbar, layout wrapper) and shared primitives (Badge, Button, Card, Tabs). Step 4 applies targeted fixes to the three highest-traffic pages so their internal layouts align with the established system:

1. **Dashboard** — icon containers were `rounded-2xl` (oversized); hero card border/opacity unified; card hover shadow tightened
2. **Orders** — WebSocket status pill used raw Tailwind colors (`bg-emerald-50`, `bg-yellow-50`, `bg-rose-50`) instead of brand tokens; Live Orders count badge used `bg-blue-600` (off-brand)
3. **Devices** — page had a double-wrapped glass-card layout (outer container with `max-w-[1600px]` + two inner sections each with their own `rounded-[28px] bg-card/95 backdrop-blur-xl`), inconsistent with Dashboard/Orders; `StatsCards` was rendering 2 items in a 4-column grid leaving empty columns

---

## Files to replace

| Source file (in this handoff folder) | Destination in the project |
|---|---|
| `Dashboard.vue` | `resources/js/pages/Dashboard.vue` |
| `Orders_Index.vue` | `resources/js/pages/Orders/Index.vue` |
| `Devices_Index.vue` | `resources/js/pages/Devices/Index.vue` |

> **Note on naming:** `Orders_Index.vue` and `Devices_Index.vue` use underscores to avoid path ambiguity in this flat handoff folder. Rename them at the destination.

---

## Step-by-step instructions

### 1. Back up existing files (recommended)
```bash
cp resources/js/pages/Dashboard.vue resources/js/pages/Dashboard.vue.bak
cp resources/js/pages/Orders/Index.vue resources/js/pages/Orders/Index.vue.bak
cp resources/js/pages/Devices/Index.vue resources/js/pages/Devices/Index.vue.bak
```

### 2. Copy the replacement files
```bash
cp handoff/step-4/Dashboard.vue       resources/js/pages/Dashboard.vue
cp handoff/step-4/Orders_Index.vue    resources/js/pages/Orders/Index.vue
cp handoff/step-4/Devices_Index.vue   resources/js/pages/Devices/Index.vue
```

### 3. Rebuild the frontend
```bash
npm run build
# or for development:
npm run dev
```

---

## What changed in each file

### `Dashboard.vue`
- Hero card: `rounded-[28px]` → `rounded-[26px]`; added `dark:border-white/10`; `bg-accent/15` → `bg-accent/12` (subtler tint)
- Session / Open Tables inner cards: `rounded-[24px]` → `rounded-[22px]`; `dark:bg-white/[0.08]` → `dark:bg-white/[0.06]`; removed duplicate `shadow-[0_22px_55px...]`
- Stat card icon containers: `h-11 w-11 rounded-2xl` → `h-10 w-10 rounded-xl` — consistent with `--radius` scale
- Card hover: removed redundant `border-black/8` class (Card.vue already supplies this after Step 3)
- All props, computed values, chart components, and logic are **unchanged**

### `Orders/Index.vue`
- Echo status pill — **connected**: `bg-emerald-50 text-emerald-700` → `bg-woosoo-green/10 text-woosoo-green`
- Echo status pill — **connecting**: `bg-yellow-50 text-yellow-700` → `bg-woosoo-accent/10 text-woosoo-primary-dark`
- Echo status pill — **disconnected**: `bg-rose-50 text-rose-700` → `bg-destructive/10 text-destructive`
- Echo dot — **connected**: `bg-emerald-500` → `bg-woosoo-green`
- Echo dot — **connecting**: `bg-yellow-500` → `bg-woosoo-accent`
- Echo dot — **disconnected**: `bg-rose-500` → `bg-destructive`
- Live Orders count badge: `bg-blue-600 text-white` → `bg-woosoo-accent text-woosoo-dark-gray`
- Removed stray `px-1 sm:px-2` from TabsContent (handled by parent spacing)
- All WebSocket logic, Echo listeners, order event handlers, and data flow are **unchanged**

### `Devices/Index.vue`
- **Removed** outer `<div class="mx-auto flex w-full max-w-[1600px] flex-col gap-8 px-4 pb-8 pt-6 sm:px-6 lg:px-8 lg:pt-8">` — this created double-wrapped layout since `AppContentLayout` already provides the max-width container
- Hero section: replaced `rounded-[28px] bg-card/95 shadow-sm backdrop-blur-xl` with `rounded-[26px] bg-card/92 backdrop-blur-sm` matching Dashboard pattern; added amber "Device management" label badge
- Stats: replaced `<StatsCards>` (4-col grid with 2 items) with inline `grid-cols-2` stat cards matching Dashboard's Session/OpenTables cards — `rounded-[18px] border-black/8 bg-white/72`; stats are constrained to `lg:w-1/2` to avoid stretching across the full width
- Table section: replaced `rounded-[28px] bg-card/95 backdrop-blur-xl` with `rounded-[26px] bg-card/92 backdrop-blur-sm`
- `StatsCards` import removed (no longer used)
- All device logic, dialog, `revealedSecurityCode`, and `openDeviceDetail` are **unchanged**

---

## QA checklist

### Dashboard
- [ ] Hero card has `rounded-[26px]` and subtle amber top gradient
- [ ] Session and Open Tables inner cards look proportional (slightly smaller radius than hero)
- [ ] Stat card icon containers are `rounded-xl` (not oversized `rounded-2xl`)
- [ ] Card hover lifts cleanly with shadow

### Orders
- [ ] "Live" status pill is green-tinted (brand green)
- [ ] "Connecting…" status pill is amber-tinted (brand amber)
- [ ] "Disconnected" status pill is destructive-red tinted
- [ ] Live Orders count badge is amber (`bg-woosoo-accent`) with dark text
- [ ] All WebSocket events still fire correctly (connect, order create, complete, etc.)

### Devices
- [ ] Page content is no longer double-wrapped in glass cards
- [ ] Hero section shows "Device management" label and matches Dashboard hero style
- [ ] Two stat cards are displayed side-by-side in a clean 2-col grid, left-anchored
- [ ] Table section has matching card treatment
- [ ] Create Device button still navigates to `route('devices.create')`
- [ ] DeviceDetailSheet still opens on row click
- [ ] Security code dialog still appears when `flash.security_code_reveal` is set

---

## What NOT to change

- Any other files in `resources/js/pages/` — only Dashboard, Orders/Index, Devices/Index are in scope
- `resources/js/components/Orders/`, `resources/js/components/Devices/` — component internals untouched
- `resources/js/components/Stats/StatsCards.vue` — still used on Orders page; no changes needed
- All PHP/Laravel backend files — completely out of scope

---

## Summary: all steps applied

| Step | Scope | Key changes |
|---|---|---|
| Step 1 | Sidebar & topbar shell | Amber active states, flat logo row, flat topbar |
| Step 2 | Layout & navigation | Dark mode toggle in topbar, refined content wrapper |
| Step 3 | UI primitives | Badge warning variant, Button radius, Card border, Tabs style |
| Step 4 | Page-by-page | Dashboard radius, Orders brand tokens, Devices layout fix |

---

*Generated by Woosoo Design · May 2026*
