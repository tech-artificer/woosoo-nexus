---
status: canonical
last_reviewed: 2026-05-14
verified_by: route list + active test suite
scope: woosoo-nexus
---

# Woosoo Nexus Stabilization and Hardening Audit

## 1. Executive summary

- `woosoo-nexus` is the Woosoo system backbone: Laravel admin panel, device-facing API, deployment/orchestration, realtime wiring, and ops surfaces. It owns the single source of truth for branch-local restaurant state. The tablet PWA is a client; `woosoo-print-bridge` remains the active printer worker in MVP mode by default.
- The biggest risks are not feature gaps — they are **scoping and authorization gaps**: branch scoping is weak in admin controllers; broadcast channel auth is too permissive (`admin.print` and `service-requests.{deviceId}` return `true`); a GET-based credential endpoint still exists; the `SessionApiController@reset()` device guard is likely broken.
- Two parallel print stories ship at once: the active MVP path defers to the Print Bridge while feature-flagged native `PrintEventService` code remains in place. Toggling the flag mid-rollout would produce mixed behavior.
- Tests are closer to truth than docs. `docs/API_MAP.md` is stale on `/api/health`, `/api/session/latest`, `/api/devices/register`. Treat the test suite as the contract source while contracts are being formalized.
- Operationally, the admin UI mixes containerized-runtime tooling with Windows/NSSM rescue tooling (`ReverbController` uses `shell_exec` against `C:\laragon\bin\nssm\...`). Choose one runtime model and remove the mismatch.

## 2. Runtime facts

### 2.1 Role and ownership

This app owns:

- Device registration and token lifecycle (`app/Http/Controllers/Api/V1/Auth/DeviceAuthApiController.php`)
- POS/Krypton order/session integration (`app/Services/Krypton/*`, `app/Http/Controllers/Api/V1/SessionApiController.php`)
- Refill idempotency and print-event generation (`app/Http/Controllers/Api/V1/OrderApiController.php`, `app/Services/DurableRefillGuard.php`)
- Monitoring/Reverb controls (`app/Http/Controllers/Admin/MonitoringController.php`, `app/Http/Controllers/Admin/ReverbController.php`)
- Docker runtime for nginx/app/queue/scheduler/pulse/reverb/tablet-pwa/mysql/redis (`compose.yaml`)

### 2.2 Main modules

High-signal directories:

- `app/Http/Controllers/Admin` — admin UI/ops surfaces: orders, devices, POS, reports, monitoring, reverb, settings
- `app/Http/Controllers/Api/V1` and `Api/V2` — device API, printer API, tablet API v2, deployment info
- `app/Services` — order processing, print events, refill guard, POS connection, branch resolution
- `app/Models` and `app/Enums` — device/order/print/refill state and enums
- `routes/web.php`, `routes/api.php`, `routes/channels.php` — runtime entry points
- `resources/js/pages` — Inertia admin/auth/settings pages
- `config/pulse.php`, `config/reverb.php`, `config/queue.php`, `config/device.php` — runtime behavior
- `routes/console.php` — scheduled retries, cleanup, Pulse ingest, stale-heartbeat checks
- `compose.yaml`, `docker/entrypoint.sh` — canonical runtime/deploy path

Major domains:

1. **Admin operations** — dashboard, orders, devices, menus, packages, tablet categories, users/roles/permissions, reports
2. **Device lifecycle** — create device, assign table, generate security code, register, login, refresh, logout, lookup-by-ip
3. **Ordering** — create order, fetch orders, refill, print/printed state, service requests
4. **Printing** — legacy direct print scaffolding plus gated PrintEvent flow
5. **Realtime** — Reverb broadcast channels plus admin-side service controls
6. **Monitoring/ops** — health endpoint, Pulse, monitoring dashboard, deployment info, scheduler jobs
7. **POS/Krypton integration** — current session, menu data, package/category data, external POS writes

### 2.3 Route and page inventory

| Surface | Examples | Status | Notes |
|---|---|---:|---|
| Public web | `/`, `/devices/certificate`, `/devices/download-certificate` | implemented | Home is certificate bootstrap, not dashboard |
| Auth web | `/login`, `/forgot-password`, `/reset-password/{token}` | implemented | Breeze-style |
| Public registration | `/register` | orphaned by design | Route aborts 404 while page/controller still exist (`RegisteredUserController`, `resources/js/pages/auth/Register.vue`) |
| Admin web | `/dashboard`, `/orders`, `/pos`, `/menus`, `/devices`, `/users`, `/roles`, `/permissions`, `/branches`, `/reports/*`, `/monitoring`, `/reverb`, `/configuration` | implemented | Mostly Inertia, gated by `auth + can:admin` |
| Admin settings API | `/admin/api/settings`, `/admin/settings` | implemented | Closure-based, branch-backed JSON |
| Device guest API | `/api/devices/register`, `/api/devices/login`, `/api/device/lookup-by-ip` | implemented | Device bootstrap |
| Device order/session API | `/api/devices/create-order`, `/api/device-orders`, `/api/order/{orderId}/refill`, `/api/sessions/current` | implemented | Core workflow |
| Compatibility aliases | `/api/order/{orderId}/print-refill`, `/api/session/latest`, `POST /api/sessions/join` | duplicated / suspicious | `sessions/join` is NOT a join — calls `SessionApiController@current` |
| Printer API | `/api/printer/*`, `/api/print-events/unprinted`, `/api/orders/{orderId}/printed` | partial | Hard-gated by `print_events.enabled`; default 503 when off (`PrintEventFeatureFlag`) |
| Health/debug | `/api/health`, `/api/config`, `/api/deployment-info`, `/api/debug/pos/menus/course` | implemented / partial | Health is real (MySQL/POS/Redis/queue); debug is dev-only |
| API resource leftovers | `/api/devices/create`, `/api/devices/{device}/edit` | stubbed | Emitted by `Route::resource` but not meaningful for a JSON API |

Implemented Inertia pages: `Dashboard.vue`, `Orders/Index.vue`, `POS/Index.vue`, `Menus/Index.vue`, `Devices/*`, `Users/*`, `roles/*`, `branches/*`, `package-configs/IndexPackageConfigs.vue`, `tablet-categories/IndexTabletCategories.vue`, `Monitoring/Index.vue`, `Admin/Reverb.vue`, `Admin/Settings.vue`, `reports/*`, `settings/*`, `auth/*`.

Suspicious / orphaned pages and controllers (also called out in Section 4 and Section 5):

- `resources/js/pages/auth/Register.vue` (unreachable)
- `resources/js/pages/reports/sales/*` (abandoned duplicate report UI)
- `app/Http/Controllers/Api/V1/PrintController.php` (no route)
- `app/Http/Controllers/Api/EventReplayController.php` (no route)
- `app/Http/Controllers/Api/V1/ServiceMonitorController.php` (no route)

### 2.4 Major workflows

**Authentication / authorization.** Admin auth = standard Breeze-style session auth (`routes/auth.php`). Device auth is custom:

- `POST /api/devices/register` validates `security_code` (or legacy `passcode`), resolves device by hashed setup code, clears it, stores IP/last_seen, deletes old tokens, issues a 30-day Sanctum token.
- `GET /api/devices/login` is IP-based login for already-claimed devices with optional global passcode fallback from `config/device.php`.
- `POST /api/devices/refresh` revokes the current token and issues a 7-day token.
- `POST /api/devices/logout` deletes the current token.

Authorization is inconsistent: `DeviceOrderPolicy` only checks `is_admin` and ignores branch scoping; some APIs enforce ownership/branch in-controller (`OrderApiController`, `ServiceRequestApiController`, `PrinterApiController`), but admin controllers often do not.

**Admin operations.** Real operational screens, not placeholders. Wired to controllers via `routes/web.php`. `Admin\Device\DeviceController` loads all devices including soft-deleted ones and computes stats client-side. Branch scoping is weak/absent.

**Device registration.** First-use registration is security-code based, not IP based. IP is mutable metadata (per controller comments). Failure paths: invalid 6-digit code → 422; inactive/trashed device → 409; duplicate IP/name/code → 409; login before claim → 403; NAT/proxy/IP mis-resolution breaks later lookup.

**Session bootstrap.** No separate "join" implementation — `POST /api/sessions/join` calls the same `SessionApiController@current()` as `GET /api/sessions/current`. `current()` fetches the latest Krypton session, nulls it out if closed, returns `data`, `server_time`, `session_started_at`, `session_duration_seconds`. `/api/devices/latest-session` returns a different shape `{ session: ... }`.

**Ordering.** No quote workflow — direct submit via `POST /api/devices/create-order`. Flow:

1. Validate intent in `StoreDeviceOrderRequest`
2. Optional `X-Idempotency-Key` with Redis cache/lock
3. Reject if device has existing `pending` or `confirmed` order
4. Delegate to `App\Services\Krypton\OrderService`
5. Broadcast `OrderCreated`
6. Return 201 or cached replay

Device must have `table_id`; active POS session must exist; package/menu IDs normalized server-side; totals recalculated server-side. Failure paths: no table assigned, unavailable menu item, missing POS session, POS DB error, duplicate in-flight request.

**Refill.** `POST /api/order/{orderId}/refill` uses `RefillOrderRequest`, device ownership checks, branch/session guards, terminal-status rejection, and `DurableRefillGuard` for DB-backed idempotency. Refill state machine:

`NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED`, with `FAILED → PROCESSING` retry.

`POST /api/order/{orderId}/print-refill` is an alias to the same handler.

**Service call.** `GET /api/tables/services` returns all `TableService` rows. `POST /api/service/request` creates a request tied to a device order and broadcasts `ServiceRequestNotification` after commit.

**Printing.** Two stories:

1. **Current MVP path** — defers real printing to `woosoo-print-bridge`; print-event HTTP endpoints feature-flagged off by default (`PrintEventFeatureFlag.php`, `PrintEventService.php`).
2. **Native PrintEvent path** — `PrinterApiController` + `PrintEventService` implement polling, reserve, ack, fail, mark-printed, heartbeat. Concurrency-aware: `reserve()`, `ack()`, `fail()` use DB transactions and `lockForUpdate()`. Scheduler jobs reset stale `RESERVED` events and retry unacknowledged ones (`routes/console.php`).

**Queue.** Canonical runtime uses Redis queues. Main worker: `queue:work redis --tries=1 --timeout=30`. Generic jobs get one attempt unless they implement their own retry. Print workflows compensate with explicit scheduled retries: reset stale reserved print events; retry unacknowledged; check stale relay heartbeats; purge old acked/dead-letter events.

**Realtime.** Reverb configured as broadcaster + WebSocket server (`config/broadcasting.php`, `config/reverb.php`, `compose.yaml`). Channels: `device.{deviceId}` and `orders.{orderId}` properly checked; `service-requests.{deviceId}` returns `true` (too permissive); `admin.print` returns `true` (too permissive).

**Health & monitoring.** `/api/health` checks MySQL, POS DB, Redis, queue depth, version, environment, uptime. Pulse installed at `/pulse` with `Authorize` middleware and `Gate::define('viewPulse', ...)`. `Admin\MonitoringController` builds its own dashboard from `device_orders`, `print_events`, `jobs`, `failed_jobs`, and DB health.

**Deployment.** Canonical = Docker Compose with `nginx`, `app`, `queue`, `scheduler`, `pulse`, `reverb`, `tablet-pwa`, `mysql`, `redis` (`docs/deployment/production-docker.md`, `compose.yaml`). `compose.yaml` still uses source bind mounts (not immutable). `docker/entrypoint.sh` auto-runs `php artisan migrate --force` on app container boot.

### 2.5 Models / state

Stateful models: `Device`, `DeviceOrder`, `DeviceOrderItems`, `PrintEvent`, `RefillSubmission`, `ServiceRequest`, `DeviceHeartbeat`. Tablet config: `Package`, `PackageModifier`, `TabletCategory`, `TabletPackageConfig`, `TabletPackageAllowedMenu`.

State machines:

- `OrderStatus`: `confirmed → completed | voided | cancelled`. `pending` retained as legacy safety net (`app/Enums/OrderStatus.php`).
- `PrintEventStatus`: pending/reserved/printed/failed lifecycle.
- `RefillSubmission`: explicit durable state machine (see Refill workflow above).

Lifecycle assumptions:

- Device orders are created as confirmed, not quoted.
- One device cannot have another active pending/confirmed order.
- Refill retries keyed by `device_id + device_order_id + client_submission_id`.
- Print-event retries are scheduler-driven as well as queue-driven.
- Soft deletes used on devices, device orders, print events, and related models.

Known stale-state risks:

- `RefillSubmission` has `processing_started_at` and `isLockExpired()`, but no recovery job uses that timeout.
- `print_events.device_order_id` uses `cascadeOnDelete()` in the original migration — risky for audit history (`database/migrations/2025_12_14_000000_create_print_events_table.php`).
- `PrintEvent` carries both `attempts` and `attempt_count`, which can diverge.
- `OrderStatus::PENDING` is kept despite the enum comment that live code should not create it.

## 3. Contracts impacted

| Endpoint | Owner | Likely caller | Contract summary | Auth | last_verified | Notes |
|---|---|---|---|---|---|---|
| `POST /api/devices/register` | `DeviceAuthApiController@register` | tablet / device first claim | `security_code` or `passcode`; optional IP/name/app info; returns token, device, table, broadcasting | guest + throttle | 2026-05-14 | **Docs mismatch:** `API_MAP.md` says 201; code returns 200. Tests: `DeviceAuthRegisterTest.php`. |
| `GET /api/devices/login` | `DeviceAuthApiController@authenticate` | claimed device login | IP lookup + optional global passcode fallback | guest | 2026-05-14 | IP-sensitive. Tests: `DeviceTokenLifecycleTest.php`. |
| `GET /api/device/lookup-by-ip` | `DeviceAuthApiController@lookupByIp` | Print Bridge bootstrap | `{ found, device { device_id, auth_token, ... } }` or unclaimed/not-found | guest | 2026-05-14 | Pure IP identity. |
| `POST /api/devices/create-order` | `DeviceOrderApiController::__invoke` | tablet | `guest_count`, `package_id`, `items[*]`, optional `session_id`, idempotency header; returns `{ success, order }` | `auth:device` + throttle | 2026-05-14 | Well covered: `DeviceCreateOrderConflictTest`, `TransactionRollbackTest`, `DeviceOrderIntentContractTest`. |
| `GET /api/sessions/current` | `SessionApiController@current` | tablet | `data` + `server_time` + timing metadata | `auth:device` | 2026-05-14 | Live current-session contract. |
| `POST /api/sessions/join` | `SessionApiController@current` | unclear / compat | same as current | `auth:device` | 2026-05-14 | **Suspicious:** not a join. |
| `GET /api/session/latest` | `SessionApiController@current` | PWA compat alias | same as current | `auth:device` | 2026-05-14 | **Docs mismatch:** `API_MAP.md` differs. |
| `GET /api/devices/latest-session` | `SessionApiController@latestSession` | Print Bridge | `{ session: ... }` | `auth:device` | 2026-05-14 | Different shape than `/sessions/current`. |
| `POST /api/order/{orderId}/refill` | `OrderApiController@refill` | tablet | validated refill items, `client_submission_id`; returns cached replay or created rows/order | `auth:device` | 2026-05-14 | Strong coverage: `OrderRefillTest`, `RefillIdempotencyTest`. |
| `POST /api/service/request` | `ServiceRequestApiController@store` | tablet | `table_service_id`, `order_id`; row + broadcast | `auth:device` | 2026-05-14 | Transaction + `DB::afterCommit()`. |
| `GET /api/v2/tablet/packages` | `TabletApiController@packages` | tablet | cached package payload | `auth:device` | 2026-05-14 | DB config with legacy fallback IDs 46/47/48. |
| `GET /api/v2/tablet/categories` | `TabletApiController@categories` | tablet | DB-backed or hardcoded fallback | `auth:device` | 2026-05-14 | Hybrid config. |
| `POST /api/printer/print-events/{id}/ack` | `PrinterApiController@ackPrintEvent` | Print Bridge | ack with printer metadata + timestamp | feature-flagged | 2026-05-14 | Tests: `PrinterPrintEventsTest`, `PrintEventFeatureFlagTest`. |
| `GET /api/token/create` | `AuthApiController@createToken` | unclear / legacy | `email`, `password`, `device_name` → token | guest | 2026-05-14 | **Risky:** GET-based credential endpoint. |
| `GET /api/token/verify` | inline closure in `routes/api.php` | device/client | bearer validity | `auth:device` | 2026-05-14 | **Duplicated:** `DeviceAuthApiController::verifyToken()` exists but is not routed. |

Tests cover current contracts better than docs do. `docs/API_MAP.md` is stale on `/api/health`, `/api/session/latest`, `/api/devices/register` — that file has been archived to `docs/archive/2026-05/API_MAP.md` as part of the 2026-05-14 audit pass.

## 4. Issues by severity

### Critical

1. **Branch/tenant scoping is weak in admin and API.** `Admin\Device\DeviceController@index()` loads all devices; `Api\V1\DeviceApiController@index()` also returns all devices. Risk: cross-branch data visibility. → Fix: centralize branch-aware scope on all device/order queries.
2. **`DeviceOrderPolicy` is too coarse.** Only returns `is_admin`; ignores branch ownership (`app/Policies/DeviceOrderPolicy.php`). Risk: wrong access model if non-global admins exist.
3. **Session reset auth is likely broken for devices.** Route uses `auth:sanctum`, but `SessionApiController@reset()` checks `get_class($user) === '\\App\\Models\\Device'` — PHP's `get_class()` does not return a leading backslash. → Fix: replace with `$user instanceof \App\Models\Device` or equivalent.
4. **Broadcast channel auth is too permissive.** `service-requests.{deviceId}` returns `true`; `admin.print` returns `true` (`routes/channels.php`). Risk: over-broad realtime subscriptions. → Fix: enforce device ownership and admin-only auth.
5. **Guest credential endpoint uses GET.** `/api/token/create` is registered as `GET` and validates `email/password/device_name` (`routes/api.php`, `AuthApiController.php`). Risk: credentials in query strings, logs, and proxy caches. → Fix: convert to `POST` body-only, or remove if legacy-only.

### High

6. **Bulk print acknowledgement is not safely scoped.** `PrinterApiController::markPrintedBulk()` loops one order at a time with no transaction and no per-order branch check. Risk: partial success, cross-branch mutation, race conditions. → Fix: wrap in transaction + branch enforcement.
7. **Timezone handling is inconsistent.** Print ack/printed timestamps are manually converted to `Asia/Manila` in both `PrintEventService.php` and `PrinterApiController.php`. Risk: contract drift vs UTC clients. → Fix: standardize on UTC at storage boundary; convert at presentation only.
8. **Realtime/ops management is environment-inconsistent.** Documented runtime is Docker, but `Admin\ReverbController` is a Windows/NSSM shell wrapper (`shell_exec`, `C:\laragon\bin\nssm\...`). Risk: admin UI implies control over the wrong runtime. → Fix: choose one runtime model; remove the mismatch from admin UI.
9. **Printer feature flag is only HTTP-deep.** Middleware blocks endpoints, but `PrintEventService` still contains native print-event logic. Risk: mixed behavior if toggled mid-rollout. → Fix: gate at service entry, not just HTTP.
10. **No stale-refill recovery.** `RefillSubmission` exposes lock-expiry semantics but no scheduler job consumes them. Stuck `PROCESSING`/`POS_CREATED`/`MIRRORED` rows linger.

### Medium (dead code, cleanup, governance)

11. Orphaned controllers without routes: `app/Http/Controllers/Api/V1/PrintController.php` (direct escpos/Windows printing), `app/Http/Controllers/Api/EventReplayController.php`, `app/Http/Controllers/Api/V1/ServiceMonitorController.php` (runs `ps aux | grep`, Linux-only, not aligned to Docker).
12. Unreachable UI: `resources/js/pages/auth/Register.vue` + `RegisteredUserController@create()` (route disabled).
13. Duplicate report UI tree: `resources/js/pages/reports/sales/*` is not referenced by active routes.
14. `Route::resource('/devices', DeviceApiController::class)` emits `create/edit` API routes that do not belong in a JSON API.
15. `api/token/verify` exists as an inline closure while `DeviceAuthApiController::verifyToken()` also exists.
16. `ProcessOrderLogs` scheduling removed because its table does not exist in production — leftover scaffolding in `routes/console.php`.
17. No unified contract source of truth; tests are ahead of docs.
18. `compose.yaml` still uses source bind mounts and explicitly states this is not immutable production hardening yet.
19. `docker/entrypoint.sh` auto-runs `php artisan migrate --force` on app container boot — needs an explicit rollback story.

### Low

20. `Admin\MonitoringController::orphanedOrders` is hardcoded to `0` because the intended cross-connection check was abandoned. Either implement or remove from the dashboard.
21. `Admin\Device\DeviceController` computes stats client-side after loading all devices — duplicate work and a hidden source of branch-leak.

## 5. Action items (prioritized)

1. **Branch-scope everywhere.** Start with `Admin\Device\DeviceController`, `Api\V1\DeviceApiController`, and any order/device admin queries. *Acceptance:* a non-global admin user cannot read another branch's devices/orders. *Rollback:* feature-flag the scope and revert.
2. **Harden broadcast authorization.** Fix `admin.print` and `service-requests.{deviceId}` in `routes/channels.php`. *Acceptance:* an unauthenticated subscriber is rejected; per-device channels require ownership. *Rollback:* revert channel file.
3. **Fix `SessionApiController@reset()` device guard.** Replace string comparison with `instanceof`. *Acceptance:* a feature test exercises the device path and passes. *Rollback:* trivial.
4. **Wrap `PrinterApiController::markPrintedBulk()` in a transaction with per-order branch checks.** *Acceptance:* partial-success scenario writes nothing on failure. *Rollback:* trivial.
5. **Retire the GET credential endpoint** `/api/token/create` (or convert to POST). *Acceptance:* curl with credentials in query string returns 405/410. *Rollback:* re-add route.
6. **Choose one runtime control model for Reverb.** Either remove the Windows/NSSM admin path or move it to a clearly-flagged local-only tool. *Acceptance:* admin UI matches the canonical container runtime.
7. **Add stale-refill recovery.** Scheduler job consumes `RefillSubmission::isLockExpired()` and re-queues to `PROCESSING` (or marks `FAILED`). *Acceptance:* simulated stuck refill resolves automatically.
8. **Gate the native print-event path at the service entry,** not just HTTP. *Acceptance:* toggling `print_events.enabled` cannot produce mixed behavior in mid-rollout.
9. **Generate or verify contracts from controllers/tests.** Replace `docs/API_MAP.md` (now archived) with auto-derived or test-pinned contract documentation. *Acceptance:* a CI step fails when a controller diverges from documented contract.
10. **Cleanup pass (after contracts stable):** delete or quarantine `PrintController`, `EventReplayController`, `ServiceMonitorController`; remove unreachable UI; collapse alias routes with explicit deprecation headers.

## 6. Verification plan

```bash
# From platform root
bash scripts/pre-merge-check.sh --app woosoo-nexus
```

Wraps:

- `composer test` — the existing Pest/PHPUnit suite is the contract source of truth.
- `php artisan route:list` — diff against expected route inventory; flag new or removed routes.
- `php artisan config:clear` — sanity check the config cache is clean.

Manual smoke after auth/scoping changes:

1. Register a fresh device with a 6-digit code; confirm token issued and old tokens revoked.
2. Submit an order from a registered device; confirm `OrderCreated` broadcast and no duplicate on retry with same `X-Idempotency-Key`.
3. Refill the order with a `client_submission_id`; replay returns cached result.
4. Subscribe to `admin.print` as an unauthenticated session; expect rejection.
5. As a non-global admin, attempt to read another branch's devices; expect empty list.

## 7. Cross-references

- [Ecosystem review](../../docs/WOOSOO_ECOSYSTEM_ENGINEERING_REVIEW_2026-05-14.md) — cross-app context
- [Tablet PWA audit](../../tablet-ordering-pwa/docs/TABLET_ORDERING_PWA_PRODUCTION_STABILITY_AUDIT_2026-05-14.md) — tablet client side of the same contracts
- [Print Bridge audit](../../woosoo-print-bridge/docs/WOOSOO_PRINT_BRIDGE_PRODUCTION_RELIABILITY_AUDIT_2026-05-14.md) — the active printer worker
- [Print events contract](print-events-contract.md) — active print event contract (planning doc archived)
- [Device registration policy](DEVICE_REGISTRATION_IDENTITY_AND_IP_POLICY_2026-04-25.md) — current device identity policy
- [Session redirect postmortem](SESSION_REDIRECT_AND_SERVICE_REQUEST_POSTMORTEM_2026-04-24.md) — historical session/service-request incident
- [Production docker deployment](deployment/production-docker.md) — canonical deployment flow
- [Documentation audit 2026-05-14](../../docs/audits/DOCS_AUDIT_2026-05-14.md) — what moved where in the 2026-05-14 cleanup

## Addendum 2026-05-17 — Deferred contract change: `/api/health` broadcasting integrity

A Reverb/broadcasting integrity workstream (adds `checkBroadcastingIntegrity()`
to the inline `/api/health` closure in `routes/api.php` (+~88) and to
`Api/HealthController.php` (+~90), plus `VerifyIntegrityCommand` and
`SessionApiController` touches) was developed but is **deliberately NOT merged
to `staging`**. It is a **contract-surface change to `/api/health`** that has
**not** been independently reviewed and conflicts with audit item A1
(HealthController orphan delete-vs-extend) and duplicates logic across
`routes/api.php` and the controller.

Status: **quarantined** on branch `feature/nexus-broadcast-integrity`
(commit `32aaf2a`). `staging` ships the unchanged (current) `/api/health`
behaviour. Before this can land: independent review, a definitive `/api/health`
contract spec entry, and resolution of audit A1. Tracked as a separate task;
does not block the `feature/tablet-strict-contract` → `staging` merge (that
branch does not contain this change).

Related: the POS-first violation found this session (`OrderService::
compensatePosOrder()` manual POS deletes) was **rejected** per
`krypton_woosoo_specs.md` Issue A; the two tests that demanded it were
rewritten to assert POS-first reality. An out-of-band POS reconciliation
worker is the correct future replacement (not a correctness blocker for
staging).
