# Woosoo Nexus — Topbar & Sidebar Migration Spec

> Paste this whole file to your dev/AI agent. It reproduces the **topbar** and **sidebar** of the approved `Woosoo Admin` design **exactly**. Every value below is literal — do not approximate, round, or "improve". Stack: Laravel 12 + Vue 3 (Inertia) + Tailwind + shadcn/ui. Build these two as the persistent app shell (`AppLayout.vue`); page content renders to the right of the sidebar and below the topbar.

---

## 0. Design Tokens (define once, use everywhere)

Add these CSS variables. There are **two themes**. The **sidebar is ALWAYS dark** regardless of theme — only the topbar and page background flip.

```css
:root {                      /* DARK theme (default) */
  --bg0:#0d0b09; --bg1:#141210; --bg2:#1b1815; --bg3:#23201a; --bg4:#2b2720;
  --bdr1:#252220; --bdr2:#352f26; --bdr3:#463929;
  --fg0:#f2ebe0; --fg1:#c0b4a0; --fg2:#7e7264; --fg3:#524a3e;
  --accent:#F6B56D; --accm:#2a1e0c; --accb:#3d2c14; --accfg:#1a1208;
  --font-d:'Raleway',sans-serif;        /* display: headings, labels, buttons */
  --font-s:'Kanit',sans-serif;          /* body / nav labels */
  --font-m:'JetBrains Mono',monospace;  /* numbers, badges, kbd */
  --r-m:6px;
}
[data-theme="light"] {       /* LIGHT theme — topbar + page only */
  --bg0:#fffaf5; --bg1:#f5f0e8; --bg2:#ffffff; --bg3:#faf5ee; --bg4:#f0e8da;
  --bdr1:#e8e0d4; --bdr2:#d8cec4; --bdr3:#c8baae;
  --fg0:#252525; --fg1:#4a4035; --fg2:#6b5d52; --fg3:#9a8878;
  --accent:#F6B56D; --accm:#fdf0e2; --accb:#f0d4a8;
}
```

Fonts (load via Google Fonts):
`Raleway:wght@400;500;600;700;800` · `Kanit:wght@300;400;500;600` · `JetBrains+Mono:wght@400;500`

---

## 1. SIDEBAR

### Container
- `width: 224px; flex-shrink: 0;` fixed left, full height, `overflow-y: auto`.
- `background: var(--bg1);` · `border-right: 1px solid var(--bdr1);`
- `display: flex; flex-direction: column;` · `padding: 18px 10px 14px;`
- **Light mode override (sidebar stays dark):** `background: hsl(20 5% 10%); border-right-color: rgba(255,255,255,0.07);`
- Collapsed state (optional): `width: 52px; padding: 18px 6px 14px;` — hide logo text, hide nav labels, center icons, badge becomes a 7px dot top-right of the item.

### Logo block (top)
```
[icon 28×28]  WOOSOO          ← logo-mark
              NEXUS           ← logo-sub
```
- Wrapper: `display:flex; align-items:center; gap:9px; padding:2px 8px 14px; border-bottom:1px solid var(--bdr1); margin-bottom:10px;`
- `.logo-icon`: `28×28; border-radius:6px; overflow:hidden;` → `<img src="/images/woosoo-icon.png">` `object-fit:contain`.
- `.logo-mark` = **"WOOSOO"**: Raleway `15px / 800`, `letter-spacing:0.04em`, `text-transform:uppercase`, `color:var(--fg0)`, `line-height:1`.
- `.logo-sub` = **"NEXUS"**: Raleway `9px / 600`, `letter-spacing:0.18em`, `text-transform:uppercase`, `color:var(--accent)`, `line-height:1`.
- Light override: mark `#f2ebe0`, sub `#F6B56D` (unchanged).

### Nav sections — EXACT order & contents
Render in this order. Section headers (`.nav-label`) are uppercase eyebrows; the first group has **no** header.

| Section | Items (label → icon key, badge / state) |
|---|---|
| *(none)* | **Dashboard** → `dashboard` |
| **OPERATIONS** | **Orders** → `orders` `badge:7` · **POS** → `pos` · **Monitoring** → `monitoring` · **Reverb** → `reverb` |
| **CATALOG** | **Menus** → `menu` · **Packages** → `package` · **Tablet Categories** → `category` |
| **DEVICES** | **Devices** → `tablet` `badge:1` |
| **PEOPLE** | **Users** → `staff` · **Roles** → `role` · **Permissions** → `lock` · **Branches** → `branch` `dim` |
| **REPORTS** | **Reports** → `reports` |
| **SYSTEM** | **Configuration** → `config` · **Settings** → `settings` |

### `.nav-label` (section eyebrow)
`font:700 9.5px var(--font-d); letter-spacing:0.14em; text-transform:uppercase; color:var(--fg3); padding:10px 8px 3px;`
Section wrapper `.nav-section` has `margin-top:6px;`

### `.nav-item`
- `display:flex; align-items:center; gap:9px; padding:7px 8px; border-radius:6px; cursor:pointer;`
- `font:500 13px var(--font-s); color:var(--fg2);` · icon size **14px** (left).
- `transition: background .12s, color .12s;`
- **Hover:** `background:var(--bg2); color:var(--fg1);`
- **Active:** `background:var(--accm); color:var(--accent);` PLUS a left rail:
  ```css
  .nav-item.active::before{content:'';position:absolute;left:-10px;top:7px;bottom:7px;
    width:2px;background:var(--accent);border-radius:0 2px 2px 0;}
  ```
  (item must be `position:relative`)
- **Dim (Branches):** `opacity:0.38; cursor:not-allowed; pointer-events:none;`
- **Light overrides:** item `color:#7e7264`; hover `background:rgba(255,255,255,0.06); color:#c0b4a0`; active `background:rgba(176,128,71,0.18); color:#c99540`; active rail `background:#B08047`.

### `.nav-badge` (the amber count pill, e.g. Orders `7`, Devices `1`)
`margin-left:auto; font:600 10px var(--font-m); background:var(--accent); color:var(--accfg); padding:1px 6px; border-radius:99px;`
Light override: `background:#B08047; color:#fff;`

### Footer (bottom, pushed down with `margin-top:auto`)
`display:flex; align-items:center; gap:9px; padding-top:12px; border-top:1px solid var(--bdr1);`
- Avatar: `28×28; border-radius:50%; background:linear-gradient(135deg,#c47650,#6b3622); color:#fff; font:700 12px var(--font-d);` letter **"M"**.
- Two lines: **"Manager"** (Raleway `12.5px/700`, `color:var(--fg0)`) · **"Woosoo HQ"** (Raleway `11px/600`, `letter-spacing:0.04em`, `color:var(--accent)`).
- Light: name `#f2ebe0`, sub `#F6B56D`, border-top `#252220`.

---

## 2. TOPBAR

### Container
`height:52px; padding:0 24px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--bdr1); background:var(--bg0); flex-shrink:0;`
Light override: `background:#ffffff;`

### Left — page title + crumb
Driven by the current route. `display:flex; align-items:center; gap:10px;`
- **Title**: Raleway `18px / 800`, `letter-spacing:-0.01em`, `color:var(--fg0)`.
- **Separator** `·`: `color:var(--bdr3); font-size:18px; line-height:1;`
- **Crumb**: Raleway `10px / 700`, `letter-spacing:0.12em`, `text-transform:uppercase`, `color:var(--fg2)`.

Route → `[Title, Crumb]` map (use exactly):
```
dashboard     → ['Dashboard',          'Operations Overview']
orders        → ['Orders',             'Kitchen Dispatch']
pos           → ['POS',                'Live Table View']
monitoring    → ['Monitoring',         'System Health']
reverb        → ['Reverb',             'WebSocket Service']
menus         → ['Menus',              'Items & Availability']
packages      → ['Packages',           'Dining Tiers']
tablet-cats   → ['Tablet Categories',  'Menu Sync']
devices       → ['Devices',            'Tablet Management']
users         → ['Users',              'Staff & Permissions']
roles         → ['Roles',              'Access Control']
permissions   → ['Permissions',        'Guards & Abilities']
reports       → ['Reports',            'Analytics']
configuration → ['Configuration',      'System Hub']
settings      → ['Settings',           'App Preferences']
```

### Right — cluster (`display:flex; align-items:center; gap:8px;`), in this order:
1. **HQ Branch chip**: `padding:3px 10px; border-radius:6px; background:var(--accm); border:1px solid var(--accb); font:700 10.5px var(--font-d); letter-spacing:0.06em; color:var(--accent);` text **"HQ Branch"**.
2. **Search box** (`.search-box`): `display:flex; align-items:center; gap:7px; height:30px; padding:0 10px; border-radius:6px; border:1px solid var(--bdr2); background:var(--bg1); min-width:220px;`
   - search icon (13px, `color:var(--fg2)`)
   - `<input placeholder="Search…">`: borderless, transparent, `color:var(--fg0)`, `13px var(--font-s)`; placeholder `color:var(--fg3)`.
   - **⌘K** kbd hint: `font:10px var(--font-m); color:var(--fg3); background:var(--bg3); border:1px solid var(--bdr2); border-radius:4px; padding:1px 5px;`
3. **Theme toggle** — ghost icon button (30×30, transparent, hover `background:var(--bg3)`). Icon: `sun` when dark / `moon` when light. Flips `data-theme`, persist to `localStorage('nexus-theme')`.
4. **Refresh** — ghost icon button, `refresh` icon (14px).
5. **Notifications** — ghost icon button, `bell` icon (14px), with an accent dot: `position:absolute; top:6px; right:6px; width:6px; height:6px; background:var(--accent); border-radius:50%; border:2px solid var(--bg0);`
6. **Avatar**: `28×28` circle, same gradient as sidebar footer, letter **"M"**, `cursor:default`.

Ghost icon button base: `width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; background:transparent; border:0; color:var(--fg0); cursor:pointer;` hover → `background:var(--bg3);`

---

## 3. Icons (exact paths — these ARE the design)

All icons render inside `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="{size}" height="{size}">`. Use these exact inner paths (Lucide-derived, but match these literally):

```
dashboard  <rect x="3" y="3" width="8" height="10" rx="1.2"/><rect x="13" y="3" width="8" height="6" rx="1.2"/><rect x="13" y="13" width="8" height="8" rx="1.2"/><rect x="3" y="16" width="8" height="5" rx="1.2"/>
orders     <path d="M9 5H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>
pos        <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M8 4v16M2 9h6M2 14h6M10 9h4M10 13h4M16 9h2M16 13h2"/>
monitoring <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
reverb     <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
menu       <circle cx="6" cy="6" r="1.8"/><circle cx="6" cy="12" r="1.8"/><circle cx="6" cy="18" r="1.8"/><path d="M11 6h8M11 12h8M11 18h8"/>
package    <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
category   <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
tablet     <rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M10 18h4"/>
staff      <circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-3.5 4-6 7-6s6 2.5 7 6"/>
role       <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
lock       <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
branch     <line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/>
reports    <path d="M4 19h16"/><path d="M6 16v-5M10 16V8M14 16v-3M18 16V6"/>
config     <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>
settings   <circle cx="12" cy="12" r="3"/><path d="M19.4 14.5a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>
search     <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
bell       <path d="M6 8a6 6 0 0112 0v5l1.5 3h-15L6 13z"/><path d="M10 20a2 2 0 004 0"/>
refresh    <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
sun        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
moon       <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
```
(If a real Lucide install is present, the equivalent component names are: `LayoutDashboard, ClipboardList, Monitor, Activity, Zap, MoreVertical/AlignLeft, Package, LayoutGrid, Tablet, User, Users, Lock, GitBranch, BarChart3, SlidersHorizontal, Settings, Search, Bell, RefreshCw, Sun, Moon`. Prefer the exact paths above for pixel-fidelity.)

---

## 4. Acceptance checklist
- [ ] Sidebar is **224px**, dark in BOTH themes, with the WOOSOO / NEXUS stacked logo.
- [ ] Nav groups appear in the exact order: *(Dashboard)*, Operations, Catalog, Devices, People, Reports, System.
- [ ] Orders shows amber badge **7**; Devices shows **1**; Branches is dimmed/disabled.
- [ ] Active item: amber text + amber-tinted bg + 2px left rail.
- [ ] Topbar is **52px**, shows Title + uppercase Crumb from the route map, and the right cluster in order: HQ Branch chip → Search (⌘K) → theme toggle → refresh → bell (accent dot) → avatar M.
- [ ] Theme toggle flips topbar/page only, persists to `localStorage('nexus-theme')`; sidebar never lightens.
- [ ] Fonts: Raleway (display/labels), Kanit (nav/body), JetBrains Mono (badges, ⌘K).
