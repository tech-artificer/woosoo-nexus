# Woosoo Nexus — Step 3 Implementation Instructions
## shadcn/ui Component Theming

> **For:** Claude Code agent or developer implementing Step 3 of the Woosoo Nexus design alignment.
> **Scope:** Visual-only changes. No business logic, no API changes, no data model changes.
> **Risk:** Low. All changes are CVA class strings and template markup only.
> **Prerequisite:** Steps 1 and 2 must be applied first.
> **Time estimate:** 5–10 minutes including build and QA.

---

## Context

Steps 1–2 fixed the sidebar and topbar. Step 3 brings the shared UI primitives (Badge, Button, Card, Tabs) into brand alignment so every page benefits automatically:

1. **Badge** — missing `warning` (amber) variant; existing `success`/`active`/`accent` lacked dark-mode styles
2. **Button** — base radius is `rounded-xl` (0.75rem) but brand `--radius` token is `0.5rem` (`rounded-lg`); this creates inconsistency with sidebars and cards
3. **Card** — `border-white/65` is invisible in light mode; `backdrop-blur-xl` is heavy; shadow is over-scaled
4. **TabsList** — missing border and brand-warm background treatment
5. **TabsTrigger** — active state was bare `bg-background`; needs the white-card-with-border treatment used elsewhere in the system

---

## Files to replace

| Source file (in this handoff folder) | Destination in the project |
|---|---|
| `badge-index.ts` | `resources/js/components/ui/badge/index.ts` |
| `button-index.ts` | `resources/js/components/ui/button/index.ts` |
| `Card.vue` | `resources/js/components/ui/card/Card.vue` |
| `TabsList.vue` | `resources/js/components/ui/tabs/TabsList.vue` |
| `TabsTrigger.vue` | `resources/js/components/ui/tabs/TabsTrigger.vue` |

> **Note on naming:** `badge-index.ts` and `button-index.ts` are named with a prefix to avoid ambiguity in this handoff folder. When copying, rename them to `index.ts` at the destination.

---

## Step-by-step instructions

### 1. Back up existing files (recommended)
```bash
cp resources/js/components/ui/badge/index.ts resources/js/components/ui/badge/index.ts.bak
cp resources/js/components/ui/button/index.ts resources/js/components/ui/button/index.ts.bak
cp resources/js/components/ui/card/Card.vue resources/js/components/ui/card/Card.vue.bak
cp resources/js/components/ui/tabs/TabsList.vue resources/js/components/ui/tabs/TabsList.vue.bak
cp resources/js/components/ui/tabs/TabsTrigger.vue resources/js/components/ui/tabs/TabsTrigger.vue.bak
```

### 2. Copy the replacement files
```bash
cp handoff/step-3/badge-index.ts   resources/js/components/ui/badge/index.ts
cp handoff/step-3/button-index.ts  resources/js/components/ui/button/index.ts
cp handoff/step-3/Card.vue         resources/js/components/ui/card/Card.vue
cp handoff/step-3/TabsList.vue     resources/js/components/ui/tabs/TabsList.vue
cp handoff/step-3/TabsTrigger.vue  resources/js/components/ui/tabs/TabsTrigger.vue
```

### 3. Rebuild the frontend
```bash
npm run build
# or for development:
npm run dev
```

---

## What changed in each file

### `badge/index.ts`
- **Added** `warning` variant: `bg-woosoo-accent/12 text-woosoo-primary-dark border-woosoo-accent/30` (light) / `bg-woosoo-accent/18 text-woosoo-accent` (dark)
- **Fixed** `success` and `active`: replaced `bg-woosoo-green-100 text-woosoo-green` with `bg-woosoo-green/12` and added full dark-mode counterparts
- **Fixed** `accent` (blue): added `dark:bg-woosoo-blue/18 dark:border-woosoo-blue/30`
- All existing variants (`default`, `secondary`, `destructive`, `outline`) are **unchanged**

### `button/index.ts`
- Base class: `rounded-xl` → `rounded-lg` — brings buttons in line with `--radius: 0.5rem` used by inputs, sidebar items, and tabs
- All variant styles and sizes are **unchanged**

### `card/Card.vue`
- `border-white/65` → `border-black/8` — the old value was near-invisible in light mode; `border-black/8` matches the system-wide card border used in Dashboard and Devices
- `backdrop-blur-xl` → `backdrop-blur-sm` — lighter GPU load; consistent with Step 2's content wrapper
- Shadow: `0_28px_60px_-38px_rgba(37,37,37,0.38)` → `0_24px_55px_-36px_rgba(37,37,37,0.32)` — softer, proportional
- Added dark-mode shadow: `dark:shadow-[0_24px_55px_-36px_rgba(0,0,0,0.38)]`

### `tabs/TabsList.vue`
- `bg-muted` → `bg-black/[0.04]` — warm neutral instead of flat muted
- Added `border border-black/8` (light) and `dark:border-white/10` — consistent with card borders
- `h-9` → `h-10` — slightly taller, more comfortable click target
- Added dark-mode background `dark:bg-white/[0.05]`

### `tabs/TabsTrigger.vue`
- Active state: `data-[state=active]:bg-background` → `data-[state=active]:bg-white data-[state=active]:border-black/8 data-[state=active]:shadow-sm` — white card treatment (light mode)
- Dark active: `dark:data-[state=active]:bg-white/[0.08] dark:data-[state=active]:border-white/12`
- Inactive text: `text-foreground` → `text-foreground/65` — lower contrast for unselected tabs
- Focus ring: replaced `focus-visible:ring-ring/50` with amber `focus-visible:ring-[#f6b56d]/40`
- Removed internal max-width `max-w-55` — no longer needed

---

## QA checklist

- [ ] **Badge warning** — `<Badge variant="warning">` renders amber-tinted pill in both light and dark
- [ ] **Badge success/active** — green-tinted pill renders correctly in dark mode (was near-invisible before)
- [ ] **Button radius** — all button variants have `rounded-lg` corners (visually match inputs and sidebar items)
- [ ] **Card border** — card borders are visible as a subtle stroke in light mode
- [ ] **Tabs** — tab list has warm bordered container; active tab is white card with shadow (light) or glass card (dark)
- [ ] **Dashboard cards** — stat cards look correct (Card is used there)
- [ ] **Orders tabs** — Live Orders / Order History tabs match new style

---

## What NOT to change

- `Badge.vue`, `Button.vue` — the `.vue` component shells are unchanged; only the `index.ts` variant definitions are replaced
- `CardHeader.vue`, `CardContent.vue`, `CardFooter.vue` — only `Card.vue` (the outer wrapper) is in scope
- `TabsContent.vue`, `Tabs.vue` — only `TabsList.vue` and `TabsTrigger.vue` are in scope
- Any files in `resources/js/pages/` — page-level changes are handled in Step 4

---

## Next steps

- **Step 4** — Page-by-page: Dashboard, Orders, Devices screens aligned to design mockup

---

*Generated by Woosoo Design · May 2026*
