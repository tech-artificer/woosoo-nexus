# Nexus ↔ Tablet Update Contract

This contract defines the deployment boundary for tablet updates when `woosoo-nexus` and `tablet-ordering-pwa` are deployed together.

Matching Tablet PWA contract:

- Repository/path: `tech-artificer/tablet-ordering-pwa` → `docs/deployment/tablet-update-contract.md`
- Use the **same branch selected for tablet deploy** when reviewing that contract.

Both repositories must keep these rules aligned.

---

## 1) Explicit branch selection is mandatory

For every tablet deployment/update run, operators must explicitly select both branches before build:

- **Nexus branch** (`woosoo-nexus`)
- **Tablet branch** (`tablet-ordering-pwa`)

Hard rule:

- Never assume defaults.
- Never use detached HEAD for production deploys.
- Never deploy when either branch is unknown or unrecorded in the release notes/change ticket.

---

## 2) Build boundary and Dockerfile contract

Current tablet image build boundary in Nexus:

- `compose.yaml` → `services.tablet-pwa.build.context: ../tablet-ordering-pwa`

Contract:

- The tablet image is built from the **sibling** `../tablet-ordering-pwa` repository.
- Build context must not be switched to Nexus local folders.
- Production Dockerfile target becomes **`Dockerfile.prod`** only after both conditions are met:
  - 14 consecutive days of successful tablet deployments with no rollback caused by asset/cache mismatch.
  - The matching Tablet PWA contract is updated in the same change window and reviewed with Nexus.

---

## 3) Runtime files and cache policy expectations

The deployed tablet origin (for example `https://woosoo.local:4443`) must expose and keep matching files from the same build fingerprint (atomically switched release) for:

- `runtime-config.js`
- `sw.js`
- `manifest.webmanifest`
- `/_nuxt/*` assets

Cache policy expectations:

- `runtime-config.js`, `sw.js`, and `manifest.webmanifest` must be fetched with no-cache/revalidate behavior during rollout so clients pick up the new release quickly.
- `/_nuxt/*` assets are fingerprinted build artifacts and may use long-lived immutable caching.
- Service worker updates must not reference stale `/_nuxt/*` bundles from a previous build fingerprint.

---

## 4) Visible debug/build values

Each deployment must make release identity visible for troubleshooting (UI, response headers, or runtime diagnostics endpoint/log output), including:

- Selected Nexus branch
- Selected Tablet branch
- Tablet build fingerprint (commit SHA, image digest, or equivalent immutable identifier)
- Build timestamp (UTC)
- Runtime environment label (`production`/`staging`)

These values must be visible to operators without requiring shell access to source repositories.

---

## 5) Deploy preflight requirements

Do not start tablet rollout unless all preflight checks pass:

1. Both repositories are present as sibling directories under the deployment root.
2. Selected Nexus and Tablet branches are explicitly checked out and clean.
3. `compose.yaml` tablet build context still points to `../tablet-ordering-pwa`.
4. Tablet artifact set contains `runtime-config.js`, `sw.js`, `manifest.webmanifest`, and `/_nuxt/*`.
5. Build fingerprint is captured in deployment notes/change ticket.
6. Rollback reference for the previous known-good tablet build is available.

---

## 6) Deployment hard-stop rules

Deployment must stop immediately (no partial proceed) if any condition below is true:

- Nexus branch is not explicitly selected.
- Tablet branch is not explicitly selected.
- Tablet build context is not `../tablet-ordering-pwa`.
- Required runtime files are missing after build.
- New release fingerprint cannot be confirmed.
- Cache invalidation/update behavior for `runtime-config.js`/`sw.js`/`manifest.webmanifest` cannot be verified via:
  - HTTP headers showing revalidation policy (for example: `Cache-Control: no-cache`, `max-age=0`, or `must-revalidate`), and
  - Browser DevTools Network inspection (or equivalent automated preflight checks) confirming fresh fetch on rollout.
- Matching Tablet PWA contract is missing or conflicts with this document.

Resume only after the blocking condition is fixed and preflight is re-run.
