# Woosoo Architecture (Canonical)

This is the active architecture reference for Woosoo Nexus.

## System authority

- Orchestration authority: `woosoo-nexus/compose.yaml`
- Deployment entrypoint: `docs/deployment/production-docker.md`
- Docs navigation root: `docs/INDEX.md`

## Runtime topology

- **Admin/API:** Laravel app behind `nginx` on `https://<PUBLIC_HOST>`
- **Tablet PWA:** proxied on `https://<PUBLIC_HOST>:4443`
- **Realtime (Reverb):** `wss://<PUBLIC_HOST>/app`
- **Main DB:** Docker `mysql` service
- **POS DB (Krypton):** external Windows host, accessed from app service
- **Queue/Cache:** Docker `redis` service with queue + scheduler workers

## Service boundaries

- `woosoo-nexus` owns deployment/orchestration/runtime networking.
- `tablet-ordering-pwa` owns tablet frontend source code.
- `woosoo-print-bridge` owns print bridge implementation, not platform orchestration authority.

## Operational rule

Use only the canonical deployment flow from `docs/deployment/production-docker.md`.
