# Woosoo Nexus — Step 1 Implementation Instructions
## Look & Feel Alignment

> **For:** Claude Code agent or developer implementing Step 1 of the Woosoo Nexus design alignment.
> **Scope:** Visual-only changes. No business logic, no API changes, no data model changes.
> **Risk:** Low. All changes are CSS class strings and template markup only.
> **Time estimate:** 10–15 minutes including build and QA.

---

## Context

The Woosoo Nexus admin panel has the correct brand tokens defined in `app.css` (amber `#F6B56D`, Raleway/Kanit fonts, dark sidebar). However, several component-level style choices diverge from the brand spec:

1. **Sidebar active items** use a white background — brand spec calls for amber-tinted (`#2a1e0c`) with amber text (`#F6B56D`)
2. **Sidebar logo area** wraps the logo in a heavy glassmorphism pill — should be a flat, simple logo row
3. **Page header** is a floating frosted-glass pill — should be a flat, flush 52px topbar
4. **No left accent bar** on active nav items — brand spec shows a 2px amber bar on the left edge

This file ships with 5 ready-to-use replacement files. Your job is to copy them into the right locations and rebuild.

---

## Files to replace

| Source file (in this handoff folder) | Destination in the project |
|---|---|
| `app.css` | `resources/css/app.css` |
| `index.ts` | `resources/js/components/ui/sidebar/index.ts` |
| `NavMain.vue` | `resources/js/components/NavMain.vue` |
| `AppSidebar.vue` | `resources/js/components/AppSidebar.vue` |
| `AppSidebarHeader.vue` | `resources/js/components/AppSidebarHeader.vue` |

---

## Step-by-step instructions

### 1. Back up existing files (recommended)
```bash
cp resources/css/app.css resources/css/app.css.bak
cp resources/js/components/ui/sidebar/index.ts resources/js/components/ui/sidebar/index.ts.bak
cp resources/js/components/NavMain.vue resources/js/components/NavMain.vue.bak
cp resources/js/components/AppSidebar.vue resources/js/components/AppSidebar.vue.bak
cp resources/js/components/AppSidebarHeader.vue resources/js/components/AppSidebarHeader.vue.bak
```

### 2. Copy the replacement files
```bash
cp handoff/step-1/app.css resources/css/app.css
cp handoff/step-1/index.ts resources/js/components/ui/sidebar/index.ts
cp handoff/step-1/NavMain.vue resources/js/components/NavMain.vue
cp handoff/step-1/AppSidebar.vue resources/js/components/AppSidebar.vue
cp handoff/step-1/AppSidebarHeader.vue resources/js/components/AppSidebarHeader.vue
```

### 3. Rebuild the frontend
```bash
npm run build
# or for development with hot reload:
npm run dev
```

### 4. Clear any cached views (Laravel)
```bash
php artisan view:clear
php artisan cache:clear
```

---

## What changed in each file

### `app.css`
- **Added** at the bottom: `[data-sidebar="menu-button"][data-active="true"]::before` rule — draws the 2px amber left-bar indicator on active nav items
- Everything else is unchanged

### `resources/js/components/ui/sidebar/index.ts`
- In `sidebarMenuButtonVariants` CVA base string:
  - `rounded-2xl` → `rounded-md` (tighter brand radius)
  - `data-[active=true]:bg-white` → `data-[active=true]:bg-[#2a1e0c]`
  - `data-[active=true]:text-woosoo-dark-gray` → `data-[active=true]:text-[#F6B56D]`
  - `data-[active=true]:shadow-[...]` → `data-[active=true]:shadow-none`
  - Added `data-[active=true]:border data-[active=true]:border-[#3d2c14]`
  - `hover:bg-white/10` → `hover:bg-white/8`

### `NavMain.vue`
- `CollapsibleTrigger` class: `rounded-2xl` → `rounded-md`, active state changed white → amber
- Child `SidebarMenuButton`: `data-[active=true]:text-woosoo-dark-gray` → amber equivalents
- No logic changes

### `AppSidebar.vue`
- `SidebarHeader` contents: removed the `rounded-[30px] backdrop-blur-xl` glass wrapper div
- Replaced with a simple `pb-2 border-b border-white/8` divider row
- `SidebarMenuButton` on logo: removed the `data-[active=true]:bg-white` and glass-specific classes
- All nav item arrays, route definitions, and logic are **unchanged**

### `AppSidebarHeader.vue`
- Outer `<div>`: removed `rounded-[26px]`, `bg-white/78`, `backdrop-blur-xl`, `shadow-[...]`, `min-h-[76px]`
- Replaced with `h-[52px] border-b border-border/60 bg-background px-5`
- `SidebarTrigger`: removed `rounded-full`, `bg-white/88`, `shadow-sm` — replaced with `rounded-md hover:bg-muted`
- "Secure admin workspace" badge: removed the pill styling, kept as plain text with dot indicator
- Breadcrumbs and title logic are **unchanged**

---

## QA checklist

After the build completes, verify in the browser:

- [ ] **Active nav item** — has amber text (`#F6B56D`) and dark amber background, with a 2px amber bar on the left edge
- [ ] **Inactive nav items** — remain white/55% opacity, darken slightly on hover
- [ ] **Collapsed sidebar** — icon-only mode still works, active icon gets amber color
- [ ] **Collapsible nav groups** (Reports, Access Control) — expand/collapse correctly, active parent shows amber
- [ ] **Logo area** — flat, no glassmorphism card
- [ ] **Page header** — flat 52px bar flush with the page edge, no floating pill
- [ ] **Light mode** — topbar background matches the warm cream page background
- [ ] **Dark mode** — topbar background matches dark page background, amber active states remain correct
- [ ] **Sidebar trigger button** — still toggles the sidebar correctly

---

## What NOT to change

Do not modify any of the following during this step:

- Any files in `resources/js/pages/` — page logic is untouched in Step 1
- Any files in `app/` — no PHP/Laravel changes
- `resources/views/app.blade.php` — font loading is already correct
- Any other component in `resources/js/components/ui/` — only `sidebar/index.ts` is in scope
- Database, migrations, routes, controllers — completely out of scope for Step 1

---

## If something looks wrong

| Symptom | Likely cause | Fix |
|---|---|---|
| Active item still white | Tailwind JIT didn't pick up `bg-[#2a1e0c]` | Run `npm run build` again (not just dev) |
| Left bar not showing | CSS specificity issue | Add `!important` to the `::before` background rule in `app.css` |
| Logo area still has glass card | Old build cached | Run `php artisan view:clear` and hard-refresh browser |
| Header still floating pill | Wrong file replaced | Confirm `AppSidebarHeader.vue` was replaced, not `AppHeader.vue` |
| Collapsed sidebar broken | Tailwind purge removed classes | Check `group-data-[collapsible=icon]` utilities are present in the built CSS |

---

## Next steps (after Step 1)

- **Step 2** — Layout & Navigation: page content spacing, topbar actions (dark mode toggle, user menu, branch selector)
- **Step 3** — shadcn/ui component theming: Badge, Button, Card, DataTable styled to brand
- **Step 4** — Page-by-page: Dashboard, Orders, Devices screens aligned to design mockup

---

*Generated by Woosoo Design · May 2026*
