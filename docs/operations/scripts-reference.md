# Operations Scripts Reference (Canonical)

This document lists active operational script entrypoints.

## Deployment Scripts

| Script | Purpose |
|---|---|
| `scripts/deployment/doctor.sh` | Preflight check — verifies environment, network, and config before deployment |
| `scripts/deployment/apply-woosoo-config.sh` | Applies config from `/etc/woosoo/woosoo.env` to the platform |
| `scripts/deployment/deploy.sh` | Full platform deploy (pulls latest, rebuilds, restarts services) |
| `scripts/deployment/verify-tablet-deploy-context.sh` | Preflight only — validates Nexus and Tablet git state before tablet deploy |
| `scripts/deployment/deploy-tablet.sh` | Deploy Tablet PWA from explicit Nexus and Tablet git refs |
| `scripts/deployment/woosoo-health.sh` | Post-deploy health check |
| `scripts/deployment/woosoo-backup.sh` | Database and volume backup |

## Canonical Execution Context

Run all production deployment scripts from the platform root on the server:

```bash
cd /opt/woosoo/woosoo-platform
```

Docker orchestration is via `compose.yaml` in this directory:

```bash
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml <command>
```

> **Note:** Do not run deployment scripts from a Windows path or from inside the `woosoo-nexus/`
> subdirectory — always run from `/opt/woosoo/woosoo-platform` on the Pi server.

## Non-Canonical Scripts

Historical or deprecated script workflows are archived under `docs/archive/` and are not
production authority. Do not use them for live deployments.
