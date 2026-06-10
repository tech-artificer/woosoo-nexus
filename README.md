# Woosoo Nexus

Laravel backend and **manager admin UI** for the **Woosoo** restaurant operations ecosystem.

Nexus is the system backbone on the restaurant LAN: device auth, order and session APIs, POS/Krypton
integration, Laravel Reverb broadcasting, and print-event orchestration. The manager admin (Inertia +
Vue 3) runs in the **same deploy** as the API — it is not a separate app.

## What Nexus is (and is not)

| Nexus is | Nexus is not |
| --- | --- |
| API + manager admin + Reverb hub | The name for the whole Woosoo ecosystem |
| Source of truth for orders, pricing, POS mapping | The customer tablet app (see `tablet-ordering-pwa`) |
| Print-event publisher for the Print Bridge | The production print executor (see `woosoo-print-bridge`) |
| LAN admin for branch operations | The owner cloud portal (see `woosoo-portal`) |

Ecosystem map: [`../docs/WOOSOO_ECOSYSTEM_OVERVIEW.md`](../docs/WOOSOO_ECOSYSTEM_OVERVIEW.md)
(platform repo).

## Stack

- Laravel 12, MySQL, Redis, Sanctum, Reverb
- Manager admin: Inertia.js + Vue 3
- POS: Krypton driver (LAN)

## Repository authority

- **Production deployment authority:** `compose.yaml` in the sibling **`woosoo-platform/`** repo
  (platform-root). This repo is an application repo; the platform repo orchestrates Docker for all
  sibling apps.
- **Canonical documentation entrypoint:** `docs/INDEX.md` (Nexus) and
  `woosoo-platform/docs/README.md` (ecosystem).
- **Frontend source authority:** `../tablet-ordering-pwa` (customer PWA sibling repo).

## Production deployment rule

Run production Docker operations from the platform repo root:

`E:\Projects\woosoo-platform` (sibling of this repo)

Use:

`docker compose --env-file ./woosoo-nexus/.env -f ./compose.yaml ...`

Run from `woosoo-platform/` root. The explicit `-f ./compose.yaml` prevents Docker from
accidentally picking up `woosoo-nexus/compose.yaml` if invoked from the wrong directory.

Do not invoke `docker compose` from inside `woosoo-nexus/` — the platform-root `compose.yaml`
is the single authoritative stack definition. The legacy `woosoo-nexus/compose.yaml` is retained
only for reference and is not used by the deploy scripts.

## Integration surfaces

| Consumer | Integration |
| --- | --- |
| Tablet PWA | Device auth, order intent API, Reverb (`orders.{id}`, `device.{id}`, `session.{id}`) |
| Print Bridge | Print events (`admin.orders`), polling, reserve/ack/failed, heartbeat |
| woosoo-portal *(planned)* | EOD sync batches — not implemented; see platform `docs/cases/woosoo-cloud-portal-sync-plan-review.md` |

Contracts live in `woosoo-platform/contracts/`.

## Documentation governance

- Canonical docs live under `docs/`.
- Historical/transitional docs live under `docs/archive/`.
- Root-level markdown is intentionally minimal.
- PRs must pass the documentation checklist in `.github/pull_request_template.md`.
