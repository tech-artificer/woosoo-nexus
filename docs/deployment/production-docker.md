# Docker Deployment Guide

> **⚠ Authority has moved.** Production Docker authority is now the
> platform-root `compose.yaml` in the sibling `woosoo-platform/` repo, **not**
> `woosoo-nexus/compose.yaml`. Follow `woosoo-platform/docs/deployment/DEPLOYMENT_GUIDE.md`
> for the current Pi deploy flow (`scripts/deployment/deploy-all.sh`). This
> document is retained for service-topology reference; the commands below that
> say "run from `woosoo-nexus/`" are stale.

This guide covers the canonical Docker Compose deployment for **Woosoo Nexus** (admin panel + API) and the **Tablet Ordering PWA**.

---

## Directory Layout

```
parent-folder/                    ← both repos must be siblings
├── woosoo-nexus/
│   ├── Dockerfile                # PHP-FPM image (Laravel)
│   ├── compose.yaml              # Legacy — reference only; authoritative stack is woosoo-platform/compose.yaml
│   ├── docker/
│   │   ├── nginx/
│   │   │   └── default.conf      # Reverse proxy + TLS + WebSocket
│   │   ├── php/
│   │   │   └── local.ini         # PHP runtime overrides
│   │   ├── certs/
│   │   │   ├── fullchain.pem     # ← place your cert here
│   │   │   ├── privkey.pem       # ← place your private key here
│   │   │   ├── generate-dev-certs.sh
│   │   │   └── README.md
│   │   └── entrypoint.sh         # App container bootstrap
│   └── docs/deployment/production-docker.md
└── tablet-ordering-pwa/
    └── Dockerfile                # Nuxt 3 multi-stage build
```

---

## Prerequisites

- Docker 24+, Docker Compose v2 (`docker compose`)
- `openssl` (for dev cert generation)
- Both repos checked out **as sibling directories** under the same parent

---

## Setup

> Production deployment authority is the platform-root `compose.yaml` in the
> sibling `woosoo-platform/` repo. Run production Docker operations from
> `woosoo-platform/` only — see `woosoo-platform/docs/deployment/DEPLOYMENT_GUIDE.md`.

### 1. TLS Certificates

**Development (self-signed):**
```sh
cd docker/certs
chmod +x generate-dev-certs.sh
./generate-dev-certs.sh 192.168.100.7   # your server LAN IP
```

**Production:** copy or mount your real certs — see `docker/certs/README.md`.

### 2. Environment file

```sh
cp .env.example .env
```

Minimum values to configure:

| Variable | Example | Notes |
|----------|---------|-------|
| `PUBLIC_HOST` | `192.168.100.7` | Server IP or hostname |
| `APP_KEY` | *(leave blank)* | Auto-generated on first start |
| `DB_PASSWORD` | `change_this` | MySQL app user password |
| `DB_ROOT_PASSWORD` | `rootpassword` | MySQL root password |
| `REVERB_APP_KEY` | `any-random-string` | WebSocket app key |
| `REVERB_APP_SECRET` | `any-random-string` | WebSocket secret |
| `DB_POS_HOST` | `192.168.100.2` | Krypton POS host |
| `DB_POS_PASSWORD` | *(your value)* | Krypton POS password |

### 3. Build and start

```sh
# ⚠ Stale — run from woosoo-platform/ using the platform-root compose.yaml instead.
# See woosoo-platform/docs/deployment/DEPLOYMENT_GUIDE.md → scripts/deployment/deploy-all.sh
docker compose up -d --build
```

First boot takes 2–4 minutes (composer install, npm build, migrations).

### 4. Generate app key (first run only)

```sh
docker compose exec app php artisan key:generate
docker compose restart app queue scheduler reverb
```

---

## URLs

| Service | URL |
|---------|-----|
| **Woosoo Nexus — Admin Panel** | `https://192.168.100.7` |
| **Tablet Ordering PWA** | `https://192.168.100.7:4443` |
| **Reverb WebSocket** | `wss://192.168.100.7/app/{REVERB_APP_KEY}` *(proxied on 443)* |
| **Laravel Horizon** | `https://192.168.100.7/horizon` |
| **Laravel Pulse** | `https://192.168.100.7/pulse` |

Replace `192.168.100.7` with your actual `PUBLIC_HOST`.

---

## Services

| Container | Role | Exposed port |
|-----------|------|--------------|
| `nginx` | Reverse proxy, TLS termination | 80, 443, 4443 |
| `app` | PHP-FPM — Laravel backend | *(internal 9000)* |
| `queue` | Redis queue worker | — |
| `scheduler` | `artisan schedule:work` | — |
| `reverb` | Reverb WebSocket server | *(internal 8080)* |
| `tablet-pwa` | Nuxt 3 SSR server | *(internal 3000)* |
| `mysql` | MySQL 8.0 database | *(internal 3306)* |
| `redis` | Redis 7 — cache / queues / sessions | *(internal 6379)* |

Only `nginx` exposes ports to the host. All other services communicate on the internal `woosoo` Docker network.

---

## Certificate Location

```
docker/certs/
├── fullchain.pem   →  mounted at /etc/nginx/certs/fullchain.pem  (read-only)
└── privkey.pem     →  mounted at /etc/nginx/certs/privkey.pem    (read-only)
```

Only the `nginx` container mounts the certs directory. The private key is never accessible to `app`, `queue`, `reverb`, or any other service container.

---

## Common Commands

```sh
# Follow logs
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f reverb

# Artisan commands
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker

# Restart a single service
docker compose restart reverb

# Redeploy without data loss
docker compose down && docker compose up -d --build

# Full reset including database volumes
docker compose down -v && docker compose up -d --build
```

---

## Package Modifier Sync

Package modifiers (for packages 46, 47, 48) are seeded during database migrations and synced
via `PackageController::syncModifiers()` (triggered by admin package updates). The canonical
sources are `PackageSeeder` and `app/Http/Controllers/Admin/PackageController.php`.
Do not use ad-hoc PHP scripts — both former scripts (`update_package_modifiers.php` and
`scripts/update_package_modifiers.php`) have been deleted.

---

## Deterministic Tablet Deployment

Use the deployment scripts to guarantee the exact Nexus + Tablet context being deployed:

```sh
# Preflight only (fails on dirty git trees unless ALLOW_DIRTY=1)
scripts/deployment/verify-tablet-deploy-context.sh

# Deploy Tablet from explicit refs
NEXUS_DEPLOY_REF=<nexus-commit-or-tag> TABLET_DEPLOY_REF=<tablet-commit-or-tag> scripts/deployment/deploy-tablet.sh
```

The preflight prints:
- Nexus branch/commit/status
- Tablet branch/commit/status
- Resolved `tablet-pwa` Dockerfile
- Resolved tablet build args and runtime env
- Fully resolved compose `tablet-pwa` service

---

## Tablet Setup

Each physical tablet needs:

1. Open `https://192.168.100.7:4443` in Chrome
2. Accept / trust the certificate (on first visit with self-signed certs)
3. Add to home screen (PWA install prompt) for fullscreen kiosk mode
4. Go to Settings on the tablet app and register a device token from the admin panel

For self-signed certs on Android tablets, install `docker/certs/fullchain.pem` as a trusted CA before navigating — see `docker/certs/README.md`.
