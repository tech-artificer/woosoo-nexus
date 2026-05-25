---
status: canonical
last_reviewed: 2026-05-22
scope: ecosystem
---

# Woosoo Restaurant Operations And Handover Manual

This is the maintainable source for the DOCX handover manual. The final deliverable is `docs/deployment/woosoo-restaurant-operations-handover-manual.docx`.

## Restaurant Network Values

| Item | Value |
|---|---|
| Woosoo server/Pi public host | `192.168.1.31` |
| Krypton Woosoo PC / POS host | `192.168.1.32` |
| Krypton subnet mask | `255.255.255.0` |
| Krypton gateway | `192.168.1.1` |
| POS database | `krypton_woosoo` |
| POS database port | `2121` |

Only restaurant network values are included. Do not use older sample values from non-restaurant examples for production handover.

## Business Requirements

- The restaurant ordering system must run on the local LAN.
- Woosoo Nexus owns business truth: pricing, packages, POS/Krypton writes, sessions, orders, realtime events, and print events.
- The Tablet Ordering PWA sends customer intent only.
- The Print Bridge confirms the last-mile print result through heartbeat and acknowledgement flows.
- Operators must be able to deploy, redeploy, check logs, troubleshoot, roll back, and prove handover readiness without reading source code.

## App Responsibilities

| App | Role | Operator Usage |
|---|---|---|
| Woosoo Nexus | Laravel admin/API, POS integration, sessions, orders, Reverb, print events | Admin panel, device management, order monitoring, configuration checks |
| Tablet Ordering PWA | Customer-facing tablet app | Register device, start session, select package/menu, submit order/refills/service requests |
| Woosoo Print Bridge | Android printer relay | Keep printer online, receive print events, ACK/failed lifecycle |
| Krypton Woosoo PC | POS database host | Static IPv4 setup, POS database availability |
| Platform Docker Stack | Runtime orchestration | Compose services, deployment scripts, certificates, logs |

## Directory Structure

```text
/opt/woosoo/woosoo-platform/
  compose.yaml
  docker/
    certs/
    nginx/
    mysql/
    php/
  scripts/
    deployment/
  woosoo-nexus/
  tablet-ordering-pwa/
/etc/woosoo/woosoo.env
```

## Common Commands

Run commands from `/opt/woosoo/woosoo-platform` unless the step says otherwise.

```bash
cd /opt/woosoo/woosoo-platform
pwd
ls -la
cd woosoo-nexus
cd ../tablet-ordering-pwa
cd ..
```

```bash
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml ps
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 app
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 nginx
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 reverb
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 mysql redis
```

```bash
sudo bash scripts/deployment/doctor.sh
sudo bash scripts/deployment/apply-woosoo-config.sh
sudo bash scripts/deployment/deploy.sh
```

## Smoke Checks

```bash
ping 192.168.1.32
curl -k https://192.168.1.31
curl -k https://192.168.1.31:4443/build-info.json
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan route:list
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan config:clear
```

## Workflows

### First-Time Restaurant Setup

1. Set Krypton Woosoo PC IPv4 to `192.168.1.32`, `255.255.255.0`, gateway `192.168.1.1`.
2. Confirm the Woosoo server/Pi is reachable at `192.168.1.31`.
3. Confirm `/etc/woosoo/woosoo.env` exists and has restaurant values.
4. Run `sudo bash scripts/deployment/doctor.sh`.
5. Apply config and deploy from platform root.
6. Open Nexus and Tablet PWA URLs.
7. Register tablets and verify an order reaches POS and print flow.

### Daily Startup Check

1. Verify all Docker services are up.
2. Open Nexus admin.
3. Open the tablet app.
4. Ping the POS host.
5. Check app, Reverb, MySQL, Redis, and nginx logs.
6. Confirm printers are online in the bridge.

### Deployment And Redeployment

1. Run preflight doctor.
2. Confirm no uncommitted emergency changes exist on deployed repos.
3. Run `sudo bash scripts/deployment/deploy.sh`.
4. Check Docker service health.
5. Clear and cache Laravel config if needed.
6. Confirm tablet build info from `https://192.168.1.31:4443/build-info.json`.
7. Run the acceptance checklist.

## Troubleshooting Matrix

| Symptom | First Check | Command |
|---|---|---|
| Nexus admin unreachable | nginx/app health | `docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 nginx app` |
| Tablet app unreachable | tablet container and HTTPS port | `curl -k https://192.168.1.31:4443/build-info.json` |
| POS connection fails | Krypton PC network and DB port | `ping 192.168.1.32` |
| Reverb/WebSocket fails | Reverb logs and app env | `docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 reverb app` |
| MySQL/Redis unhealthy | service status and logs | `docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 mysql redis` |
| Tablet stuck on old build | build-info endpoint | `curl -k https://192.168.1.31:4443/build-info.json` |
| Orders do not print | bridge heartbeat and print events | Check Print Bridge app status and Nexus print-event logs |
| Deployment script fails | preflight variables | `sudo bash scripts/deployment/doctor.sh` |

## Screenshot Checklist

- Windows adapter list with the active Ethernet adapter selected.
- IPv4 dialog showing IP `192.168.1.32`, mask `255.255.255.0`, gateway `192.168.1.1`.
- `/etc/woosoo/woosoo.env` with sensitive values hidden.
- `docker compose ps` showing expected services.
- Nexus admin login or dashboard.
- Tablet PWA loaded at `https://192.168.1.31:4443`.
- POS connectivity check.
- Print Bridge status screen.

## Final Handover Acceptance

- Nexus reachable at `https://192.168.1.31`.
- Tablet PWA reachable at `https://192.168.1.31:4443`.
- POS host `192.168.1.32` reachable.
- One test order reaches POS.
- Print flow verified through Print Bridge ACK or operator-confirmed print.
- Logs reviewed with no unresolved deployment blockers.
- Rollback path and backup location explained to operator.
