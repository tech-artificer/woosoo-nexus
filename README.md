# Woosoo Nexus

Integrated restaurant platform for:
- Admin panel + API (Laravel/Inertia/Vue)
- Tablet ordering PWA (Nuxt, built from sibling repo)
- Print relay integration

## Repository authority

- **Production deployment authority:** `compose.yaml` in this repository only.
- **Canonical documentation entrypoint:** `docs/INDEX.md`.
- **Frontend source authority:** `../tablet-ordering-pwa` (sibling repository).

## Production deployment rule

Run production Docker operations from:

`E:\Projects\woosoo-nexus`

Use:

`docker compose ...` (from this repo, using `compose.yaml`)

Do not use standalone production deployment flows from other repositories.

## Documentation governance

- Canonical docs live under `docs/`.
- Historical/transitional docs live under `docs/archive/`.
- Root-level markdown is intentionally minimal.
- PRs must pass the documentation checklist in `.github/pull_request_template.md`.
