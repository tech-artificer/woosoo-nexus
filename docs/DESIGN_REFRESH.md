# Woosoo Nexus — Design Refresh

> **Source:** Woosoo Nexus Functional Guide (claude.ai/design) · Design Alignment Series (Steps 1–4)
> **Goal:** Apply look and feel from the Functional Guide to the HQ Console. No functionality changes — visual language, layout, and component style only.
> **Scope:** `woosoo-nexus` only. Tablet PWA and print bridge are excluded.

---

## How to Use This Document

Fill in the **Design Change** cells below with what the Functional Guide shows. Leave blank if the current design should stay. Once all sections are filled, implementation proceeds top-to-bottom.

Status key: `[ ]` = pending · `[~]` = in progress · `[x]` = done

The **Developed** column tracks per-row implementation state: `No` = not yet built · `Yes` = shipped to the working tree.

---

## 1. Design System — Global Tokens

**Files:** `resources/css/app.css` · `resources/css/nexus-shell.css`

> ✓ **All tokens were already correct** before the alignment series began. The Design Alignment Roadmap explicitly confirmed: "Brand tokens — amber `#F6B56D`, Raleway / Kanit / JetBrains Mono, dark sidebar, light & dark themes — are already defined in `app.css`. The series consumes them; it doesn't redefine them."

### 1.1 Color Palette

| Token | Current Value | Design Change | Notes | Developed |
|---|---|---|---|---|
| `--background` (light) | `hsl(36 28% 96%)` warm beige | No change — already aligned | | Yes |
| `--foreground` | `#252525` via `--color-woosoo-dark-gray` | No change | | Yes |
| `--primary` (light) | `#B08047` via `--color-woosoo-primary-dark` | No change | Button bg in light mode | Yes |
| `--primary` (dark) | `#F6B56D` via `--color-woosoo-accent` | No change | Flips in dark mode | Yes |
| `--accent` | `#F6B56D` warm gold via `--color-woosoo-accent` | No change | Nav active, ring, highlights | Yes |
| `--card` | `hsl(38 35% 98%)` / `hsl(20 8% 13%)` dark | No change | Card surface | Yes |
| `--border` | `hsl(34 18% 82%)` light / `hsl(20 8% 18%)` dark | No change | | Yes |
| `--muted` | `hsl(36 20% 91%)` | No change | Table header, subdued bg | Yes |
| `--muted-foreground` | `hsl(28 9% 36%)` | No change | Label text, placeholders | Yes |
| `--radius` | `0.5rem` | No change | Global border radius | Yes |
| Sidebar bg | `hsl(20 5% 10%)` via `--shell-bg` / `--sidebar-background` | No change | Always dark | Yes |
| Sidebar active | `hsl(30 80% 66%)` via `--shell-active` ≈ `#F6B56D` | No change | | Yes |

**Status:** `[x]`

---

### 1.2 Typography

| Token | Current | Design Change | Developed |
|---|---|---|---|
| Heading font | **Raleway** — `--font-header` in `app.css` | No change | Yes |
| Body/UI font | **Kanit** — `--font-sans` in `app.css` | No change | Yes |
| Mono font | **JetBrains Mono** — `--font-mono` in `nexus-shell.css` | No change | Yes |
| Heading size scale | `text-2xl / text-xl / text-lg` | No change | Yes |
| Body size | `text-sm / text-base` | No change | Yes |
| Label / table header | `text-xs uppercase tracking-wide` | No change | Yes |
| Button text weight | `font-medium` | No change | Yes |

**Status:** `[x]`

---

### 1.3 Spacing & Grid

| Dimension | Current | Design Change | Developed |
|---|---|---|---|
| Sidebar width | `224px` (w-56) | No change | Yes |
| Topbar height | `52px` via `--topbar-h` | No change | Yes |
| Page content max-width | `max-w-[1680px]` in `AppContentLayout.vue` | No change | Yes |
| Page horizontal padding | `px-3 md:px-5` in `AppContentLayout.vue` | No change | Yes |
| Card inner padding | `py-6` (Card.vue base) | No change | Yes |
| Base spacing unit | 4px (Tailwind default) | No change | Yes |

**Status:** `[x]`

---

### 1.4 Elevation & Shadow

| Element | Current | Design Change | Developed |
|---|---|---|---|
| Card shadow | `shadow-[0_24px_55px_-36px_rgba(37,37,37,0.32)]` + dark variant | Lightened from prior value — Step 3 ✓ | Yes |
| Dialog shadow | `shadow-lg` | No change | Yes |
| Topbar shadow | `border-b border-[var(--topbar-border)]` | No change | Yes |
| Sidebar shadow | none (background contrast) | No change | Yes |
| Dropdown shadow | `shadow-lg` | No change | Yes |

**Status:** `[x]`

---

## 2. Shell & Navigation

**Files:** `resources/js/components/shell/AdminSidebar.vue` · `resources/js/components/shell/AdminSidebarContent.vue` · `resources/js/components/shell/AdminTopbar.vue` · `resources/js/layouts/AppLayout.vue` · `resources/js/layouts/AppContentLayout.vue`

> Note: The codebase uses a custom shell (`shell/Admin*.vue`) rather than shadcn/ui sidebar primitives. The design alignment targets below map to these custom files.

### 2.1 Sidebar

**Current behavior:**
- Always-dark charcoal panel via `--shell-bg: hsl(20 5% 10%)`
- Flat logo row (icon + text) with bottom border
- Nav groups from `NAV_SECTIONS` config with icons + labels
- Active item: amber text via `--shell-active` + 2px left `<span>` bar
- Hover: `--shell-hover: rgba(255,255,255,0.06)`

| Area | Current | Design Change | Developed |
|---|---|---|---|
| Background | `--shell-bg: hsl(20 5% 10%)` near-black charcoal | No change | Yes |
| Width (expanded) | `224px` (w-56) | No change | Yes |
| Logo treatment | Flat: icon (`rounded-xl border border-white/10 bg-white/10`) + text, border-bottom | Glassmorphism pill removed — flat `border-b border-white/8` row | Yes |
| Nav item style | icon + label, `rounded-md`, amber left-bar `<span>`, amber text on active | No change from current | Yes |
| Active state | `text-[var(--shell-active)]` + 2px left `<span>` bar (absolute positioned) | Add amber-tinted bg (`bg-[#2a1e0c]`, `border border-[#3d2c14]`) to active item — applied to main + footer nav bindings | Yes |
| Hover state | `--shell-hover: rgba(255,255,255,0.06)` | No change | Yes |
| Nav grouping | `NAV_SECTIONS` config (key-based groups) | No change | Yes |
| Group header style | `text-[10px] uppercase tracking-widest text-[var(--shell-dim)]` | No change | Yes |
| Collapsed state | N/A — mobile uses Sheet drawer | No change | Yes |
| Bottom section | User `DropdownMenu` with avatar + `UserMenuContent` | No change | Yes |

**Status:** `[x]`

---

### 2.2 Topbar

**Current behavior:**
- Flat `52px` header, `border-b`, background via `--topbar-bg`
- Left: mobile hamburger + page title (h2) + breadcrumbs
- Right: HQ/branch link · search (⌘K CommandDialog) · theme toggle · refresh · bell · avatar

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Background | `var(--topbar-bg)` = `var(--background)` (follows theme) | No change | | Yes |
| Height | `52px` via `--topbar-h` | No change — was floating pill, now flush `52px` | Step 1 Change 04 ✓ | Yes |
| Left content | `h2` page title + `<Breadcrumbs>` | No change | | Yes |
| Right content | HQ link + search + theme toggle + refresh + bell + avatar | Dark mode toggle added (Step 2) ✓ | | Yes |
| Bottom border | `border-b` via `--topbar-border` | No change — was glassmorphism, now flat border | Step 1 Change 04 ✓ | Yes |
| Search bar | Global `CommandDialog` (⌘K) in topbar | Added ✓ | ⌘K wired | Yes |
| "HQ" indicator | Branch name from `page.props.branch` (fallback `"HQ"`) | Replace static "HQ" with live branch name | Backend share + frontend `branchLabel` computed ✓ | Yes |

**Status:** `[x]`

---

## 3. Shared Components

**Files:** `resources/js/components/ui/`

### 3.1 Button

**File:** `components/ui/button/index.ts`

> ✓ WOOSOO STEP 3 applied: `rounded-xl → rounded-lg`.

| Variant | Current | Design Change | Developed |
|---|---|---|---|
| `default` | amber bg (`bg-woosoo-accent`), dark text, lift hover | No change | Yes |
| `brand` | amber bg, compact (no lift) | New variant added for card contexts | Yes |
| `secondary` | gray bg | No change | Yes |
| `outline` | `border-black/10 bg-white/72`, hover amber border | No change | Yes |
| `destructive` | red bg | No change | Yes |
| `ghost` | transparent, hover bg | No change | Yes |
| `link` | text only, underline | No change | Yes |
| Size `sm` | `h-9 px-3` | No change | Yes |
| Size `default` | `h-10 px-4` | No change | Yes |
| Size `lg` | `h-11 px-6` | No change | Yes |
| Border radius | `rounded-lg` (was `rounded-xl`) | `rounded-xl → rounded-lg` — matches `--radius: 0.5rem` | Yes |
| Focus ring | `focus-visible:ring-[rgb(246_181_109_/_0.22)]` amber | No change | Yes |

**Status:** `[x]`

---

### 3.2 Card

**File:** `components/ui/card/Card.vue`

> ✓ WOOSOO STEP 3 applied: border, blur, shadow.

| Property | Current | Design Change | Developed |
|---|---|---|---|
| Background | `bg-card/88` | No change | Yes |
| Border | `border-black/8` (light) · `dark:border-white/10` | `border-white/65 → border-black/8` — visible in light mode | Yes |
| Border radius | `rounded-[26px]` | No change | Yes |
| Shadow | `shadow-[0_24px_55px_-36px_rgba(37,37,37,0.32)]` + dark variant | Lightened + dark shadow added | Yes |
| Blur | `backdrop-blur-sm` | `backdrop-blur-xl → backdrop-blur-sm` — GPU reduction | Yes |
| Header padding | `p-6` (via `CardHeader`) | No change | Yes |
| Content padding | `p-6 pt-0` (via `CardContent`) | No change | Yes |
| Title size | `text-lg font-semibold` | No change | Yes |
| Description color | `text-muted-foreground` | No change | Yes |

**Status:** `[x]`

---

### 3.3 Badge

**File:** `components/ui/badge/index.ts`

> ✓ WOOSOO STEP 3 applied: `warning` variant added; `success`/`active`/`accent` dark-mode fixed.

| Variant | Current | Design Change | Developed |
|---|---|---|---|
| `default` | amber bg | No change | Yes |
| `secondary` | gray bg | No change | Yes |
| `destructive` | red bg | No change | Yes |
| `outline` | transparent + border | No change | Yes |
| `success` | `bg-woosoo-green/12 text-woosoo-green dark:bg-woosoo-green/20 dark:text-woosoo-green-100` | Fixed dark mode (was `bg-woosoo-green-100 text-woosoo-green` — no dark counterpart) | Yes |
| `active` | Same as `success` (shared constant) | Fixed dark mode — matches `success` | Yes |
| `warning` | `bg-woosoo-accent/12 text-woosoo-primary-dark dark:bg-woosoo-accent/18 dark:text-woosoo-accent` | **New variant** — was missing | Yes |
| `accent` | `bg-woosoo-blue/10 text-woosoo-blue dark:bg-woosoo-blue/18 dark:border-woosoo-blue/30` | Added dark counterpart | Yes |
| Shape | `rounded-md` pill | No change | Yes |
| Size | `text-xs px-2 py-0.5` | No change | Yes |

**Custom status badges (order/device):**

| Status | Current Color | Design Change | Developed |
|---|---|---|---|
| Online / Active | `success` variant → `bg-woosoo-green/12 text-woosoo-green` | Dark mode fixed | Yes |
| Offline | `secondary` / gray | No change | Yes |
| Warning | `warning` variant (new) | New amber warning variant | Yes |
| Pending | `accent` → `bg-woosoo-blue/10 text-woosoo-blue` | Dark mode fixed | Yes |
| Completed | `success` variant | No change | Yes |
| Voided | `destructive` | No change | Yes |
| Cancelled | `secondary` | No change | Yes |

**Status:** `[x]`

---

### 3.4 Input & Form Fields

**Files:** `components/ui/input.vue` · `components/ui/select.vue` · `components/ui/textarea.vue` · `components/ui/label.vue`

| Property | Current | Design Change | Developed |
|---|---|---|---|
| Input height | `h-11` | No change | Yes |
| Border | `border border-black/10` / `dark:border-white/10` | Lightened to `border-black/8` (light); dark unchanged — WOOSOO STEP 5 | Yes |
| Border radius | `rounded-xl` | No change | Yes |
| Focus ring | `focus-visible:ring-[rgb(246_181_109_/_0.18)]` (amber accent @18%) + `focus-visible:border-woosoo-accent` | Already amber — confirmed aligned, no blue default | Yes |
| Background | `bg-white/78` / `dark:bg-white/[0.03]` | No change | Yes |
| Placeholder color | `text-muted-foreground` | No change | Yes |
| Label weight | `font-medium text-sm` | No change | Yes |
| Error state | `aria-invalid:border-destructive` + ring | No change | Yes |

**Status:** `[x]`

---

### 3.5 Data Table

**Files:** `components/ui/table.vue` · feature-specific DataTable components

| Property | Current | Design Change | Developed |
|---|---|---|---|
| Header bg | none (`[&_tr]:border-b` only) | Subtle tint `bg-black/[0.03]` / `dark:bg-white/[0.03]` — WOOSOO STEP 5 | Yes |
| Header text | `text-muted-foreground text-xs uppercase` | No change | Yes |
| Row bg | transparent | No change | Yes |
| Row hover | `hover:bg-muted/50` | Matches card surface: `hover:bg-black/[0.025]` / `dark:hover:bg-white/[0.025]` — WOOSOO STEP 5 | Yes |
| Row dividers | `border-b` | No change | Yes |
| Cell padding | `p-4` | No change | Yes |
| Pagination controls | below table | No change | Yes |
| Empty state | centered text + icon | No change | Yes |

**Status:** `[x]`

---

### 3.6 Dialog & Sheet

**Files:** `components/ui/dialog.vue` · `components/ui/sheet.vue`

| Property | Current | Design Change | Developed |
|---|---|---|---|
| Dialog overlay | `bg-black/80` | `bg-black/40 backdrop-blur-sm` (both Dialog + Sheet overlays) — WOOSOO STEP 5 | Yes |
| Dialog bg | `bg-background` | No change | Yes |
| Dialog border | `border` (theme token) | Card treatment `border-black/8` / `dark:border-white/10` (Dialog + Sheet content) — WOOSOO STEP 5 | Yes |
| Dialog border radius | `rounded-lg` | No change | Yes |
| Dialog max-width | `sm:max-w-lg` | No change | Yes |
| Sheet side | right | No change | Yes |
| Sheet width | `w-3/4 sm:max-w-sm` | No change | Yes |
| Header border | `border-b` | No change | Yes |
| Close button | top-right X icon | No change | Yes |

**Status:** `[x]`

---

### 3.7 Stat / Metric Card (Dashboard widgets)

**File:** `pages/dashboard/components/` · `components/dashboard/`

| Property | Current | Design Change | Developed |
|---|---|---|---|
| Layout | icon left + value right | No change | Yes |
| Icon treatment | `h-10 w-10 rounded-xl bg-accent/12` | `h-11 w-11 rounded-2xl → h-10 w-10 rounded-xl` — Step 4 ✓ | Yes |
| Value size | `text-2xl font-bold` | No change | Yes |
| Label size | `text-sm text-muted-foreground` | No change | Yes |
| Trend indicator | arrow + % text | No change | Yes |
| Border | provided by Card.vue post Step 3 | Redundant per-card `border-black/8` removed | Yes |
| Background | `bg-card` | No change | Yes |

**Status:** `[x]`

---

## 4. Pages

### 4.1 Login

**File:** `pages/auth/Login.vue`
**Layout:** `layouts/auth/AuthCardLayout.vue` or `AuthSplitLayout.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Layout type | centered card | | Split = logo left, form right | No |
| Background | warm gradient | | | No |
| Card style | white card, shadow | | | No |
| Logo placement | above form | | | No |
| Heading | "Log in to your account" | | | No |
| Button style | primary amber | | | No |
| Field style | standard input | | | No |

**Status:** `[ ]`

---

### 4.2 Dashboard

**File:** `pages/Dashboard.vue`
**Behavior:** Landing page post-login. Shows daily sales overview, top-selling items, sales by branch.

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Stat card grid | 4-col row | No change | | Yes |
| Hero card radius | `rounded-[26px]` | `rounded-[28px] → rounded-[26px]` — Step 4 ✓ | | Yes |
| Inner cards radius | `rounded-[22px]` | `rounded-[24px] → rounded-[22px]` — Step 4 ✓ | | Yes |
| Icon containers | `h-10 w-10 rounded-xl` | `h-11 w-11 rounded-2xl → h-10 w-10 rounded-xl` — Step 4 ✓ | | Yes |
| Redundant borders | removed (Card.vue supplies) | Drop per-card `border-black/8` — Step 4 ✓ | | Yes |
| Chart section | below stat cards | No change | | No |
| Chart type | bar + line (Unovis) | No change | | No |
| Date range selector | dropdown top-right | No change | | No |
| Top items | table with rank, name, qty, revenue | No change | | No |
| Branch breakdown | table or chart | No change | | No |
| Loading state | skeleton placeholders | No change | | No |

**Status:** `[~]`

---

### 4.3 Orders

**File:** `pages/Orders/Index.vue`
**Behavior:** View all orders. Filter by date, status, branch. View order detail in sheet/dialog.

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Table columns | id, table, branch, status, total, date | No change | | No |
| Status badge colors | `success`/`warning`/`destructive`/`secondary` via Badge component | No change | Active/Completed/Voided/Cancelled | No |
| Echo connection pill (Live) | `bg-woosoo-green/10 text-woosoo-green` | `bg-emerald-50 text-emerald-700 → woosoo-green tokens` — Step 4 ✓ | | Yes |
| Echo connection pill (Connecting) | `bg-woosoo-accent/10 text-woosoo-primary-dark` | `bg-yellow-50 text-yellow-700 → woosoo-accent tokens` — Step 4 ✓ | | Yes |
| Echo connection pill (Disconnected) | `bg-destructive/10 text-destructive` | `bg-rose-50 text-rose-700 → destructive tokens` — Step 4 ✓ | | Yes |
| Live Orders count badge | `bg-woosoo-accent text-woosoo-dark-gray` | `bg-blue-600 text-white → woosoo-accent tokens` — Step 4 ✓ | | Yes |
| Date filter | date range picker | No change | | No |
| Branch filter | dropdown | No change | | No |
| Search | by order # or table | No change | | No |
| Order detail | right-side sheet | No change | Shows items, totals, timeline | No |
| Empty state | "No orders found" | No change | | No |

**Status:** `[~]`

---

### 4.4 Menus

**File:** `pages/Menus/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Layout | Hero + `<StatsCards>` + table card, all `rounded-[26px] border-black/8 bg-card/92 shadow-sm backdrop-blur-sm` | Already matches Devices/Dashboard refresh pattern — no change needed | | Yes |
| Stat card style | `<StatsCards>` brand variants (primary / accent / destructive) | Already aligned | | Yes |
| Table columns | name, price, status, actions | No change (uses refreshed Table primitive) | | Yes |
| Availability toggle | switch in table row | No change | Inline toggle, updates live | Yes |
| Image display | thumbnail in table | No change | | Yes |
| Create button | amber primary (DataTable toolbar) | Already amber `Button` | | Yes |
| Filter/search | name search + category | No change | | Yes |
| Bulk actions | none currently | No change | | Yes |

**Status:** `[x]`

---

### 4.5 Packages

**File:** `pages/Packages/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Layout | Hero (amber gradient) + `rounded-[26px]` card grid; cards `rounded-[18px] border-black/8` | Already matches refresh pattern | | Yes |
| Package card | name + amber `font-mono` price + meat range + most-popular ring | Already aligned | | Yes |
| Drag handle | none — order via "Display Order" field | No drag handle in current impl (sort_order input) | | Yes |
| "Most popular" | `bg-woosoo-accent/15 text-woosoo-accent` badge + `Star` | Already aligned | | Yes |
| Menu assignment | "Manage Meats" dialog, grouped P/B/C | No change | | Yes |
| Create button | amber `Button size="sm"` "New Package" | Already amber CTA | | Yes |
| Empty state | prompt to create | No change | | Yes |

**Status:** `[x]`

---

### 4.6 Tablet Categories

**File:** `pages/tablet-categories/IndexTabletCategories.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Category list style | Hero + `rounded-[26px] border-black/8 bg-card/92` section | Already matches refresh pattern | | Yes |
| Drag handle | grip icon | No change | | Yes |
| Menu tags | pill/chip list with `receipt_name` badges | Already aligned (amber accent badges) | | Yes |
| Featured indicator | star or badge | No change | | Yes |
| Add menus | attach dialog, grouped Category→Group, amber accents | Already aligned | | Yes |
| Create category | dialog | No change | | Yes |

**Status:** `[x]`

---

### 4.7 Devices

**File:** `pages/Devices/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Fleet stats display | 2-col inline grid (was `<StatsCards>` 4-col) | Remove `<StatsCards>` → inline 2-col grid — Step 4 ✓ | | Yes |
| Hero section radius | `rounded-[26px] bg-card/92 backdrop-blur-sm` | `rounded-[28px] backdrop-blur-xl → rounded-[26px] backdrop-blur-sm` — Step 4 ✓ | | Yes |
| Outer max-width wrapper | removed (AppContentLayout provides it) | Removed double-glass wrapper — Step 4 ✓ | | Yes |
| Device list style | table with status dot | No change | | No |
| Status indicators | colored dot + label | No change | green/yellow/red | No |
| Battery level | icon + % text | No change | | No |
| Last seen | relative timestamp | No change | | No |
| Detail sheet | right-side sheet | No change | | No |
| APK download | button in sheet | No change | | No |
| Token display | masked + copy | No change | | No |
| Create device | form page | No change | | No |

**Status:** `[~]`

---

### 4.8 Users

**File:** `pages/Users/Index.vue` · `pages/Users/Create.vue` · `pages/Users/Edit.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Table columns | name, email, role, status, actions | | | No |
| Role badge | colored badge | | | No |
| Create flow | dedicated create page | | | No |
| Edit flow | dedicated edit page | | | No |
| Delete | confirmation dialog | | | No |

**Status:** `[ ]`

---

### 4.9 Roles & Permissions

**Files:** `pages/roles/IndexRoles.vue` · `pages/roles/CreateRole.vue` · `pages/roles/EditRole.vue` · `pages/Permissions/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Role list | table | | | No |
| Permission grid | checkbox matrix by group | | | No |
| Role create/edit | dedicated page | | | No |

**Status:** `[ ]`

---

### 4.10 Branches

**File:** `pages/branches/IndexBranches.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Branch list | table or card | | | No |
| Branch detail | inline or dialog | | | No |
| Device count per branch | counter badge | | | No |

**Status:** `[ ]`

---

### 4.11 Reports

**Files:** `pages/reports/Index.vue` · `pages/reports/sales/DailySales.vue` · `pages/reports/sales/MonthlySales.vue` · `pages/reports/HourlySales.vue` · `pages/reports/GuestCount.vue` · `pages/reports/MenuItems.vue` · `pages/reports/OrderStatus.vue` · `pages/reports/DiscountTax.vue` · `pages/reports/PrintAudit.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Report nav | sidebar sub-links or tab bar | | | No |
| Chart style | Unovis bar/line/donut | | | No |
| Summary stat cards | above chart | | | No |
| Table below chart | detailed row data | | | No |
| Date range picker | date range component | | | No |
| Branch filter | dropdown | | | No |
| Export button | top-right | | PDF/CSV | No |

**Status:** `[ ]`

---

### 4.12 Service Requests

**File:** `pages/ServiceRequests/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Request list | card or table | | Real-time via Reverb | No |
| Request type icon | custom SVG icons | | cleaning/concierge/water/card | No |
| Status badge | pending/resolved | | | No |
| Resolve action | button in row | | | No |
| Table number | prominent display | | | No |
| Timestamp | relative | | | No |

**Status:** `[ ]`

---

### 4.13 KDS Display

**File:** `pages/KDS/Display.vue`
**Behavior:** Kitchen Display System. Full-screen kitchen view. Dense, high-contrast, wall-distance readable.

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Layout | multi-column grid | | Each column = a station or table | No |
| Order card | table #, items, elapsed timer | | | No |
| Status left-border | new=blue / preparing=amber / ready=green | | Color-coded left border only | No |
| Timer | JetBrains Mono, large | | Red when overdue | No |
| Advance button | tap card or button | | | No |
| Font size | large (wall distance) | | Min 18px body | No |
| Dark-only | yes (kitchen environment) | | Never light mode | No |
| New order animation | pulse/highlight | | | No |

**Status:** `[ ]`

---

### 4.14 Monitoring

**File:** `pages/Monitoring/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Status indicators | colored badges + labels | | | No |
| Layout | card grid | | | No |
| Refresh | auto-poll or manual | | | No |

**Status:** `[ ]`

---

### 4.15 Event Logs

**File:** `pages/EventLogs/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Table columns | timestamp, type, actor, description, severity | | | No |
| Severity badge | info/warning/error colors | | | No |
| Filter bar | type + date + search | | | No |
| Pagination | standard | | | No |

**Status:** `[ ]`

---

### 4.16 POS

**File:** `pages/POS/Index.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Layout | session overview + order management | | | No |
| Active orders list | table/card list | | | No |
| Table selector | grid of table numbers | | | No |
| Order detail | panel or sheet | | | No |

**Status:** `[ ]`

---

### 4.17 Settings

**Files:** `pages/settings/Profile.vue` · `pages/settings/Password.vue` · `pages/settings/Appearance.vue`
**Layout:** `layouts/settings/Layout.vue` (tabbed sidebar nav)

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Layout | two-col: settings nav left, form right | | | No |
| Form style | labeled fields, save button | | | No |
| Appearance page | light/dark/system toggle | | | No |
| Profile photo | avatar upload | | | No |

**Status:** `[ ]`

---

### 4.18 Media Library

**File:** `pages/media/IndexMedia.vue`

| Area | Current | Design Change | Behavior Notes | Developed |
|---|---|---|---|---|
| Layout | grid of image cards | | | No |
| Upload zone | drag-drop or file picker | | | No |
| Image card | thumbnail + filename + actions | | | No |
| Select mode | checkbox select for menu attachment | | | No |
| Delete | confirmation dialog | | | No |

**Status:** `[ ]`

---

## 5. Implementation Order

Steps 1–4 of the Design Alignment Series are complete. Remaining work follows this sequence:

1. `[x]` **Global tokens** — `app.css` + `nexus-shell.css` (Section 1) — already correct
2. `[x]` **Shell** — Sidebar + Topbar (Section 2) — implemented in `shell/Admin*.vue`
3. `[x]` **Core UI components** — Button, Card, Badge, Tabs (Section 3) — WOOSOO STEP 3 ✓
4. `[x]` **Dashboard** (Section 4.2) — radius/border unification — WOOSOO STEP 4 ✓
5. `[x]` **Orders** (Section 4.3) — Echo status pills on brand tokens — WOOSOO STEP 4 ✓
6. `[x]` **Devices** (Section 4.7) — double-glass removed, 2-col stats — WOOSOO STEP 4 ✓
7. `[ ]` **Sidebar active background** — amber `bg-[#2a1e0c]` + `border-[#3d2c14]` on active nav items
8. `[ ]` **Menus + Packages + Categories** (4.4–4.6) — content management pages
9. `[ ]` **Reports** (4.11) — analytics pages
10. `[ ]` **Users/Roles/Branches** (4.8–4.10) — admin config pages
11. `[ ]` **Service Requests + Monitoring + Event Logs** (4.12–4.15) — ops monitoring pages
12. `[ ]` **KDS** (4.13) — separate visual context (dark-only, kitchen)
13. `[ ]` **Auth pages** (4.1) — login/reset
14. `[ ]` **Input, Table, Dialog, Sheet** (3.4–3.6) — remaining UI primitives

---

## 6. Verification Checklist

- [x] `npm run build` in `woosoo-nexus` — passes clean (4.85s, zero errors — 2026-06-21)
- [ ] Walk: Dashboard → Orders → Menus → Packages → Devices
- [x] Toggle dark mode — sidebar consistency, card surfaces, text contrast
- [ ] Open a create/edit dialog or sheet — inherits new field + button styles
- [ ] Open a detail sheet (order or device) — layout and spacing correct
- [ ] Reports page — chart colors, stat cards, table styling
- [ ] KDS page — dark-only, readable at distance, status colors
- [ ] `npm run build` — no Tailwind purge or TypeScript errors
- [ ] No functionality regressions (filters, toggles, drag-reorder, toggles)
