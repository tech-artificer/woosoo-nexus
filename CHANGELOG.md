# Changelog

All notable changes to this repo are documented here.
Format: Keep a Changelog; commits follow Conventional Commits.

### Bug Fixes

- Remove stale woosoo:sync-package-modifiers reference (command was removed during the package_modifiers → package_allowed_menus consolidation)
- Same-origin URL generation across multi-host access
- Address remaining CodeRabbit review threads
- SaveQuietly for PENDING→CONFIRMED; unbind pusher handlers on unmount
- Address second CodeRabbit review batch
- Address remaining CodeRabbit review comments
- Address second CodeRabbit review batch on PR #205
- Address CodeRabbit review comments on PR #205
- Remove dead print listeners from admin and KDS
- Rename OrderPrinted::broadcastAs() to 'order.print_confirmed'
- Restore CheckSessionIsOpened with narrowed scope
- Add distinct validation to menu_ids
- Close TOCTOU race in recall cap, gate canRecallTicket, eager-load device_order (#204)
- Resolve vue-tsc type errors in Packages/Index.vue
- Remove unused catch binding in paginationLinks computed
- Nex-case-025 code-review follow-ups (nav guard, navBadges typing, theme cookie)
- Use toBeGreaterThanOrEqual (Pest v3 method name)
- Make AdminShellBadgeServiceTest resilient to shared dev database
- Wire initializeNexusTheme in app.ts boot
- Follow-up — complete index map, categories API, and add tests
- Resolve 10 bugs in PackageConfig and TabletCategory
- Prevent credential revert when system_settings empty or APP_KEY rotates
- Also exclude items.menu from eager-load when POS unreachable
- Guard KdsController::index() against POS connection failure
- Admin UI audit fixes across POS, devices, and config pages
- Add POS connection pre-flight guard and graceful disconnected UI (nex-021)
- Make order broadcast resilient to POS outage
- Register missing routes and stub controllers (nex-case-022 Track 4)
- Simplify KDS to Preparing/Served and wire backend API
- Guard null print dispatch and sanitize pos.fill-order errors (nex-case-022 Track 2)
- Remove unused imports failing CI eslint on dev
- Log debug POS exception, return generic error message (nex-case-019)
- Edge-to-edge fullscreen + kiosk-proof body reset (nex-case-018)
- Strip client-supplied price from refill payload (nex-case-017)
- Remove non-existent tables.index route that crashed AppSidebar
- Address audit findings — locked-row save, terminal TOCTOU, stale broadcast, mock fallback
- De-shout typography with tokenized weight scale
- Guard toggleItem on terminal orders and move Mark Ready gate into transaction
- Fullscreen board layout and Mark Ready gating
- Enforce intent-only payload on device order create
- Harden apply-woosoo-config.sh per review
- Sync apply-woosoo-config.sh with platform WOOSOO_ENV implementation; fix nex-011 case run state
- Remove PrintOrder re-dispatch from markPrinted ack paths
- Address PR #160 P2 review comments on refreshDetailFromPos
- Refresh POS detail values before broadcasting
- Align BRD order lifecycle with actual OrderStatus enum
- Harden npm ci against WiFi drops on Pi (INFRA-CASE-003)
- Address review comments — href guard, types, token consistency, DRY
- Restore handover manual DOCX build script
- Restore files still referenced from live config
- SessionReset implements ShouldBroadcastNow for immediate dispatch
- Driver-gate package_modifiers constraint drop for SQLite tests
- Reka Checkbox emit name + drop modifier constraints
- PublicOrigin CLI escape hatch + Dockerfile mkdir bootstrap/cache
- PublicOrigin CLI escape hatch + Dockerfile mkdir bootstrap/cache
- Restrict DeviceOrderItems mass-assignment with explicit fillable
- Use Krypton MySQL NOW() for date_time_opened to prevent table timer drift
- Honor uploaded MenuImage on tablet + admin endpoints
- Convert ternary statements to if/else in Monitoring/Index.vue
- Guard refresh() and logout() against TransientToken crash (nex-case-008)
- Guard refresh() and logout() against TransientToken crash
- Return public host in client config
- Allow authenticated devices to call /api/devices/register
- Accept GET on /api/devices/login (tablet uses api.get for IP auth)
- Remaining coderabbitai review findings
- Address coderabbitai review findings
- SessionReset broadcasts synchronously like other order events
- Return FAILURE when POS DB unreachable in sync command
- Stop writing to non-existent device_orders.print_event_id
- Switch default placeholder to 2.webp + document chain
- Skip silently when POS DB is unreachable
- Production operability hardening
- Always attach items to initial PrintEvent
- Restrict force-end endpoint to admin users only
- Dispatch SessionReset when payment status closes an order
- Polling returns correct items per print event, full name first
- Use menu full name (not receipt_name code) for refill print events
- Hydrate packages from krypton menus
- Phase 1 correctness batch — UTC cast, ack UTC, void guard, terminal scope, cors comment
- POS-first contract correction for sibling rollback test
- Refill/print hardening + POS-first contract test correction
- Correct session-reset device auth check
- Correct residual test-side fixtures, auth and contract assertions
- Stabilise test suite + fix two refill/print production bugs
- POS refill idempotency - prevent duplicate ordered_menu inserts on retry
- Add device API endpoints to CSRF exemption list
- Add API CSRF exemption middleware to prevent 419 errors on API routes
- Fix print event lifecycle loop and package modifiers display
- Align staging order flow and tests
- Add --no-install-recommends and non-root USER to Dockerfile.processor
- Return scalar price_level_id from getMenuPriceLevel()
- Extend ASSET_URL regex to catch quoted dotenv values
- Remove invalid quotes from MySQL INTERVAL expressions
- Replace bash-only 'source .env' with POSIX '. .env'
- Make DeviceOrderFactory financials internally consistent
- Fix stdin redirection and replace hardcoded credentials in WINDOWS_QUICKSTART.ps1
- Replace hardcoded credentials in WINDOWS_STEPS.txt with placeholders
- Address valid findings from staging review
- Upgrade pwa-ci Node from 18 to 22; document compose bind-mount scope
- Resolve docker compose config failure in CI
- Align backend validators and refill auth to tablet staging payload
- Address PR review comments for staging branch
- Replace MySQL FIELD() with database-agnostic CASE statement for SQLite compatibility
- Wrap complete() in DB::transaction, add missing admin pages, harden tests
- Allow preregistered tablet ip login
- Use url('/') instead of hardcoded 127.0.0.1 in SessionExpiryHandlingTest
- Tighten Echo type in globals.d.ts from any to 'reverb'
- Use url('/') instead of hardcoded 127.0.0.1 in SessionExpiryHandlingTest
- Tighten Echo type in globals.d.ts from any to 'reverb'
- Transaction integrity hardening and security code column fix
- Resolve all CI failures (typecheck, build-and-test, quality)
- Align log level and device registration rate-limit coverage
- Preserve 503 contract, add POS unavailable mapping and CI session determinism
- Remove test-command dependency in bulk complete and add route coverage
- Remove duplicate route and double broadcast dispatch in order complete
- Sidebar nav — restore Packages, collapse Reports, add Monitoring
- Restore active-branch metadata and stabilize validation assertions
- Align refill broadcast payload with order confirmation
- Align DeviceRegisterRequest message keys with required_without rules
- Remove innodb-buffer-pool-size from MySQL command — caused crash loop
- Raise MySQL max_connections to 500 and extract cert path resolver
- Cert download on HTTPS, real client IP via FastCGI, code cleanup
- Add created_at and updated_at to Role interface in EditRole.vue
- Serve CA cert over HTTP for device trust bootstrap
- Docker cache dir, session config, deploy script robustness, accessibility page
- Cert SAN, DEVICE_AUTH_PASSCODE in deploy script, track docker config files
- Add NUXT_PUBLIC_DEVICE_AUTH_PASSCODE to tablet-pwa service env
- Add Docker entrypoint for Laravel app container
- Add PHP-FPM pool config for Docker build
- Switch PHP-FPM to TCP 9000 for inter-container FastCGI

### Chores

- Remove confirmed-dead events, channel, and duplicate dispatch
- Code-simplifier pass on admin UI redesign
- Run CI on dev and soften testing policy
- Watch dev branch and gate PRs with all workflows
- Promote staging -> main (nex-011 fix + UI handoff + Laravel Boost)
- Merge UI visual handoff — admin pages design tokens + software-dev docs
- Gitignore generated .docx artifacts
- Merge nex-014 intake case — session domain host-binding 419
- Exclude handoff/ reference snippets from eslint
- Exclude handoff/ reference snippets from eslint
- Remove duplicate, dead, and bloat files (cleanup pass 1)
- Remove unreachable processLegacyRefill + stale references
- Hygiene cleanup — strip stale audit tags/Log::debug, remove orphan
- Update composer.lock dependencies
- Pending changes
- Bump vue-router from 4.6.4 to 5.0.4
- Bump @types/node from 22.19.17 to 25.6.0
- Bump laravel/pulse from 1.4.2 to 1.7.3
- Bump prettier-plugin-tailwindcss from 0.6.14 to 0.7.2
- Bump prettier from 3.8.2 to 3.8.3
- Bump @internationalized/date from 3.12.0 to 3.12.1
- Consolidate dependabot composer bumps and resolve lock drift
- Apply pending lint and UI cleanup updates
- Add engines.node constraint for vite 8 compatibility (^20.19.0 || >=22.12.0)
- Bump all open dependabot PRs (npm + composer)
- Resolver-first device branch, strict settings validation, transactional package sync
- Bump lorisleiva/laravel-actions from 2.9.1 to 2.10.1
- Add POS payment status sync console commands
- Update monitoring and compose/bootstrap wiring

### Documentation

- Scope repo-local docs to woosoo-nexus; defer to platform Authority Map
- Add software update and rollback workflows
- Fix AI_ONBOARDING print-bridge paths and legacy print-service note (#156)
- Clarify planned routes/endpoint; fix code block language tags
- Add designer specification for KDS v1.0
- Add PR #173 cross-reference to Files Changed section
- Polish Laravel Boost guide and cross-link onboarding
- Merge COMPLETE case doc — APPROVED 2026-06-05
- Mark COMPLETE — APPROVED 2026-06-05
- Add intake case — session domain host-binding 419
- Fix three stale references after authority redirect
- Add intake case — duplicate order printing
- Repoint dead printer_manual.md refs to printer_readme.md
- Delete orphaned documentation-inventory + fix INDEX dead links
- Relocate Copilot onboarding to docs/AI_ONBOARDING.md (Claude-only)
- Claude-only entrypoint + correct order-state to OrderStatus enum
- Add professional Business Requirements Document for Woosoo Nexus
- Improve user manual clarity, fix broken INDEX refs, and clean up deprecated printer manual
- Add public manual screenshots
- Restaurant operations handover manual + assets
- Docs, cases updates
- Record deferred /api/health contract change + POS-first decision
- Document woosoo:sync-package-modifiers as sole package modifier update path
- Record deferred polish follow-ups
- Audit Krypton SP usage and corrective actions
- Align ordering transaction specs and review findings
- Add agent review prompt for requirements validation
- Add long-term requirements specification
- Add server-authoritative order transaction implementation plan
- Record fixed transaction issues and rollout learnings
- Fix deployment checklist gaps and certs README SAN list
- Add tablet-ordering-pwa clone step and zzz-app.conf to deployment docs
- Add single-file Pi Docker deployment checklist

### Features

- Remediate 2026-06-15 KDS review (Tier A + B)
- Add global runtime error handler to app.ts
- Meats-only schema — drop side/dessert/beverage limits, add is_most_popular
- Consolidate Package as single dining-tier concept
- KDS hardening — multichannel broadcast, MAX_RECALLS guard, threshold tuning, echo type safety
- Nex-case-031 functional gap-fill — orders, dashboard, reports, users, monitoring, settings
- Server-authoritative elapsed time via clock offset
- Action endpoints return full board payload for optimistic UI apply
- Complete handoff UI alignment — remaining 15 pages + 2 components (#196)
- Wire recall button to served tickets
- Redesign Devices — UI-only polish
- Redesign IndexTabletCategories — UI-only polish
- Redesign IndexPackageConfigs — UI-only polish
- Redesign Packages — UI-only polish
- Redesign POS — UI-only polish
- Redesign Orders — UI-only polish
- Visual polish for overdue pulse and item done states
- Nex-case-025 — operator page wave (Orders/Devices/Packages/Tablet Categories)
- Nex-case-025 — admin shell migration (224px sidebar, spec nav, live badges)
- P2 recall — served->in_progress edge, contract, broadcast fix, tests
- P2 recall — served→in_progress edge + contract + tests
- Wire board to advance/toggle endpoints (Wave 1)
- Fleet stats, featured categories, MCP config, cert cleanup [WIP]
- Phase 1 — advance/toggle transaction workflow
- P0 read-only board wired to real data
- Apply visual handoff across admin pages; add software-dev docs
- Canonical order_id channel fix + POS detail sync (NEX-CASE-013)
- Brand-alignment UI for admin dashboard, reports, menus, users
- Conditional Vite build in entrypoint
- Run npm run build in entrypoint on php-fpm startup
- Restore Course/Group/Image filters in admin Menus DataTable
- POS payment outbox consumer, fix SessionReset scope, device last-seen middleware
- Print-latency dashboard + session reset/force-end controls
- Add session:force-end command and admin API endpoint
- Add admin-editable descriptions for packages and modifiers
- Strict tablet contract - no legacy fallback, fixed POS category mapping
- Add NEXUS_PRINT_EVENTS_ENABLED feature flag to gate PrintEvent runtime
- Add woosoo:sync-package-modifiers Artisan command
- Update device auth and deployment config
- Tighten registration policy and add cert download coverage
- Fix security code column length and branch-user table migration, update DeviceForm and observer
- Add complete Docker deployment setup with TLS
- Admin panel UX — sidebar nav cleanup, tab width, dark theme, thin scrollbar
- Harden print event flow and tune runtime config
- Add public certificate landing page
- Harden device flows and deployment config

### Other

- Revert "fix(pr-205): address remaining CodeRabbit review comments"

This reverts commit a86c4008fb69c983f0a24eddde80c62657ecc5a4.
- Merge pull request #205 from tech-artificer/staging

Staging
- Merge pull request #220 from tech-artificer/dev

Dev
- Merge pull request #223 from tech-artificer/fix/tablet-category-distinct-validation

fix(tablet-categories): add distinct validation to menu_ids
- Merge pull request #219 from tech-artificer/feat/runtime-error-handler

feat(frontend): global runtime error handler in app.ts
- Merge pull request #218 from tech-artificer/agent/packages-meats-only-most-popular

feat(packages): meats-only packages + single most-popular badge
- @
fix(packages): harden menu sync + validation per CodeRabbit (#205)

- syncAllowedMenus: validate payload + wrap replace in a transaction; add JsonResponse return type.
- Store/UpdatePackageRequest: max_meat gte:min_meat; constrain allowed_menus.*.menu_type to in:meat.
- Packages/Index.vue: preserve extra_price/is_required/is_default/is_active through Manage Meats sync (no data loss).
- PackageController::index: preload Krypton menu names in one query (drop N+1).
- Tests: reject invalid sync payload + inverted meat range.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
@
- Merge pull request #217 from tech-artificer/dev

Dev
- Merge pull request #216 from tech-artificer/agent/packages-ui-consolidation

feat(packages): consolidate Package as single dining-tier concept
- Merge pull request #202 from tech-artificer/dev

Dev
- Merge pull request #201 from tech-artificer/agent/nex-case-030-kds-server-authoritative-time

feat(kds): server-authoritative elapsed time + KDS hardening
- Merge pull request #200 from tech-artificer/agent/nex-case-031-admin-functional-gap-fill

feat(admin): nex-case-031 functional gap-fill — orders, dashboard, re…
- Merge pull request #199 from tech-artificer/agent/nex-case-030-kds-server-authoritative-time

feat(kds): server-authoritative elapsed time via clock offset
- Merge pull request #198 from tech-artificer/dev

promote(staging): KDS optimistic UI apply + admin handoff completion
- Merge pull request #197 from tech-artificer/agent/kds-action-payload-optimistic

feat(kds): action endpoints return full board payload for optimistic UI apply
- Merge pull request #193 from tech-artificer/agent/kds-recall-button

feat(kds): wire recall button to served tickets
- Merge pull request #195 from tech-artificer/dev

promote(staging): admin UI redesign + KDS test coverage (nex-027)
- Merge pull request #194 from tech-artificer/agent/admin-pages-ui-redesign

feat(admin): admin pages UI redesign — UI-only polish
- Merge pull request #192 from tech-artificer/dev

Promote dev → staging: recall + admin shell + operator page wave (combined E2E)
- Merge pull request #191 from tech-artificer/agent/nex-case-026-kds-visual-polish

feat(kds): Case B visual polish (overdue pulse, done-item states, recall-aware)
- Merge nex-case-025 admin shell migration into dev

Brings the custom admin shell (AdminShell/AdminSidebar/AdminTopbar, 224px sidebar, live badges, theme persistence), the operator page wave (Orders hero + print-highlight, Devices/Packages/Tablet Categories token cleanup), handoff docs, and code-review follow-ups (non-admin nav guard, navBadges typing, theme cookie).

Verified on merged tree: vue-tsc clean, vite build clean, eslint clean, php artisan test 511 passed (1834 assertions).

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
- Merge pull request #170 from tech-artificer/staging

Staging
- Merge pull request #183 from tech-artificer/dev

Dev
- Merge pull request #190 from tech-artificer/fix/kds-pos-menu-relation-guard

fix(kds): also exclude items.menu from eager-load when POS unreachable
- Merge pull request #189 from tech-artificer/agent/nex-case-022-batch-b

refactor(pos): extract pos.fill-order closure into PosController::fil…
- Merge pull request #188 from tech-artificer/fix/kds-pos-connection-guard

fix(kds): guard KdsController::index() against POS connection failure
- Merge pull request #187 from tech-artificer/agent/nex-case-020-admin-ui-audit-fixes

fix(nexus): admin UI audit fixes (nex-case-020)
- Merge pull request #184 from tech-artificer/agent/nex-case-021-pos-connection-hardening

fix(nexus): POS connection pre-flight guard + disconnected UI (nex-021)
- Merge pull request #186 from tech-artificer/agent/nex-case-022-track-2

fix(nexus): null print-dispatch guard + pos.fill-order error sanitization (nex-022 Track 2)
- Merge remote-tracking branch 'origin/dev' into HEAD
- Merge pull request #185 from tech-artificer/agent/nex-case-022-track-4

fix(nexus): Track 4 route fixes + KDS POS-resilient broadcast + CI on dev
- Merge origin/dev into agent/nex-case-022-track-4

Resolve conflicts:
- Display.vue: keep Case A server-driven board (kdsApi + useKdsEcho, recall
  removed), superseding the #181 kds-live-board optimistic prototype.
- ci.yml/tests.yml: take dev's CI-on-dev triggers (functionally identical to
  this branch's); CLAUDE.md testing policy auto-merged and preserved.
- Merge pull request #181 from tech-artificer/agent/kds-live-board

feat(kds): wire board to advance/toggle endpoints (Wave 1)
- Merge pull request #182 from tech-artificer/chore/nexus/ci-dev-branch-triggers

chore(ci): watch dev branch and gate PRs with all workflows
- Merge staging into dev — back-merge apply-woosoo-config.sh hardening (378a05c) + KDS docs
- Merge pull request #179 from tech-artificer/dev

Dev
- Merge pull request #180 from tech-artificer/test/nexus/debug-pos-error-handling

test(nexus): pin debug/pos error-leak hardening (nex-case-019)
- Merge branch 'agent/nex-case-019-debug-endpoint-hardening' into dev
- Merge branch 'agent/nex-case-018-kds-fullscreen-hardening' into dev
- Merge branch 'agent/nex-case-017-refill-intent-payload-hardening' into dev
- Merge branch 'fix/nexus/sidebar-tables-route' into dev
- Merge pull request #178 from tech-artificer/agent/nex-case-015-tablet-intent-payload-hardening

fix(nexus): enforce intent-only payload on device order create (NEX-CASE-015)
- Kitchen Display ui mockup
- Merge pull request #174 from tech-artificer/dev

Dev
- Merge pull request #173 from tech-artificer/chore/nexus/nex-014-deploy-script-sync-and-nex-011-case-update

fix(deploy): sync apply-woosoo-config.sh with WOOSOO_ENV implementation; fix nex-011 case run state
- Merge pull request #172 from tech-artificer/dev

Dev
- Merge pull request #171 from tech-artificer/laravel-boost-polish

docs(nexus): polish Laravel Boost guide and cross-link onboarding
- Merge pull request #169 from tech-artificer/claude/beautiful-brown-QpObV

Enable Boost Tinker tool (local) and add team usage guide
- Clarify Tinker env toggle comments and guide

Address review nitpicks on PR #169:
- .env.example: explain that config/boost.php defaults to
  env('BOOST_TINKER_TOOL_ENABLED', false) (off as a safe fallback) and that the
  local template intentionally opts in, so the true value is deliberate, not a
  contradiction.
- .env.docker.example: clarify that with APP_ENV=production Boost is inert and
  the toggle has no effect.
- docs/LARAVEL_BOOST_GUIDE.md: state the template-enables vs config-defaults-off
  relationship explicitly.

Comment/doc-only; no behavior change.

https://claude.ai/code/session_01ExDBcyP7oakMGqokZeCdan
- Enable Boost Tinker tool (local) and add team usage guide

- Publish config/boost.php and add tinker_tool_enabled, driven by
  BOOST_TINKER_TOOL_ENABLED (defaults off). Enables the Tinker MCP tool so
  agents can execute PHP in the app context to verify behavior. Runs in local
  environments only, per Boost's environment gate.
- Document the toggle in env examples: enabled in local profiles
  (.env.example, .env.local.example), disabled in the Docker/production profile
  (.env.docker.example).
- Add docs/LARAVEL_BOOST_GUIDE.md covering day-to-day Boost usage, the MCP
  tools, the search-docs allowlist note, and Tinker safety. Registered in
  docs/INDEX.md under AI Agent Onboarding.

https://claude.ai/code/session_01ExDBcyP7oakMGqokZeCdan
- Merge pull request #168 from tech-artificer/dev

Dev
- Merge pull request #166 from tech-artificer/dev

Dev
- Merge branch 'dev' of https://github.com/tech-artificer/woosoo-nexus into dev
- Merge pull request #165 from tech-artificer/claude/beautiful-brown-QpObV

Install Laravel Boost for AI-assisted development
- Fix CLAUDE.md guideline accuracy: PHP version and Livewire

- Pin php to 8.2 (matches composer.json ^8.2 and Dockerfile php:8.2-fpm-alpine).
  The installer detected the 8.4 runtime, but production runs 8.2; using 8.4
  here risks AI-generated code using 8.3/8.4-only syntax that passes CI but
  fails in the deployed container.
- Remove livewire/livewire (LIVEWIRE) from Foundational Context. Livewire is
  not a direct dependency (absent from composer.json require/require-dev); it
  is a transitive install. Listing it as a guideline package would misdirect
  AI-assisted decisions about framework choices.

Addresses review comments on PR #165.

https://claude.ai/code/session_01ExDBcyP7oakMGqokZeCdan
- Install Laravel Boost for AI-assisted development

Adds laravel/boost (dev) to provide AI agents with Laravel-specific,
version-pinned context and tooling (database introspection, Tinker,
docs search) for this Laravel 12 / Inertia + Vue stack.

- Configure Boost MCP server for Claude Code (.mcp.json)
- Add version-pinned AI guidelines in CLAUDE.md (separate file;
  canonical AGENTS.md left untouched)
- Store Boost config in boost.json (guidelines + mcp, Claude Code)

https://claude.ai/code/session_01ExDBcyP7oakMGqokZeCdan
- Merge pull request #164 from tech-artificer/dev

Dev
- Merge pull request #163 from tech-artificer/fix/nex-011-duplicate-print

fix(nex-011): remove PrintOrder re-dispatch from markPrinted ack paths
- Merge pull request #142 from tech-artificer/staging

Staging
- Merge pull request #159 from tech-artificer/dev

Dev
- Merge pull request #160 from tech-artificer/fix/nex-013-detail-refresh

fix(nex-013): refresh POS detail values before broadcasting (PR #158 P1)
- Merge pull request #158 from tech-artificer/agent/nex-case-013-pos-order-detail-sync

feat(nexus): canonical order_id channel fix + POS detail sync (NEX-CASE-013)
- Merge pull request #157 from tech-artificer/dev

Dev
- Merge pull request #154 from tech-artificer/dev

Dev
- Merge pull request #150 from tech-artificer/dev

style(nexus): add design-alignment handoff (steps 1–4); fix status do…
- Merge pull request #149 from tech-artificer/dev

feat(infra): run npm run build in entrypoint on php-fpm startup
- Dev → staging (Step 1 brand alignment)
- Merge pull request #141 from tech-artificer/dev

Dev
- Merge agent/infra-case-003-pi-docker-build-npm-ci-wifi: harden npm ci for Pi WiFi (APPROVED)
- Merge pull request #155 from tech-artificer/chore/nexus/claude-only-agents-consistency

docs(agents): Claude-only entrypoint + correct order-state to OrderStatus enum
- Force Vite rebuilds during normal deploys
- Merge branch 'dev' of https://github.com/tech-artificer/woosoo-nexus into dev
- Merge pull request #153 from tech-artificer/agent/vite-build-conditional

feat(infra): conditional Vite build in entrypoint
- Merge pull request #151 from tech-artificer/claude/affectionate-sagan-2KUpD

Exclude handoff directory from ESLint checks
- Add design-alignment handoff (steps 1–4); fix status dot token
- Step 1 brand alignment — amber active states, flat sidebar and topbar
- Merge pull request #138 from tech-artificer/claude/eager-dirac-d002U

docs: Woosoo Nexus Business Requirements Document
- Merge pull request #139 from tech-artificer/claude/trusting-hawking-2f2q5

chore: remove duplicate, dead, and bloat files (cleanup pass 1)
- Merge pull request #135 from tech-artificer/staging

Staging
- Dev → staging for Pi deployment
- Merge pull request #134 from tech-artificer/claude/magical-wozniak-hC89p

docs: improve operations manual, fix INDEX refs, clean up deprecated printer manual
- Merge pull request #133 from tech-artificer/fix/device-order-items-mass-assignment

fix: restrict DeviceOrderItems mass-assignment with explicit fillable
- Merge pull request #131 from tech-artificer/claude/pos-table-timer-issue-5qtRY

fix(pos): use Krypton MySQL NOW() for date_time_opened to fix table timer drift
- Merge pull request #119 from tech-artificer/staging

fix(nex-case-003): replace missing stored procedure with Eloquent inline
- Apply the first Woosoo Nexus UI foundation pass for colors, backgrounds, and fonts
- Deleted menus toolbar
- Merge pull request #123 from tech-artificer/agent/nex-case-009-admin-menus-filters

feat(menus): restore Course/Group/Image filters in admin Menus DataTable
- Revert "fix(nexus): SessionReset broadcasts synchronously like other order events"

This reverts commit 655bda605266bb95684eb5c8bc41f96fbf130633.
- Merge pull request #122 from tech-artificer/agent/nex-case-007-pos-payment-outbox-session-reset

feat(nexus): POS payment outbox consumer, fix SessionReset scope, dev…
- Merge pull request #104 from tech-artificer/staging

docs(api): add comprehensive API contract sync reference for all thre…
- Merge pull request #118 from tech-artificer/agent/nex-case-001-security-auth-hardening

Agent/nex case 001 security auth hardening
- Dockerfile update
- Merge branch 'staging' of https://github.com/tech-artificer/woosoo-nexus into staging
- Merge durable refill idempotency with remote staging
- Add Pi 5 deployment automation scripts

- update-client.sh: Pull latest staging, rebuild, migrate, cache, verify
- rollback-client.sh: Rollback to previous saved commits and .env
- verify-client.sh: Quick health, route, container, and URL checks
- Update
- Merge branch 'staging' of https://github.com/tech-artificer/woosoo-nexus into staging
- Remove redundant RefillSubmissionService - using remote DurableRefillGuard
- Replace broad CSRF exemptions with explicit endpoint patterns
- Merge durable refill idempotency with remote staging
- Add durable refill idempotency with state machine
- Replace broad CSRF exemptions with explicit stateless route exemptions
- Replace broad CSRF exemptions with explicit endpoint patterns
- Add durable refill submission guard for idempotent refills
- Complete WS2 print idempotency and WS5 performance improvements merge to staging
- Merge pull request #92 from tech-artificer/staging

Staging
- Prevent error message exposure in OrderController status transition
- Merge pull request #103 from tech-artificer/docs/governance-normalization-20260509

docs: normalize governance and archive legacy docs
- Merge pull request #91 from tech-artificer/staging

Staging
- Potential fix for pull request finding

Co-authored-by: Copilot Autofix powered by AI <175728472+Copilot@users.noreply.github.com>
- Potential fix for pull request finding

Co-authored-by: Copilot Autofix powered by AI <175728472+Copilot@users.noreply.github.com>
- Merge branch 'main' into staging
- Merge conflict - use DeviceOrderResource instead of raw toArray()
- Merge branch 'main' into staging
 refill fix
- Merge pull request #85 from tech-artificer/staging

Staging
- Resolve deployment guide filename collision
- Merge staging into main
- Align device registration with setup code identity
- Merge pull request #82 from tech-artificer/feat/device-registration-policy-and-cert-test-2026-04-25

feat(device-auth): tighten registration policy and add cert download …
- Merge pull request #81 from tech-artificer/staging-check

Staging check
- Document deployment update process for backend and frontend

Added detailed steps for updating backend and frontend deployments, including commands for pulling repositories, installing dependencies, and verifying deployment success.
- Add deployment configuration for Raspberry Pi 5

Added comprehensive deployment configuration for Raspberry Pi 5, including architecture overview, fixed vs dynamic values, directory structure, step-by-step deployment guide, and verification checklist.
- Add deployment guide for Pi operations

Added comprehensive deployment instructions for Pi operations, including SSH access, network changes, database credential updates, full redeploy steps, PWA redeploy, and verification checks.
- Merge pull request #70 from tech-artificer/staging

fix: transaction integrity hardening and security code column fix
- Merge pull request #71 from tech-artificer/claude/deployment-readiness-docs-3oFSa

feat: Docker deployment setup with TLS
- Merge branch 'staging' into claude/deployment-readiness-docs-3oFSa
- Merge pull request #69 from tech-artificer/staging

wip: device admin, service requests, session fixes, test coverage 202…
- Device admin, service requests, session fixes, test coverage 2026-04-24
- Merge pull request #68 from tech-artificer/staging

Expose public_port/public_scheme in broadcasting config for client pa…
- Expose public_port/public_scheme in broadcasting config for client payload
- Merge pull request #67 from tech-artificer/staging

Fix BroadcastConfig to return correct public host and client port
- Fix BroadcastConfig to read port/scheme from resolved config, not env()
- Fix BroadcastConfig to return correct public host and client port
- Merge pull request #53 from tech-artificer/staging

ci: stabilize staging checks and fix package checkbox typing
- Merge remote-tracking branch 'origin/dependabot/npm_and_yarn/prettier-plugin-tailwindcss-0.7.2' into HEAD
- Merge remote-tracking branch 'origin/dependabot/npm_and_yarn/prettier-3.8.3' into HEAD
- Merge remote-tracking branch 'origin/dependabot/npm_and_yarn/internationalized/date-3.12.1' into HEAD
- Merge pull request #52 from tech-artificer/copilot/fix-merge-chore-deps

chore(deps): consolidate all open dependabot bumps — npm + Composer
- Merge pull request #51 from tech-artificer/feature/frontend-quality-accessibility-orders-realtime

Feature/frontend quality accessibility orders realtime
- Merge origin/staging into feature branch (Mission-6+7 + Mission-8 integration)

CONFLICT RESOLUTION STRATEGY:
✓ Frontend (Mission-6 quality fixes): Kept OURS
  - resources/css/app.css
  - resources/js/components/*
  - resources/js/pages/* (Dashboard, Orders, Auth, etc.)
  - resources/views/app.blade.php

✓ Backend (Mission-8 print event system): Kept THEIRS (staging)
  - app/Events/Order/OrderPrinted.php (detailed broadcast payload)
  - app/Http/Controllers/Api/V1/PrinterApiController.php
  - app/Http/Controllers/Admin/OrderController.php (complete+print methods present in both, staging's more integrated)
  - app/Models/DeviceOrder.php
  - bootstrap/app.php
  - routes/api.php, routes/channels.php

✓ Build artifacts: Removed (proper .gitignore)
  - public/build/manifest.json (deleted, stays in gitignore)

✓ Syntax fixes applied:
  - Fixed invalid Vue template syntax in IndexMedia.vue, IndexPackageConfigs.vue, IndexTabletCategories.vue
    Changed: @update:open="if (!$event) ..." → @update:open="(val) => { if (!val) ... }"
- Merge origin/main into feature branch (resolved conflicts)

Resolved 3 merge conflicts with hybrid solutions:
1. Dashboard.vue: Accepted descriptive helpText from main ('Completed orders today', 'Guests served today')
2. OrderDetailSheet.vue: Combined AlertDialog confirmation (Mission-6) with isOrderCompleted disable logic (main)
3. manifest.json: Accepted ours (will rebuild to regenerate)

Security, UX audit, and permissions CRUD changes from main now integrated with Mission-6+7 work.
- Merge remote-tracking branch 'origin/dependabot/composer/lorisleiva/laravel-actions-2.10.1' into HEAD
- Merge remote-tracking branch 'origin/dependabot/composer/tightenco/ziggy-2.6.2' into HEAD
- Update Sync POS Payment Status
- Merge pull request #87 from tech-artificer/copilot/fix-failing-checks-staging

fix: TypeScript error in EditRole.vue — Role interface missing created_at/updated_at
- Merge branch 'staging' of https://github.com/tech-artificer/woosoo-nexus into staging
- Merge branch 'staging' of https://github.com/tech-artificer/woosoo-nexus into staging

### Performance

- Batch browse-menu modifier/relation loading (kill N+1)

### Refactor

- Consolidate print-dispatch route into OrderApiController
- Simplify recall() comments and remove redundant variable
- Extract pos.fill-order closure into PosController::fillOrder
- Simplify monitoring dashboard per code-review
- Extract PosController business logic into service layer
- Extract inline validation in PosController to FormRequest classes
- Wrap fresh order in DeviceOrderResource in refill response

### Reverts

- Restore save() for PENDING→CONFIRMED auto-advance

### Tests

- DeviceFactory sets device_uuid explicitly (works under Event::fake)
- DeviceOrderFactory device_id defaults to Device::factory()
- Add filterTickets and applyOrderUpdate unit tests
- Seed fill-order happy path as SERVED (valid SERVED->COMPLETED transition)
- Fake only fill-order events (Event::fake() all broke model UUID generation)
- Isolate pos.fill-order happy path from broadcast (Event::fake all)
- Pin debug/pos error-leak hardening (nex-case-019)
- Update PrinterApiTest — assert OrderPrinted not PrintOrder on ack paths
- Lock /pulse authorization contract
- Lock regression guards for order details, printer branch auth, and registration route behavior
- Harden cert/root assertions and modernize PHPUnit attributes

