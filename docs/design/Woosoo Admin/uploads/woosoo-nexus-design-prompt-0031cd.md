# Woosoo Nexus — Claude Design Prompt

Complete design prompt for generating UI mockups, screen layouts, and component specs for the Woosoo Nexus admin panel.

---

## PROMPT (copy everything below this line)

---

You are designing the **Woosoo Nexus** admin panel — a restaurant operations dashboard for a Korean BBQ samgyupsal restaurant chain. This is a real production system used daily by restaurant staff and managers.

---

## Product Context

**Woosoo Nexus** is a Laravel 12 + Vue 3 (Inertia.js) + TailwindCSS web application. It is the backend brain of the restaurant system: it manages tablet ordering, POS integration, device management, print job orchestration, and staff operations. The admin panel is used exclusively by **restaurant managers and system operators** — not customers.

The system connects three pieces:
1. **Woosoo Nexus** (this app) — admin panel + API server
2. **Tablet PWA** — customer-facing ordering tablets at each table
3. **Print Bridge** — Android relay that dispatches orders to kitchen/cashier printers

---

## Brand & Design Direction

- **Restaurant type:** Korean BBQ samgyupsal (meat-heavy, warm, lively atmosphere)
- **Tone:** Professional operations tool — clean, dense-information, high-contrast. Think restaurant POS system, not SaaS dashboard.
- **Density:** Information-dense. Tables are primary. Cards for KPIs. Modals for forms.
- **Icons:** Lucide icons (already used in codebase).
- **Components:** shadcn/ui component library (already installed). Use its patterns: DataTable, Dialog, Badge, Select, Card, Tabs.

### Typography (exact — loaded via Google Fonts)

| Role | Family | CSS Token |
|---|---|---|
| **Headings, titles, buttons, labels** | Raleway (variable 100–900) | `--font-header` |
| **Body text, paragraphs, table content** | Kanit (variable 100–900) | `--font-sans` |

### Brand Color Palette (exact tokens)

| Token | Hex | Role |
|---|---|---|
| `--color-woosoo-accent` | `#F6B56D` | **Hero amber** — accent, dark-mode primary, focus ring, selection highlight |
| `--color-woosoo-primary-light` | `#FCD8BA` | Light-mode primary (buttons, CTAs) |
| `--color-woosoo-primary-dark` | `#B08047` | Dark amber — sidebar primary in light mode |
| `--color-woosoo-dark-gray` | `#252525` | Foreground / text on light backgrounds |
| `--color-woosoo-white` | `#FFFFFF` | Base white |
| `--color-woosoo-blue` | `#2563EB` | Informational / link blue |
| `--color-woosoo-red` | `#DC2626` | Error / danger |
| `--color-woosoo-green` | `#16A34A` | Success |
| `--color-woosoo-orange` | `#F97316` | Warning / highlight orange |

### Status / Semantic Colors

| Token | Hex | Role |
|---|---|---|
| `--success` | `#16A34A` | Success states |
| `--warning` | `#F59E0B` | Warning states |
| `--error` | `#EF4444` | Error states |
| `--info` | `#3B82F6` | Informational states |

### Light Mode

- **Background:** `#FFFFFF` with soft warm gradient (`#f5ede3 → #fffaf5 → #ffffff → #f7f5f2`)
- **Foreground:** `#252525`
- **Primary (buttons/actions):** `#FCD8BA` with `#252525` text
- **Accent:** `#F6B56D`
- **Border:** `hsl(0 0% 89.8%)`
- **Border radius:** `0.5rem`
- **Sidebar background:** `hsl(20 5% 10%)` (near-black warm dark)
- **Sidebar active/primary:** `#B08047`

### Dark Mode

- **Background:** `hsl(20 6% 5%)` with dark warm gradient (`#17120f → #120f0d → #0e0c0a`)
- **Foreground:** `hsl(30 10% 93%)`
- **Primary (buttons/actions):** `#F6B56D` amber (flips — amber becomes the primary)
- **Primary foreground:** `#252525`
- **Accent:** `#F6B56D`
- **Focus ring:** `#F6B56D`
- **Card background:** `hsl(20 8% 13%)`
- **Border:** `hsl(20 8% 18%)`
- **Sidebar background:** `hsl(20 5% 8%)`
- **Sidebar active/primary:** `#F6B56D`

**Design character summary:** Warm amber/brown palette. Light mode = bright white with a soft warm-tinted background. Dark mode = near-black with warm brown tones where amber becomes the dominant action color. Typography is Raleway for all headings, buttons, and UI labels; Kanit for body text and table content — giving it a clean but slightly editorial feel.

---

## Pages to Design

### 1. Dashboard (`/dashboard`)
The first screen after login. Real-time operations snapshot.

**KPI cards (top row):**
- Active Devices (online tablets right now)
- Open Orders (confirmed, not yet completed)
- Queue Depth (background job backlog)
- Print Failures (failed print events today)

**Content sections:**
- Recent orders table (last 10 orders: table, package, guest count, status badge, time ago)
- Device status grid (each registered tablet: name, table, last seen, status dot green/red)
- System health row (MySQL ✓, Redis ✓, POS DB ✓/✗, Queue ✓)

**Notes:** Live updates via WebSocket (Reverb). Status dots pulse when active.

---

### 2. Orders (`/orders`)
Primary operational screen. Used constantly during service.

**Filters bar:** Date range, status (All / Confirmed / Completed / Voided / Cancelled), package, search by order ID.

**Orders table columns:** Order ID, Table, Device, Package, Guest Count, Items Count, Status badge, Created At, Actions.

**Status badges:** `confirmed` = amber, `completed` = green, `voided` = red, `cancelled` = gray.

**Bulk actions toolbar** (appears when rows selected): Bulk Complete, Bulk Void.

**Order detail page (`/orders/{id}`):**
- Header: Order ID, status badge, timestamps
- Order items table: Menu item name, quantity, is refill, printed status
- Print event log (if print events feature is on)
- Action buttons: Mark Complete, Void, Print

---

### 3. POS (`/pos`)
Live Krypton POS data viewer. Read-mostly.

**Layout:** Left sidebar = terminal list. Main panel = tables grid for selected terminal.

**Table card:** Table number, status (open/closed), current order summary, guest count.

**Clicking a table** opens a panel/modal showing:
- All orders at that table
- Add order button (opens form)
- Void / Pay / Edit order actions

---

### 4. Menus (`/menus`)
Menu item management. Grid or table view toggle.

**Table columns:** ID, Name (kitchen name), Category, Availability toggle (on/off switch), Image thumbnail, Actions.

**Bulk toggle availability** — select rows, flip toggle for all.

**Image upload** — per-row action or drag-drop in menu row.

---

### 5. Packages (`/packages` and `/package-configs`)
Two separate pages.

**Packages** = legacy simple CRUD table (name, price, description).

**Package Configs** = richer admin-managed tablet packages with allowed menus.

Three canonical packages:
| Package | Price | Tier |
|---|---|---|
| Classic Feast | ₱449 | Entry |
| Noble Selection | ₱499 | Mid |
| Royal Banquet | ₱549 | Premium |

Design package cards with a tier indicator and meat count badge.

---

### 6. Tablet Categories (`/tablet-categories`)
Categories shown on the ordering tablet. Drag-to-reorder. Menu sync management.

**List view:** Category name, slug, menu count, sort order, actions (edit, delete).

**Detail/edit panel:** Category info + attached menus table with featured toggle and detach.

---

### 7. Devices (`/devices`)
Every registered ordering tablet in the system.

**Table columns:** Device name, Table assigned, IP address, Type, Status (active/inactive badge), Last seen, Security code status, Actions.

**Actions per device:** Assign table, Regenerate security code, Create token, Soft delete.

**Trashed view** (`/devices/trashed`): Deleted devices with restore action.

**APK download button** for release/debug channel.

---

### 8. Users, Roles, Permissions (`/users`, `/roles`, `/permissions`)
Standard RBAC management. Standard admin patterns.

- Users: name, email, roles, status, last login, actions (edit, delete, restore)
- Roles: name, permissions count, assigned users count, actions
- Permissions: name, guard, actions

---

### 9. Reports (`/reports/*`)
Sub-navigation tabs for: Daily Sales | Menu Items | Hourly Sales | Guest Count | Print Audit | Order Status | Discount & Tax.

**Each report:** Date range filter, chart (bar or line), data table below chart.

**Print Audit** report: print event ID, order, attempts, status, timestamps — for diagnosing print failures.

---

### 10. Monitoring (`/monitoring`)
System health and operational telemetry. Used by system operators.

**Sections:**
- DB health (MySQL + POS DB connection status)
- Queue stats (depth, failed jobs, last run)
- Device order anomalies (stuck orders, orders without print events)
- Print event stats (pending, reserved, failed, purge button)
- Recent failed jobs table

---

### 11. Reverb Service (`/reverb`)
WebSocket service management panel.

**Status card:** Running / Stopped badge, PID, uptime.

**Action buttons:** Start, Stop, Restart (with confirmation modal).

**Connection stats:** Connected clients count, channels active.

---

### 12. Configuration (`/configuration`)
Hub page with tiles/cards linking to sub-sections:
- POS Connection (test + configure Krypton DB)
- Settings (theme, alerts, pagination)
- Package Configs
- Tablet Categories

---

### 13. Settings (`/admin/settings`)
Branch-backed key-value settings form.

**Fields:** Theme (light/dark/system), Items per page, Email notifications toggle, Order alerts toggle, Sound alerts toggle, POS system name, API base URL, WebSocket URL.

---

## Key UI Patterns to Follow

- **Sidebar navigation:** Fixed left sidebar with icon + label. Collapsible on smaller screens. Active item highlighted with warm accent. Sections grouped (Operations, Catalog, People, System).
- **Page header:** Page title + subtitle + primary action button (right-aligned).
- **Data tables:** Sortable columns, row selection checkboxes for bulk actions, pagination, search input. Empty state with icon + message.
- **Status badges:** Pill-shaped, color-coded (confirmed=amber, completed=green, voided=red, cancelled=gray, active=green, inactive=gray, online=green pulse, offline=red).
- **Confirmation modals:** Destructive actions (void, delete, purge) require a modal with red confirm button.
- **Toast notifications:** Success/error toasts bottom-right for all form submissions and actions.
- **Loading states:** Skeleton loaders for tables. Spinner overlays for form submits.
- **Responsive:** Designed for **desktop/large screen first** (1280px+). Tablets in landscape (1024px). This is not a mobile app.

---

## Sidebar Navigation Structure

```
WOOSOO NEXUS
─────────────────
[Dashboard icon]    Dashboard

OPERATIONS
[Orders icon]       Orders
[POS icon]          POS
[Monitoring icon]   Monitoring
[Print icon]        Reverb

CATALOG
[Menu icon]         Menus
[Package icon]      Packages
[Category icon]     Tablet Categories

DEVICES
[Device icon]       Devices
[Service icon]      Service Requests
[Log icon]          Event Logs

PEOPLE
[User icon]         Users
[Role icon]         Roles
[Lock icon]         Permissions
[Branch icon]       Branches

REPORTS
[Chart icon]        Reports

SYSTEM
[Config icon]       Configuration
[Settings icon]     Settings
[Manual icon]       Manual
─────────────────
[Avatar] Admin User
```

---

## Technical Constraints for Design

- Stack is **Inertia.js + Vue 3** — no separate frontend build, pages are rendered server-side with Vue components.
- Component library is **shadcn/ui** — use its exact component patterns (DataTable, Dialog, Badge, Button variants, Card, Tabs, Form).
- All forms use **Inertia form helpers** — no separate API calls from admin pages.
- Real-time updates via **Laravel Echo + Reverb** — assume live data on Dashboard and Orders pages.
- Currency is **Philippine Peso (₱)**.
- Timestamps are displayed in **Asia/Manila** timezone.
- The system supports **multi-branch** — branch selector may appear in the header for future multi-branch expansion.
