# Woosoo Nexus

Integrated restaurant platform for:
- Admin panel + API (Laravel/Inertia/Vue)
- Tablet ordering PWA (Nuxt, built from sibling repo)
- Print relay integration

## Repository authority

- **Production deployment authority:** `compose.yaml` in the sibling `woosoo-platform/` repo (platform-root). This repo is an **application** repo; the platform repo orchestrates Docker for all three sibling apps.
- **Canonical documentation entrypoint:** `docs/INDEX.md`.
- **Frontend source authority:** `../tablet-ordering-pwa` (sibling repository).

## Production deployment rule

Run production Docker operations from the platform repo root:

`E:\Projects\woosoo-platform` (sibling of this repo)

Use:

`docker compose --env-file ./woosoo-nexus/.env -f ./compose.yaml ...`
<!-- Run from woosoo-platform/ root. The explicit -f ./compose.yaml prevents Docker from
     accidentally picking up woosoo-nexus/compose.yaml if invoked from the wrong directory. -->

Do not invoke `docker compose` from inside `woosoo-nexus/` — the platform-root
`compose.yaml` is the single authoritative stack definition. The legacy
`woosoo-nexus/compose.yaml` is retained only for reference and is not used by
the deploy scripts.

## Documentation governance

- Canonical docs live under `docs/`.
- Historical/transitional docs live under `docs/archive/`.
- Root-level markdown is intentionally minimal.
- PRs must pass the documentation checklist in `.github/pull_request_template.md`.
