# Woosoo Nexus — Developer Handoff Prompt

Paste the block below to the developer or AI coding agent implementing the missing functionality.

---

You are implementing the outstanding functionality in the **Woosoo Nexus** restaurant admin console (a React + Babel prototype). Your single source of truth for what's built, what works, and what's missing is **`Woosoo Nexus - Functional Guide.html`**. Open it first and read it end to end. A machine-readable task list is at **`handoff/functional-tasks.json`**; a tickable version is **`handoff/FUNCTIONAL_CHECKLIST.md`**.

**Context**
- The app is `Woosoo Admin.html`, with screens split across `admin-app.jsx`, `admin-core.jsx`, `admin-screens.jsx`. Read these before changing anything.
- Korean-BBQ eat-all-you-can operation: dining *sessions* tied to *packages*, with *print jobs* (initial orders + refills) dispatched to the grill. Preserve this domain model.
- Visual/brand rules live in the existing `handoff/` bundle — do **not** restyle. Match the current components, amber accent, fonts, density, toast and modal patterns exactly.

**What to do**
1. Work the checklist **HIGH priority first**, then MED, then LOW. High items: Orders status actions + reprint + retry; POS void/mark-paid/terminal-switch; Roles & Permissions CRUD; Users invite/save/remove; Packages CRUD; Tablet Categories drag-to-reorder.
2. For each task, the **Verify** line is your acceptance test — implement until that observable behavior is true.
3. Make state changes **persist** within the session and reflect immediately in the UI (cards move columns, counts update, toggles hold).
4. Keep mock data flowing through existing structures; no backend unless asked — simulate where needed, but make every advertised control actually work.

**Rules**
- Don't break any function the guide marks green.
- No new colors, fonts, or layout patterns. Reuse existing `Btn`, `Pill`, `Icon`, toast and modal components.
- After each area, re-run its Verify steps and tick it off.

**Deliverable:** every checklist item satisfied, every Verify line passing, zero console errors, no regressions.

---
