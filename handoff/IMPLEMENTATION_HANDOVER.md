# Woosoo Admin — UI/UX Implementation Handover
## Complete Interface Improvement Reference

> **Date:** May 30, 2026  
> **Scope:** Full admin panel — every page, every issue, every specific change.  
> **Prerequisites:** Apply Steps 1–4 from `handoff/step-1/` through `handoff/step-4/` first. This document covers the *remaining* work beyond those four steps.  
> **Approach:** Visual alignment only. No business logic, routing, or data model changes.

---

## How to read this document

Each page section contains:
- **File path** — exact location in `resources/js/`
- **Current problems** — what specifically is wrong and why
- **Required changes** — exact class strings to swap, with before → after
- **Priority** — `HIGH` (visible inconsistency on primary pages), `MED` (secondary pages), `LOW` (admin/utility pages)

---

## Design system quick reference

These are the brand tokens and patterns used throughout the system. Every change in this document maps back to one of these.

### Color tokens (defined in `app.css`)

| Token | Value | Use |
|---|---|---|
| `woosoo-accent` | `#F6B56D` | Amber — primary actions, active states, badges |
| `woosoo-primary-dark` | `#B08047` | Amber dark — hover, warning text |
| `woosoo-primary-light` | `#FCD8BA` | Amber light — subtle tints |
| `woosoo-dark-gray` | `#252525` | Text on amber backgrounds |
| `woosoo-green` | `#16A34A` | Success / live status |
| `woosoo-blue` | `#2563EB` | Info / accent states |
| `woosoo-red` | `#DC2626` | Alias for destructive |

### Raw Tailwind colors to NEVER use (replace with tokens above)

```text
text-emerald-*  bg-emerald-*  → woosoo-green
text-yellow-*   bg-yellow-*   → woosoo-accent / woosoo-primary-dark
text-amber-*    bg-amber-*    → woosoo-accent / woosoo-primary-dark (context-dependent)
text-blue-600   bg-blue-600   → woosoo-blue
text-rose-*     bg-rose-*     → destructive
text-orange-*                 → woosoo-accent  (amber is the closest brand token)
text-purple-*   text-indigo-* → no brand equivalent; use woosoo-blue
```

### Structural patterns

**Page wrapper** — replaces all bare `p-6`, `mx-auto flex ... px-4 pb-8 pt-6` patterns:
```html
<div class="space-y-5">
```

**Hero card** — the top descriptive section every primary page uses:
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
  <div class="relative space-y-3">
    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
      [Section label]
    </span>
    <div>
      <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Page title</h1>
      <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Short description.</p>
    </div>
  </div>
</div>
```

**Table card** — the card wrapping any DataTable:
```html
<div class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
  <div class="p-4 sm:p-6">
    <DataTable ... />
  </div>
</div>
```

**Inline mini stat card** — used when 2–3 stats don't justify a full StatsCards row:
```html
<div class="rounded-[22px] border border-black/8 bg-white/72 px-4 py-4 dark:border-white/10 dark:bg-white/[0.06]">
  <p class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">Label</p>
  <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums">Value</p>
  <p class="mt-1 text-sm text-muted-foreground">Subtitle</p>
</div>
```

**Status/connection pill** — branded connection indicators:
```html
<!-- Connected -->
<span class="inline-flex items-center gap-1.5 rounded-full bg-woosoo-green/10 px-2.5 py-1 text-xs font-medium text-woosoo-green">
  <span class="h-1.5 w-1.5 rounded-full bg-woosoo-green"></span>
  Live
</span>

<!-- Connecting -->
<span class="inline-flex items-center gap-1.5 rounded-full bg-woosoo-accent/10 px-2.5 py-1 text-xs font-medium text-woosoo-primary-dark">
  <span class="h-1.5 w-1.5 rounded-full bg-woosoo-accent animate-pulse"></span>
  Connecting…
</span>

<!-- Disconnected / Error -->
<span class="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive">
  <span class="h-1.5 w-1.5 rounded-full bg-destructive"></span>
  Disconnected
</span>
```

**Brand table rows** — replaces bare `<table class="w-full text-sm">`:
```html
<table class="w-full text-sm">
  <thead>
    <tr class="border-b border-black/8 dark:border-white/10">
      <th class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase">Col</th>
    </tr>
  </thead>
  <tbody>
    <tr class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]">
      <td class="px-4 py-3">Value</td>
    </tr>
  </tbody>
</table>
```

---

## Pages — detailed change log

---

### 1. `pages/reports/Index.vue`
**Priority: HIGH** | Report landing hub — seen by all admin users navigating analytics.

**Problems:**
1. Page wrapper is `<div class="p-6 space-y-6">` — no hero section, starts with raw cards
2. Icon colors use raw Tailwind: `text-emerald-500`, `text-orange-500`, `text-blue-500`, `text-purple-500`, `text-indigo-500`, `text-rose-500`, `text-amber-500`
3. Cards use `class="border border-border"` — inconsistent with brand `border-black/8`
4. Hover state `hover:border-primary/30` jumps to `hover:bg-primary hover:text-primary-foreground` on the inner Button — jarring; should be a subtler amber lift

**Required changes:**

**Wrapper:** Replace `<div class="p-6 space-y-6">` with:
```html
<div class="space-y-5">
```

**Add hero section** as first child:
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
  <div class="relative space-y-3">
    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Analytics</span>
    <div>
      <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Reports</h1>
      <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Sales performance, guest trends, print audit trails, and operational breakdowns across the active date range.</p>
    </div>
  </div>
</div>
```

**Icon color map** (in the `reportLinks` array, change `color` values):
```
'text-emerald-500'  → 'text-woosoo-green'
'text-orange-500'   → 'text-woosoo-accent'
'text-blue-500'     → 'text-woosoo-blue'
'text-purple-500'   → 'text-woosoo-blue'       (no purple token; blue is closest)
'text-indigo-500'   → 'text-woosoo-blue'
'text-rose-500'     → 'text-destructive'
'text-amber-500'    → 'text-woosoo-accent'
```

**Card classes:** Remove `border border-border` from each card's `:class`. Card.vue (Step 3) already supplies `border-black/8` — double-specifying overrides it with the weaker `border-border`.

**Button inside card:** Change:
```html
<Button variant="outline" size="sm" as-child class="w-full group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
```
to:
```html
<Button variant="outline" size="sm" as-child class="w-full group-hover:border-woosoo-accent/50 group-hover:text-foreground transition-colors">
```

---

### 2. `pages/reports/DailySales.vue`
**Priority: HIGH** | Primary financial report — reviewed daily by management.

**Problems:**
1. Entire content in `<div class="p-6 space-y-6">` — no brand card wrapping, just floats on the page background
2. Header is a raw `<h1 class="text-3xl font-bold">` — `font-bold` is not in the brand type scale; all headings use `font-semibold` with `font-header` for display text
3. Summary cards use bare `<Card>` — fine after Step 3, but padding class is `pb-3` on CardHeader; Step 3 changes CardHeader padding to `p-5 pb-1`
4. Data table uses bare `<tr class="border-b hover:bg-muted/50">` — not using brand table row pattern
5. No date range filter/picker — the `startDate` and `endDate` props exist but are never surfaced to the user in the UI; there is no way to change the report window

**Required changes:**

**Wrapper:** Replace `<div class="p-6 space-y-6">` with `<div class="space-y-5">`.

**Header section:** Replace the bare header div:
```html
<!-- BEFORE -->
<div class="flex items-center justify-between">
  <div>
    <h1 class="text-3xl font-bold">{{ props.title }}</h1>
    <p class="text-sm text-muted-foreground mt-1">Analyze daily sales performance and trends</p>
  </div>
</div>
```
with the hero card pattern (see Global Patterns above), using `"Analytics · Daily Sales"` as the label and `props.title` as the `<h1>` text.

**Table rows:** In the data table `<tbody>`, replace `class="border-b hover:bg-muted/50"` with the brand table row class:
```
class="border-b border-black/6 transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]"
```

**Table header row:** Replace `class="border-b"` on `<tr>` with `class="border-b border-black/8 dark:border-white/10"`.

**Table header cells:** Replace `class="text-left py-3 px-4 font-semibold"` with `class="px-4 py-3 text-left text-xs font-semibold tracking-[0.1em] text-muted-foreground uppercase"`.

**Add date range UI:** The `startDate` / `endDate` props exist but are not shown. Add a filter row above the summary cards:
```html
<div class="flex flex-wrap items-center gap-3">
  <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Date range:</span>
  <span class="text-sm font-medium">{{ props.startDate ?? '—' }}</span>
  <span class="text-muted-foreground">→</span>
  <span class="text-sm font-medium">{{ props.endDate ?? 'today' }}</span>
</div>
```
(Full date picker wiring is a backend concern; the display is a visual change only.)

---

### 3. `pages/reports/HourlySales.vue`
**Priority: MED** | Same structural issues as DailySales.

**Problems:** Identical to DailySales (1–4 above), plus:
5. Peak hour row highlight: `class="bg-amber-50"` — raw Tailwind; breaks in dark mode (amber-50 is light cream)

**Required changes:** Apply the same wrapper, header, table header, table row changes as DailySales, plus:

**Peak row highlight:** Replace `:class="{ 'bg-amber-50': peakHour && row.hour === peakHour.hour }"` with:
```html
:class="{ 'bg-woosoo-accent/8 dark:bg-woosoo-accent/6': peakHour && row.hour === peakHour.hour }"
```

---

### 4. `pages/reports/GuestCount.vue`
**Priority: MED**

**Problems:** Same structural issues as DailySales (1–4). Additionally:
5. "Busiest Day" card has a redundant inner card: `<div class="bg-card border rounded-lg p-4">` wraps the value — this creates a card-inside-a-card which adds visual weight for no reason.

**Required changes:** Apply wrapper/header/table changes. Remove the inner `<div class="bg-card border rounded-lg p-4">` — render the value and date directly in CardContent like all other summary cards.

---

### 5. `pages/reports/MenuItems.vue`
**Priority: MED**

**Problems:** Same structural issues (1–4), plus:
5. "Top 5 Revenue Generators" list items: `class="flex items-center justify-between p-3 border rounded-lg"` — bare `border rounded-lg` not using brand tokens
6. "Package Best Sellers" and "All Items" tables have same raw table style issues

**Required changes:** Apply wrapper/header/table changes. For list items, change:
```
class="flex items-center justify-between p-3 border rounded-lg"
```
to:
```
class="flex items-center justify-between rounded-xl border border-black/8 px-4 py-3 dark:border-white/10"
```

---

### 6. `pages/reports/OrderStatus.vue`
**Priority: MED**

**Problems:** Same structural issues (1–4), plus:
5. `getStatusColor` function maps status to `'default' | 'secondary' | 'outline'` Badge variants — missing the `success`, `warning`, and `destructive` variants added in Step 3
6. Status breakdown list items: `class="flex items-center justify-between p-4 border rounded-lg"` — same bare border issue

**Required changes:** Apply wrapper/header/table changes. Update `getStatusColor`:
```ts
// BEFORE
const getStatusColor = (status: string) => {
  return status === 'COMPLETED' ? 'default' : status === 'CONFIRMED' ? 'secondary' : 'outline'
}

// AFTER
const getStatusColor = (status: string): 'success' | 'warning' | 'destructive' | 'secondary' | 'outline' => {
  const s = status.toUpperCase()
  if (s === 'COMPLETED') return 'success'
  if (s === 'CONFIRMED' || s === 'SERVED' || s === 'READY') return 'secondary'
  if (s === 'VOIDED' || s === 'CANCELLED') return 'destructive'
  if (s === 'PENDING' || s === 'IN_PROGRESS') return 'warning'
  return 'outline'
}
```

Fix breakdown list items (same as MenuItems change above).

---

### 7. `pages/reports/PrintAudit.vue`
**Priority: MED**

**Problems:** Same structural issues (1–4), plus:
5. Status badge logic inline in template uses `v-if/v-else-if/v-else` per row — verbose; can use the same `getStatusColor` helper above
6. `device_id` column shows raw numeric ID — not useful to staff; rename column header to "Device" and consider adding device name if props allow

**Required changes:** Apply wrapper/header/table changes. Extract status badge helper (same pattern as OrderStatus).

---

### 8. `pages/reports/DiscountTax.vue`
**Priority: MED**

**Problems:** Same structural issues (1–4). Additionally:
5. "Period Summary" card at the bottom renders bare `<div>` children with no visual separation — dense and hard to scan

**Required changes:** Apply wrapper/header/table changes. In the Period Summary grid, change child divs from plain `<div>` to add a bottom separator:
```html
<div class="rounded-[18px] border border-black/8 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/[0.04]">
  <div class="text-xs font-semibold uppercase tracking-[0.15em] text-muted-foreground">Total Orders</div>
  <div class="mt-1 text-2xl font-semibold tabular-nums">{{ totalOrders }}</div>
</div>
```
(Apply to each of the 6 summary items in the grid.)

---

### 9. `pages/ServiceRequests/Index.vue`
**Priority: HIGH** | High-traffic operational page used during service.

**Problems:**
1. Page wrapper is `<div class="space-y-6 px-1 sm:px-2">` — `px-1 sm:px-2` is vestigial padding from a pre-AppContentLayout era; AppContentLayout already provides horizontal padding. Remove it.
2. Page jumps directly to `<DataTableToolbar>` — no hero section, no page identity
3. `StatsCards` is rendered mid-page without a containing card — the stats float between the toolbar and the table
4. No brand hero / label at top

**Required changes:**

**Remove stale wrapper padding:** Change `<div class="space-y-6 px-1 sm:px-2">` to `<div class="space-y-5">`.

**Add hero section** as first child (before `DataTableToolbar`):
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="space-y-1.5">
      <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Operations</span>
      <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Service Requests</h1>
      <p class="max-w-xl text-sm leading-6 text-muted-foreground">Guest-initiated assistance requests from tablet devices. Pending items require attention from floor staff.</p>
    </div>
  </div>
</div>
```

**Wrap StatsCards in a containing section:** The stats should live inside the hero or immediately below in a separate inner card (not floating). Consider moving stats inline:
```html
<StatsCards :stats="localStats" class="pt-1" />
```
(StatsCards already uses its own card grid; just ensure `gap-4` is maintained by removing extra `space-y-6` wrapping.)

---

### 10. `pages/Menus/Index.vue`
**Priority: HIGH** | Core operational page — menus edited frequently.

**Problems:**
1. Wrapper is `<div class="flex h-full flex-1 flex-col gap-6">` — uses flex height-fill which behaves inconsistently when content is short
2. Header is a bare `<div class="flex items-center justify-between">` with `<h1 class="text-2xl font-bold tracking-tight">` — `font-bold` off-brand; no hero card; no brand label
3. No hero card wrapping the header — jumps from breadcrumb to bare text
4. `StatsCards` floats without a containing card

**Required changes:**

**Replace wrapper:**
```html
<!-- BEFORE -->
<div class="flex h-full flex-1 flex-col gap-6">
<!-- AFTER -->
<div class="space-y-5">
```

**Replace header section** with the hero card pattern:
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
  <div class="relative space-y-3">
    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Menu management</span>
    <div>
      <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Menus</h1>
      <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Manage menu items, update availability, and review item-level detail across all categories.</p>
    </div>
  </div>
</div>
```

**Table card wrapper:** Wrap `<DataTable :data="menus" :columns="columns" />` in the table card pattern.

---

### 11. `pages/Users/Index.vue`
**Priority: HIGH** | Admin user management — not frequently visited but needs consistency.

**Problems:**
1. Wrapper is `<div class="space-y-6">` (fine) but header uses `<h1 class="text-2xl font-bold tracking-tight">` — `font-bold` off-brand
2. No hero card — header floats uncontained
3. Pagination buttons at bottom use raw classes: `class="px-3 py-1.5 text-sm rounded-md border bg-background hover:bg-accent transition-colors"` — bare `border bg-background`; active state: `bg-primary text-primary-foreground` which is fine but the inactive state can use brand glass styling
4. Commented-out `<!-- <pre> {{ users }} </pre> -->` debug line should be removed
5. `StatsCards` uses `variant: 'danger'` which doesn't exist in badge/card variants — should be `variant: 'destructive'`

**Required changes:**

**Wrapper:** Keep `<div class="space-y-6">` but add hero card as first child:
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="space-y-1.5">
      <span class="...">User management</span>
      <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Users</h1>
      <p class="...">Manage staff accounts, roles, and branch access.</p>
    </div>
    <Link :href="route('users.create')">
      <Button><Plus class="mr-2 h-4 w-4" /> Add User</Button>
    </Link>
  </div>
</div>
```
Then remove the old header div and the Add User button from the template body.

**Pagination buttons:** Replace bare pagination with brand-consistent buttons:
```html
<button
  v-for="link in paginationLinks"
  :key="link.label"
  @click.prevent="goto(link)"
  class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-black/8 bg-white/72 px-2.5 text-sm transition-colors hover:bg-woosoo-accent/10 dark:border-white/10 dark:bg-white/[0.04]"
  :class="{ 'border-woosoo-accent/40 bg-woosoo-accent/12 font-semibold text-woosoo-primary-dark dark:bg-woosoo-accent/15': link.active }"
  v-html="link.label"
/>
```

**Fix StatsCards variant:** Change `variant: 'danger'` to `variant: 'destructive'` in both Users/Index and wherever else `danger` appears. `danger` is not a registered CVA variant.

**Remove debug comment:** Delete `<!-- <pre> {{ users }} </pre> -->`.

---

### 12. `pages/Users/Create.vue` and `pages/Users/Edit.vue`
**Priority: MED**

**Problems:**
1. Wrapper: `<div class="flex h-full flex-1 flex-col gap-4 rounded p-6">` — `rounded p-6` applied to the wrapper (not a card), creating padding without visual containment; inconsistent
2. Page title `<h2 class="text-lg font-semibold">` is bare, no hero card, no breadcrumb label distinction

**Required changes:**

Replace the wrapper and heading in both files:
```html
<!-- BEFORE -->
<div class="flex h-full flex-1 flex-col gap-4 rounded p-6">
  <h2 class="text-lg font-semibold">Create User</h2>
  <UserForm form-type="create" />
</div>

<!-- AFTER -->
<div class="space-y-5">
  <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">User management</span>
    <h1 class="mt-3 font-header text-2xl font-semibold tracking-tight text-foreground">Create User</h1>
  </div>
  <div class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
    <div class="p-5 sm:p-6">
      <UserForm form-type="create" />
    </div>
  </div>
</div>
```
Apply the same pattern to Edit.vue (change "Create User" → "Edit User").

---

### 13. `pages/Packages/Index.vue`
**Priority: MED**

**Problems:**
1. Wrapper: `<div class="space-y-6 p-6">` — `p-6` is redundant; AppContentLayout already provides the inner padding
2. No hero section — jumps straight to the create/edit form card
3. `AlertDialogAction class="bg-destructive hover:bg-destructive/90"` — fine, matches the default destructive intent; keep as-is
4. Form card uses `<Card>` which is correct after Step 3, but `CardContent` padding isn't adjusted

**Required changes:**

**Remove extra p-6:** Change `<div class="space-y-6 p-6">` → `<div class="space-y-5">`.

**Add hero card** before the form card:
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
  <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Configuration</span>
  <h1 class="mt-3 font-header text-2xl font-semibold tracking-tight text-foreground">Packages</h1>
  <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">Configure meal packages, map modifier items, and control display order for guest-facing menus.</p>
</div>
```

---

### 14. `pages/branches/IndexBranches.vue`
**Priority: MED**

**Problems:**
1. Wrapper: `<div class="p-6 space-y-6">` — stale padding
2. No hero section — renders DataTable immediately without context
3. No page title visible (Head sets "Branches" but there's no `<h1>` in the template)

**Required changes:**

**Replace wrapper:** `<div class="space-y-5">`.

**Add hero card:**
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="space-y-1.5">
      <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Configuration</span>
      <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Branches</h1>
      <p class="max-w-xl text-sm leading-6 text-muted-foreground">Manage branch locations, their device allocations, and associated users.</p>
    </div>
    <Button @click="handleAdd"><Plus class="mr-2 h-4 w-4" /> Add Branch</Button>
  </div>
</div>
```
(Import `Plus` from `lucide-vue-next` and `Button` from `@/components/ui/button` — both likely already available.)

**Wrap DataTable** in the table card pattern.

---

### 15. `pages/roles/IndexRoles.vue`
**Priority: MED**

**Problems:**
1. Wrapper: `<div class="p-6 space-y-6">` — stale padding
2. Header `<h1 class="text-2xl font-bold tracking-tight">` — `font-bold` off-brand
3. No hero card

**Required changes:**

Replace wrapper with `<div class="space-y-5">`. Replace bare header div with the hero card pattern, label: `"Access control"`. Move the "New Role" button into the hero card's right side (same as Users/Index pattern).

---

### 16. `pages/roles/CreateRole.vue` and `pages/roles/EditRole.vue`
**Priority: LOW**

**Problems:**
1. `<div class="max-w-2xl mx-auto p-6 space-y-6">` — `max-w-2xl mx-auto` centers the content at a small width; on large screens this leaves a lot of empty space on both sides inside the content card
2. `<h1 class="text-2xl font-bold tracking-tight">` — `font-bold` off-brand
3. The Back button uses `variant="ghost"` which is correct; keep

**Required changes:**

Remove `max-w-2xl mx-auto` — the content width is already constrained by AppContentLayout. Change `font-bold` → `font-semibold`. Add hero card pattern with label `"Access control"`. Wrap the form Card in `<div class="space-y-5">`.

---

### 17. `pages/EventLogs/Index.vue`
**Priority: LOW**

**Problems:**
1. Badge color classes set directly: `getLevelColor` returns `'bg-red-500'`, `'bg-yellow-500'`, `'bg-blue-500'`, `'bg-gray-500'` — raw Tailwind applied as a class string to an existing `<Badge>` component, bypassing the CVA system
2. Super-admin raw warning: `class="p-3 bg-yellow-500/10 border border-yellow-500/20 rounded"` — raw Tailwind; should use brand tokens
3. Log viewer container `class="rounded-lg border bg-card"` — `rounded-lg` is `--radius-lg` which maps to `--radius` = 0.5rem; should be `rounded-[26px]` to match page content cards
4. No hero card / page title in template (page has no `<h1>`)

**Required changes:**

**`getLevelColor`:** Change return values to use brand tokens:
```ts
const getLevelColor = (level: string): string => {
  if (level.includes('ERROR'))   return 'bg-destructive'
  if (level.includes('WARNING')) return 'bg-woosoo-accent'
  if (level.includes('INFO'))    return 'bg-woosoo-blue'
  return 'bg-muted-foreground'
}
```
Keep `class="text-white shrink-0"` on the Badge.

**Super-admin warning:** Change:
```
class="flex items-center gap-2 p-3 bg-yellow-500/10 border border-yellow-500/20 rounded"
```
to:
```
class="flex items-center gap-2 rounded-xl border border-woosoo-accent/25 bg-woosoo-accent/10 p-3"
```

**Log container:**
```
class="rounded-lg border bg-card"
```
→
```
class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10"
```

**Add page title** as a hero section before the filter row.

---

### 18. `pages/Monitoring/Index.vue`
**Priority: MED** | Technical ops — used by super-admins during incidents.

**Problems:**
1. Wrapper: `<div class="space-y-6">` — missing hero pattern (but page has its own `<h1>`)
2. `<h1 class="text-3xl font-bold tracking-tight">` — `font-bold` off-brand
3. `deviceStateColor` returns raw Tailwind: `'bg-emerald-500'`, `'bg-yellow-500'`, `'bg-red-500'`, `'bg-gray-400'`
4. Alert banner `class="bg-destructive/10 border border-destructive/20 rounded-lg p-4"` uses `rounded-lg` — should be `rounded-[20px]` to match page card radii
5. Print latency table with `p-2` cell padding — tighter than brand `p-3` / `px-4 py-3`
6. Auto-refresh button `:class="{ 'bg-primary/10': autoRefresh }"` — correct semantic; fine
7. SVG inline icons for refresh/spinner — consider replacing with `lucide-vue-next` `RefreshCw` and `Loader2` for consistency (all other pages use lucide)

**Required changes:**

**Header:** Remove the bare `<div class="flex items-center justify-between">` with raw `<h1>`. Replace with:
```html
<div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="space-y-1.5">
      <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">System health</span>
      <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">System Monitoring</h1>
      <p class="max-w-2xl text-sm leading-6 text-muted-foreground">Real-time order processing, print failure tracking, and session management.</p>
    </div>
    <div class="flex items-center gap-3">
      <!-- existing toggle + refresh buttons, unchanged -->
    </div>
  </div>
</div>
```

**`deviceStateColor`:**
```ts
// BEFORE
const deviceStateColor = (state: string): string => {
  if (state === 'green') return 'bg-emerald-500';
  if (state === 'yellow') return 'bg-yellow-500';
  if (state === 'red') return 'bg-red-500';
  return 'bg-gray-400';
};

// AFTER
const deviceStateColor = (state: string): string => {
  if (state === 'green')  return 'bg-woosoo-green';
  if (state === 'yellow') return 'bg-woosoo-accent';
  if (state === 'red')    return 'bg-destructive';
  return 'bg-border';
};
```

**Alert banner radius:** `rounded-lg` → `rounded-[20px]`.

**SVG icons:** Replace the two inline SVG `<path>` refresh icons with:
```html
<RefreshCw class="h-4 w-4 mr-2" :class="{ 'animate-spin': autoRefresh }" />
```
Import: `import { RefreshCw } from 'lucide-vue-next'`.

---

### 19. `pages/POS/Index.vue`
**Priority: HIGH** | POS is used in real-time during service hours.

**Problems:**
1. Outer wrapper is `<div class="mx-auto flex w-full max-w-[1600px] flex-col gap-8 px-4 pb-8 pt-6 sm:px-6 lg:px-8 lg:pt-8">` — same double-wrap problem as Devices was before Step 4
2. Hero section: `rounded-[28px] border border-border/60 bg-card/95 ... backdrop-blur-sm` — `rounded-[28px]` (should be `[26px]`), `border-border/60` (should be `border-black/8`), `bg-card/95` (should be `bg-card/92`)
3. Stat mini cards (4-col session row): `rounded-2xl border border-border/60 bg-card/95` — same fixes
4. "Krypton Only" badge: `rounded-2xl border border-emerald-400/30 bg-emerald-500/10 text-emerald-300` — raw emerald; should use brand green tokens
5. Terminal selector section: uses `rounded-[28px] border border-border/60 bg-card/95` — same fixes
6. Terminal cards interior: `bg-gradient-to-b from-slate-900/95 to-slate-950` dark-themed cards for terminals — this is intentional (device representation); keep but tighten border: `border-border/60` → `border-white/12`
7. Tables section: same `rounded-[28px] border border-border/60 bg-card/95` issues
8. Error state: `rounded-xl border border-rose-300/40 bg-rose-500/10 text-rose-700 dark:text-rose-300` — raw rose; should use `border-destructive/30 bg-destructive/10 text-destructive`

**Required changes:**

**Remove outer wrapper** `mx-auto flex w-full max-w-[1600px]...` — replace with `<div class="space-y-5">`.

**All section cards:** Change:
- `rounded-[28px]` → `rounded-[26px]`
- `border-border/60` → `border-black/8 dark:border-white/10`
- `bg-card/95` → `bg-card/92`

**Stat mini cards (4-col):** Change `rounded-2xl` → `rounded-[22px]`, apply same border/bg fixes.

**Krypton badge:**
```
class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 ... text-emerald-300"
```
→
```
class="rounded-full border border-woosoo-green/30 bg-woosoo-green/10 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-woosoo-green"
```

**Error states:**
```
class="rounded-xl border border-rose-300/40 bg-rose-500/10 ... text-rose-700 dark:text-rose-300"
```
→
```
class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
```

**Terminal `ring-2 ring-primary/70` selected state** — `primary` in light mode is `woosoo-primary-dark` (#B08047) — correct; keep.

---

### 20. `pages/Admin/Settings.vue`
**Priority: LOW**

**Problems:**
1. Wrapper: `<div class="max-w-2xl mx-auto p-6 space-y-6">` — same narrow centering issue as Roles/Create
2. `<h1 class="text-2xl font-bold tracking-tight">` — `font-bold` off-brand
3. Otherwise well-structured (Card sections, Switch controls, Input fields) — nothing else to fix

**Required changes:**

Remove `max-w-2xl mx-auto` from wrapper. Change `font-bold` → `font-semibold`. Add hero card with label `"Configuration"`.

---

### 21. `pages/settings/Profile.vue`
**Priority: LOW**

No structural issues. The profile page already uses brand amber focus rings, correct Input styling, and the SettingsLayout. The `isDefaultAdminEmail` warning uses `border-amber-200/80 bg-amber-50` — change these to brand tokens:
```
border-amber-200/80 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-100
```
→
```
border-woosoo-accent/30 bg-woosoo-accent/10 text-woosoo-primary-dark dark:border-woosoo-accent/25 dark:bg-woosoo-accent/8 dark:text-woosoo-primary-light
```

---

### 22. `pages/auth/Login.vue`
**Priority: LOW** — Already the most brand-aligned page in the application. No changes required.

---

### 23. `pages/Devices/Create.vue` and `pages/Devices/Edit.vue`
**Priority: LOW** — Not read in this pass; assume same pattern as Users/Create + Edit. Apply hero card + table card wrapper. Remove any `p-6 rounded` wrappers.

---

### 24. `pages/Permissions/Index.vue`
**Priority: LOW** — Not read in this pass but follows the same `p-6 space-y-6` pattern as Roles. Apply hero card + wrapper fix.

---

## Components — additional fixes

### `components/Stats/StatsCards.vue`
**Priority: HIGH**

This component is used on Orders, Devices, Menus, Users, and ServiceRequests. A single fix here cascades to all.

**Problem:** The component renders its cards in a fixed 4-column grid regardless of how many cards are passed. When only 2 cards are passed (Devices, after Step 4 this is fixed inline, but ServiceRequests and Menus still use it), 2 empty columns create a visually broken layout.

**Required change:** Inspect the component's grid definition. Change from `grid-cols-4` to `grid-cols-[repeat(auto-fill,minmax(200px,1fr))]` or `grid-cols-2 sm:grid-cols-4`. This way 2 cards fill the left half naturally, and 4 cards span the full row.

Also confirm `variant: 'danger'` → `variant: 'destructive'` in all callers passing that variant (see Users/Index fix above).

---

### `components/Orders/OrderStatusBadge.vue`
**Priority: MED**

This component likely maps order statuses to badge variants. It was not read in this pass. Check that it uses the Step-3 variants (`success`, `warning`, `destructive`) rather than raw color classes or pre-Step-3 `default/secondary/outline` only.

---

### `components/NavMain.vue`
**Priority: LOW — verify only**

Step 1 aligns the active state. Verify that the `Reports` sub-nav (accordion) item uses the same amber active color when a sub-route is active, not just the parent. The `isActive` prop on `analyticsNavItems` is set on the parent only; child items don't feed back up to the parent's `isActive`.

---

## Implementation order (recommended)

Apply in this order to minimize visual regressions:

| Priority | Pages | Reason |
|---|---|---|
| 1 | `StatsCards.vue` grid fix | Affects many pages simultaneously |
| 2 | `pages/reports/Index.vue` | Hub page — gateway to all reports |
| 3 | `pages/ServiceRequests/Index.vue` | Operational; used during service |
| 4 | `pages/Menus/Index.vue` | Operational; used frequently |
| 5 | `pages/POS/Index.vue` | Operational; used during service |
| 6 | All reports sub-pages (DailySales → DiscountTax) | Batch — all share same pattern |
| 7 | `pages/Users/Index.vue`, Create, Edit | Admin; less time-critical |
| 8 | `pages/Packages/Index.vue` | Admin |
| 9 | `pages/Monitoring/Index.vue` | Super-admin |
| 10 | Branches, Roles, EventLogs, Settings | Configuration; rarely visited |

---

## QA checklist (full sweep)

After all changes are applied and `npm run build` completes:

- [ ] All page hero cards have `rounded-[26px]` and `border-black/8`
- [ ] All DataTable wrappers have the table card pattern (no bare `section` or `p-6` divs)
- [ ] No raw `font-bold` on display headings — all use `font-semibold`
- [ ] No `text-emerald-*` / `bg-emerald-*` remaining — replaced with `woosoo-green`
- [ ] No `text-yellow-*` / `bg-yellow-*` remaining — replaced with `woosoo-accent`
- [ ] No `bg-amber-50` / `text-amber-*` remaining — replaced with amber brand tokens
- [ ] No `bg-blue-600` / `text-blue-*` remaining — replaced with `woosoo-blue`
- [ ] No `bg-rose-*` / `text-rose-*` remaining — replaced with `destructive`
- [ ] No `bg-purple-*` / `bg-indigo-*` remaining — replaced with `woosoo-blue`
- [ ] StatsCards grid works with 2, 3, and 4 cards
- [ ] `variant: 'danger'` not passed anywhere — changed to `'destructive'`
- [ ] Pagination buttons use brand glass styling
- [ ] Reports peak-row highlight works in dark mode
- [ ] All connection/status pills use brand tokens
- [ ] POS terminal selected state still shows ring
- [ ] DeviceDetailSheet, OrderDetailSheet, ServiceRequestDetailSheet still open on row click (visual-only change, behavior unchanged)
- [ ] Dark mode: all pages render correctly — check border visibility, text contrast, amber tints

---

*Generated by Woosoo Design · May 30, 2026*
