# Copilot task: Cross‑repo improvements — statuses, filters, access model, and infra (tech-artificer org)

Scope (STRICT)
- Only these three repositories are in-scope for this work:
  - tech-artificer/woosoo-nexus (PRIMARY — KDS)
  - tech-artificer/tablet-ordering-pwa (PWA)
  - tech-artificer/relay-device (device/relay)
- Do NOT modify other repositories; do not propose changes outside these three repos.

Summary / Goal
- Implement a consistent, secure, and performant status & filtering model across the three repos. Enforce device-only API endpoints under Api/V1 with device auth; admin web controllers under Admin namespace and web routes (Inertia + Vue).
- Deliver PR-ready changes: enums, controllers (device/admin separation), frontend composables/components, tests, migrations, and a CI workflow. Improvements and enhancements beyond the baseline are welcome. If a full implementation cannot be completed in one pass, provide detailed TODOs and failing tests that describe remaining work.

Critical access rules (follow exactly)
- Device-only API:
  - Location: routes/api.php (prefer prefix /api/v1)
  - Controllers: app/Http/Controllers/Api/V1/*
  - Middleware: `auth:device` (or equivalent device guard), throttle, CORS
  - Devices may only call API endpoints; admin web must never use device API endpoints.
- Admin web (Inertia):
  - Controllers: app/Http/Controllers/Admin/*
  - Routes: routes/web.php or dedicated routes/admin.php (prefix /admin recommended)
  - Middleware: web, auth, can:admin (or equivalent)
  - Admin UI must not call device-only API endpoints.
- Shared/public endpoints must be explicitly approved and documented.

High-level objectives (must be satisfied)
1. Canonical status model (backend + frontend) with server-enforced transitions.
2. Device API endpoints: server-filtered, authenticated, minimal payloads, meta counts.
3. Admin web pages: Inertia/Vue pages with URL-persistent filters, multi-select statuses, chips, Clear button, debounced search, date presets, saved views, bulk actions, status counts, compact/virtualized lists.
4. Real-time + optimistic updates + conflict reconciliation (broadcasts).
5. Bulk operations with correct auth boundaries.
6. EventLogs & Devices: filters, exports, device health endpoints (where applicable).
7. Tests: API (device-auth), Admin (admin-auth), feature/integration tests for filters and transitions.
8. Performance: DB indexes, server-side pagination/cursor, virtualized lists.
9. CI & security: Add GitHub Actions workflow templates (lint/tests/security scan), enable Dependabot where missing.
10. Centralization: propose or scaffold a shared package only if needed (e.g., for enums); do not publish anything without approval.

Repository order (priority)
1. tech-artificer/woosoo-nexus — highest priority (KDS)
2. tech-artificer/tablet-ordering-pwa — PWA ordering client
3. tech-artificer/relay-device — device software / relay

Start tasks and order (woosoo-nexus first)
1. Backend enums (canonical)
   - app/Enums/OrderStatus.php
   - app/Enums/ItemStatus.php
   - If PHP < 8.1 in repo, create config/statuses.php instead.
2. Frontend constants
   - resources/js/constants/statuses.ts (strings must match backend exactly)
3. Device API skeleton and filtering (device-only)
   - routes/api.php additions (prefix /api/v1, middleware auth:device)
   - app/Http/Controllers/Api/V1/OrderController.php with index, show, updateStatus, bulkStatus endpoints
     - index accepts status[], item_status[], branch, station, since, until, search, page, per_page
     - index returns data[] and meta { total, page, per_page, counts: { pending, in_progress, ready, completed, cancelled } }
     - Ensure device auth is enforced and only minimal fields for devices are returned
4. Admin controllers & routes (Admin namespace, web)
   - routes/web.php or routes/admin.php additions (prefix /admin, middleware web/auth/can:admin)
   - app/Http/Controllers/Admin/OrderController.php returning Inertia::render(...) for index/show and admin updateStatus/bulk endpoints
   - Admin controllers must be distinct from device API controllers and expose admin-only fields
5. Frontend admin filter UI (Inertia)
   - resources/js/composables/useFilters.ts (Inertia-compatible; read/write URL query params; debounced search 300ms; multi-select status)
   - resources/js/components/FilterBar.vue (multi-select statuses with counts, date presets, chips, Clear button)
   - resources/js/pages/Orders/Index.vue wired to useFilters and FilterBar (admin route)
6. DB & migrations
   - Add migrations to create DB indexes on status, created_at, branch_id
   - Provide an artisan command or seed to normalize statuses if needed; if normalization is risky, provide clear TODO and non-destructive script
7. Tests
   - tests/Feature/Api/OrderApiTest.php: device-only access, filters returning expected counts, updateStatus success/failure
   - tests/Feature/Admin/OrderAdminTest.php: admin-only access, filter persistence (URL)
   - Add failing tests that describe remaining behavior if full implementation is not done
8. CI
   - .github/workflows/ci.yml to run PHP tests (phpunit/pest), node lint/test, and basic security audit

Implementation rules (MUST follow)
- Device endpoints: app/Http/Controllers/Api/V1; protect with auth:device.
- Admin controllers: app/Http/Controllers/Admin; protect with web middleware + admin permission.
- Always match status string constants between backend and frontend exactly.
- Return minimal device payloads; admin routes may return additional fields.
- When producing files in a PR, include file blocks for each file and commit as separate logical commits.
- If blocked by environment or repo constraints, produce a clear blocking message and TODOs/tests describing required next steps.

PR & branch guidance
- Branch: feat/orders-filters-statuses-auth
- Use conventional commits (feat:, test:, chore:)
- PR description must include summary, files changed, access model, API contract, migration notes, manual QA checklist, and acceptance criteria.

Acceptance criteria
- Device API endpoints require device auth and return minimal fields.
- Admin controllers are under Admin namespace and require admin auth.
- Filters persist in URL for admin pages and return expected results.
- Status transitions validated server-side; optimistic UI updates reconcile correctly with broadcasts.
- Bulk operations function correctly and update counts in UI meta.
- CI runs tests and linting (or failing tests documented with TODOs).

Deliverable format
- Provide all created/modified files as file blocks in PR-ready form.
- If full implementation not possible, include detailed TODOs, failing tests that capture expectations, and instructions for how to complete remaining work.

Begin with woosoo-nexus now (follow the Start tasks order above). After finishing woosoo-nexus, proceed to tablet-ordering-pwa and relay-device in separate PRs following the same rules. If any blocking issues (missing PHP version, auth guard not present, missing dependencies), produce a clear blocking message and next steps.