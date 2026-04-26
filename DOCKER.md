# Docker Deployment Guide

This guide covers the full Docker Compose deployment for **Woosoo Nexus** (admin panel + API) and the **Tablet Ordering PWA**.

---

## Directory Layout

```
parent-folder/                    ← both repos must be siblings
├── woosoo-nexus/
│   ├── Dockerfile                # PHP-FPM image (Laravel)
│   ├── compose.yaml              # Full stack definition
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
│   └── DOCKER.md
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
# Run from woosoo-nexus/
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

## Tablet Setup

Each physical tablet needs:

1. Open `https://192.168.100.7:4443` in Chrome
2. Accept / trust the certificate (on first visit with self-signed certs)
3. Add to home screen (PWA install prompt) for fullscreen kiosk mode
4. Go to Settings on the tablet app and register a device token from the admin panel

For self-signed certs on Android tablets, install `docker/certs/fullchain.pem` as a trusted CA before navigating — see `docker/certs/README.md`.
