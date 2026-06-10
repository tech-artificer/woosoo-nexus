---
status: canonical
last_reviewed: 2026-06-05
scope: app
---

# CASE: nex-case-014-session-domain-login-419

## Run State
- task_slug: nex-case-014-session-domain-login-419
- tier: 2
- branch: fix/nex-014-session-domain-host-binding
- status: COMPLETE
- last_completed_agent: executioner
- next_agent: done
- active_runner: claude
- interrupted: false
- interrupt_reason: none
- updated: 2026-06-05

## Handoff
- Phase in progress: none — COMPLETE.
- Done so far: SESSION_DOMAIN fix was already committed (commit `30ffeae`). This case added the WOOSOO_ENV profile switch (local|staging|production) driving APP_ENV/APP_DEBUG/LOG_LEVEL, and derived SESSION_SECURE_COOKIE from WOOSOO_SCHEME. Executioner APPROVED.
- Exact next action (operator, on Pi): clear any pinned SESSION_DOMAIN from live .env; set WOOSOO_ENV="production"; re-run apply-woosoo-config.sh; restart app container; confirm GET /sanctum/csrf-cookie returns host-only cookie.
- Working-tree state: committed on fix/nex-014-session-domain-host-binding (platform repo); merged to dev.
- Risks / do-not-redo: The live `.env` is operator/secrets-owned — do not commit it. SESSION_DOMAIN is auth-adjacent: changing it invalidates currently-issued session cookies (one re-login). Do not touch `SANCTUM_STATEFUL_DOMAINS`/`CORS_ALLOWED_ORIGINS`/`APP_URL` host-pinning — those are correctly host-scoped for CORS and the tablet origin.

## Tier
2 — authentication/session configuration affecting every admin login. No order/payment/print logic, no API contract change, but it gates admin access and is re-applied by the deployment pipeline, so it is operationally high impact.

## Branch
fix/nex-014-session-domain-host-binding (off `dev`)

## Problem

Admin login intermittently fails with **HTTP 419 "Page Expired"** whenever the
Woosoo Nexus admin panel is reached on any host other than the one currently
pinned in `.env` as `SESSION_DOMAIN` (today `192.168.100.7`). Because the
deployment box changes IP across networks/sites (DHCP, restaurant vs. dev LAN),
this recurs every time the active host no longer matches the pinned value —
breaking all logins until someone hand-edits `.env`.

Symptom observed this session: loading `https://localhost/login` and submitting
returned `419`; the page renders fine, but the CSRF/session cookie is never
stored by the browser.

Secondary scope (added at request): the deployment config script currently
emits **production-only** values (`APP_ENV=production`, `APP_DEBUG=false`,
`LOG_LEVEL=error`, `SESSION_SECURE_COOKIE=true`) with no way to provision a dev
or staging box. We want a single switch so the same tooling can produce correct
`.env` values for **dev / staging / production** — with the `SESSION_DOMAIN` fix
applied uniformly across all three.

## Contrarian Review

1. Correct app/platform scope? Yes — woosoo-nexus app + its deployment script. Single repo.
2. Does this already exist / already handled? Partially — `config/session.php:159` already implements a request-host fallback when `SESSION_DOMAIN` is empty, and `.env.example:100` ships it empty. The bug is that the deploy script overrides that with a hardcoded host.
3. Scope exactly as described? Yes — a configuration/deploy fix, not a code redesign.
4. What breaks if wrong? Every admin login on a non-matching host → 419 lockout; operators cannot reach the back office after any IP change.
5. Simpler path? Yes, and it is the chosen one: stop pinning `SESSION_DOMAIN` and rely on the existing request-host fallback. No new code paths.
6. Touches contract/auth/state/payment/print? Auth/session yes (web guard CSRF + session cookie). Not payment/print/order/state. Tablet/device API is unaffected (token auth — see Investigation). Tier 2.
7. Split required? No. One focused change in the deploy script + env template + docs, plus an operator `.env` correction.

## Investigation

Authoritative sources (verified this session):

- `scripts/deployment/apply-woosoo-config.sh:347` — `set_env "SESSION_DOMAIN" "$WOOSOO_HOST"`. **This is the durable root cause**: every config apply re-pins the session cookie domain to the box's host/IP. (Lines 350–351 also host-pin `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` — those are correct and must stay.)
- `config/session.php:159-171` — custom `domain` resolver that returns `null` (→ use request host) when `SESSION_DOMAIN` is empty / `'null'` / `'localhost'`. Comment: "Allow null/empty to use request host (needed for IP-based local network access)." The intended-robust behavior already exists; the pinned env defeats it.
- `.env.example:100` — `SESSION_DOMAIN=` (empty). The template is already correct; the live `.env` and the deploy script disagree with it.
- Live `.env` (operator-owned): `SESSION_DOMAIN=192.168.100.7`, `SESSION_SECURE_COOKIE=true`, `APP_URL=https://192.168.100.7`, `SANCTUM_STATEFUL_DOMAINS=192.168.100.7,192.168.100.42,192.168.1.31,...` — three distinct IPs, confirming the host genuinely changes across networks (corroborated by `docs/cases/deployment-docs-krypton-pc-ip-change.md` and `PUBLIC_HOST=192.168.1.31` in the restaurant config).
- `routes/api.php:225,240` and `app/Http/Controllers/Api/V1/Auth/DeviceAuthApiController.php` — tablet/device API uses `auth:sanctum` with `PersonalAccessToken` (Bearer tokens), **not** the web session cookie. So changing `SESSION_DOMAIN` does not affect the tablet/device flows.
- `resources/js/pages/auth/Login.vue:29-35` — submit does `axios.get('/sanctum/csrf-cookie')` then `form.post`. Logic is correct and unchanged by the recent redesign; it cannot succeed if the cookie is dropped due to a domain mismatch. **Not the cause.**

Live evidence captured:

```
# Set-Cookie domain is pinned regardless of the host used to request it:
GET https://localhost/sanctum/csrf-cookie         -> Set-Cookie ... domain=192.168.100.7
GET https://192.168.100.7/sanctum/csrf-cookie     -> Set-Cookie ... domain=192.168.100.7

# Login POST result by host (full SPA flow with X-XSRF-TOKEN):
POST https://localhost/login        -> 419  (cookie dropped by browser; no CSRF token)
POST https://192.168.100.7/login    -> 422  (validation only — CSRF/session OK)
```

The `419 → 422` swing on the configured host proves the CSRF/session machinery is sound; the failure is purely the cookie-domain/host mismatch.

## Root Cause

`SESSION_DOMAIN` is pinned to a single host (the box's current IP) by
`scripts/deployment/apply-woosoo-config.sh:347`. With `SESSION_SECURE_COOKIE=true`,
the browser only stores/sends the session + `XSRF-TOKEN` cookies for that exact
domain. Any access via a different host (a new DHCP IP, `localhost`,
`woosoo.local`) means the cookie is silently dropped, so the login POST arrives
with no valid CSRF token → `VerifyCsrfToken` returns **419**. The application
already supports the correct behavior (request-host binding via empty
`SESSION_DOMAIN`, `config/session.php:159`); the deploy script overrides it.

## Environment Profiles

A single `WOOSOO_ENV` config var (allowed: `local` | `staging` | `production`;
default `production` to preserve current behavior) drives the environment-varying
`.env` values. `SESSION_DOMAIN` is **empty in every profile** — that is the
core fix, not an environment toggle. `SESSION_SECURE_COOKIE` is derived from
`WOOSOO_SCHEME` (https → true) rather than from the env name, so a dev box on
plain http still works.

| `.env` key             | local (dev)                  | staging                       | production                    |
|------------------------|------------------------------|-------------------------------|-------------------------------|
| `APP_ENV`              | `local`                      | `staging`                     | `production`                  |
| `APP_DEBUG`            | `true`                       | `false`                       | `false`                       |
| `LOG_LEVEL`            | `debug`                      | `info`                        | `error`                       |
| `SESSION_DOMAIN`       | *(empty)*                    | *(empty)*                     | *(empty)*                     |
| `SESSION_SECURE_COOKIE`| from scheme (`http`→`false`) | from scheme (`https`→`true`)  | from scheme (`https`→`true`)  |
| `SESSION_DRIVER`       | `redis`                      | `redis`                       | `redis`                       |
| `SESSION_SAME_SITE`    | `lax`                        | `lax`                         | `lax`                         |
| `APP_URL` / `ASSET_URL`| `${SCHEME}://${HOST}`        | `${SCHEME}://${HOST}`         | `${SCHEME}://${HOST}`         |
| `SANCTUM_STATEFUL_DOMAINS` / `CORS_ALLOWED_ORIGINS` | host-derived | host-derived | host-derived |

(`SANCTUM_STATEFUL_DOMAINS`/`CORS_ALLOWED_ORIGINS`/`APP_URL` stay host-derived in
all profiles — they are correctly scoped and unchanged by this case.)

## Proposed Fix

Primary (durable): stop pinning the session cookie domain; rely on the existing
request-host fallback. Plus: make the deploy tooling environment-aware.

1. **`scripts/deployment/apply-woosoo-config.sh`**
   - Line 347: emit `set_env "SESSION_DOMAIN" ""` (with a comment: empty →
     `config/session.php` binds the cookie to the request host; required for
     IP/host-portable deployments). **Apply in every profile.**
   - Add a `WOOSOO_ENV` input (validate against `local|staging|production`,
     default `production`). Replace the hardcoded lines 305 (`APP_ENV`), 306
     (`APP_DEBUG`), 353 (`LOG_LEVEL`) and 348 (`SESSION_SECURE_COOKIE`) with
     values derived from `WOOSOO_ENV` and `WOOSOO_SCHEME` per the matrix above.
     A small `case "$WOOSOO_ENV"` block keeps it readable.
2. **`docs/deployment/examples/woosoo.env.example`** — add `WOOSOO_ENV="production"`
   to the REQUIRED/OPTIONAL block with a comment listing the three allowed values.
3. **`.env.example:100`** — already empty; add an inline comment warning never to
   pin `SESSION_DOMAIN` to an IP/host, to prevent regressions.
4. **Per-environment env templates** — add `docs/deployment/examples/.env.local.example`,
   `.env.staging.example`, `.env.production.example` (or one annotated matrix doc)
   capturing the table above so an operator can provision any tier directly.
5. **Docs** — record the rule in `docs/deployment/*` and cross-reference
   `docs/cases/deployment-docs-krypton-pc-ip-change.md`: "Never pin
   `SESSION_DOMAIN`; leave empty so the cookie follows the request host."
6. **Live `.env`** (operator action, not committed): set `SESSION_DOMAIN=` (empty),
   set the desired `WOOSOO_ENV`, re-run the apply script (or hand-edit), and
   restart the `app` container so config re-reads.

Optional follow-up (separate case): standardize on the stable hostname
`woosoo.local` (already the `woosoo.env.example` default) for `APP_URL` +
stateful domains so IP changes stop rippling through other host-pinned values.

## Files Changed

<!-- Script changes applied in PR #173 (chore/nexus/nex-014-deploy-script-sync-and-nex-011-case-update → dev).
     The nexus repo had a stale copy of the deploy script; PR #173 syncs it with the platform version. -->
- `scripts/deployment/apply-woosoo-config.sh` — added `WOOSOO_ENV` input variable (default `production`) with `case` block driving `_APP_ENV`/`_APP_DEBUG`/`_LOG_LEVEL`; added `_SESSION_SECURE_COOKIE` derived from `WOOSOO_SCHEME` (https→true, anything else→false); replaced hardcoded `APP_ENV="production"`, `APP_DEBUG="false"`, `LOG_LEVEL="error"`, `SESSION_SECURE_COOKIE="true"` with the profile-driven variables; invalid `WOOSOO_ENV` values print a clear error and exit 1; updated startup echo to show environment profile.
- `docs/deployment/examples/woosoo.env.example` — added `WOOSOO_ENV="production"` with comment listing all three allowed values and explaining that `SESSION_SECURE_COOKIE` is derived from `WOOSOO_SCHEME` independently.
- `docs/cases/nex-case-014-session-domain-login-419.md` — this case (run state updated to IMPLEMENTED, next_agent=verifier).
- Operator-only (NOT committed): live `.env` `SESSION_DOMAIN=` correction + `WOOSOO_ENV` selection.

Notes on what was NOT done (still planned):
- `.env.example` anti-pinning comment — deferred; existing comment at line 408-410 of the script is sufficient; `.env.example:100` ships `SESSION_DOMAIN=` empty already.
- Per-env `.env.{local,staging,production}.example` files — deferred; the matrix is fully documented in the case doc Investigation/Environment Profiles section and in `woosoo.env.example` comment.

## Verification

(planned checklist for executioner)

- After setting `SESSION_DOMAIN=` empty and restarting `app`, confirm the
  cookie domain follows the host:
  - `GET https://localhost/sanctum/csrf-cookie` → `Set-Cookie` has **no** `domain=` (host-only).
  - `GET https://192.168.100.7/sanctum/csrf-cookie` → host-only cookie.
- Full SPA login flow (cookie jar + `X-XSRF-TOKEN`) returns **422 on bad creds**
  (not 419) on BOTH `https://localhost` and `https://192.168.100.7`.
- Real login with valid staff credentials succeeds and redirects to dashboard on
  both hosts.
- Re-run `apply-woosoo-config.sh` and confirm `.env` no longer ends up with a
  pinned `SESSION_DOMAIN`.
- Per-environment checks: run the apply script with `WOOSOO_ENV=local`,
  `staging`, and `production` and confirm `.env` lands the matrix values
  (`APP_ENV`/`APP_DEBUG`/`LOG_LEVEL`), `SESSION_SECURE_COOKIE` tracks
  `WOOSOO_SCHEME`, and `SESSION_DOMAIN` is empty in all three. An invalid
  `WOOSOO_ENV` is rejected with a clear error.
- `php artisan test --filter=Auth` stays green.

## Executioner Verdict

APPROVED 2026-06-05. SESSION_DOMAIN unconditionally empty; WOOSOO_ENV profile switch (local|staging|production, default=production) drives APP_ENV/APP_DEBUG/LOG_LEVEL; SESSION_SECURE_COOKIE derives from WOOSOO_SCHEME; invalid WOOSOO_ENV exits 1 with clear error; SANCTUM/CORS host-derivation untouched; 441 tests / 1556 assertions green; pre-merge-check exit 0.

## Remaining Risks

- Live `.env` is operator/secrets-owned and must not be committed; the env
  correction is a manual deploy step.
- Switching `SESSION_DOMAIN` invalidates currently-issued session cookies — all
  signed-in admins will need to log in once after the change (or clear cookies).
- This case fixes the session cookie only. `APP_URL` and the stateful/CORS host
  lists remain IP-pinned by design; if those drift on a network change they are a
  separate concern (see optional follow-up).
