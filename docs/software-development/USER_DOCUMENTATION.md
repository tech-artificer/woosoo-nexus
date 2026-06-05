---
status: canonical
last_reviewed: 2026-06-02
scope: ecosystem
---

# Woosoo user documentation

## 1. Purpose

This document explains how to use and operate the Woosoo system. It is organized by role:

- Staff/tablet user.
- Admin/manager.
- Print Bridge operator.
- Deployment/operator.
- Support/maintenance user.

Use this guide during training, daily startup, restaurant service, incident response, and handover.

## 2. System at a glance

Woosoo has four main pieces:

| Piece | What users see | Main responsibility |
|---|---|---|
| Tablet Ordering PWA | Tablet ordering screens | Start sessions, choose package/menu items, submit orders, request refills/service. |
| Woosoo Nexus | Admin panel | Manage orders, devices, menus, packages, reports, monitoring, and operations. |
| Print Bridge | Android relay app | Receive print jobs, print receipts, report status. |
| Platform stack | Docker services and scripts | Run Nexus, Tablet PWA, Reverb, MySQL, Redis, Nginx, queue, and scheduler. |

## 3. Daily startup checklist

Run this before the restaurant opens.

1. Confirm the Woosoo server/Pi is powered on and connected to the LAN.
2. Confirm the Krypton POS PC is powered on and reachable.
3. Open Nexus admin at `https://<PUBLIC_HOST>`.
4. Open the Tablet PWA at `https://<PUBLIC_HOST>:4443`.
5. Open the Print Bridge app and confirm the status is connected.
6. Confirm the printer is paired and powered on.
7. Place a test order.
8. Confirm the order appears in Nexus.
9. Confirm the receipt prints.
10. Confirm no unresolved errors appear in Monitoring or Print Audit.

## 4. Staff/tablet user guide

### 4.1 Start a table session

1. Open the tablet app from the home screen shortcut.
2. Confirm the welcome screen shows the correct table or device context.
3. Tap **Begin the Feast**.
4. Select the guest count.
5. Continue to package selection.

### 4.2 Choose a package

1. Review available packages.
2. Select the package requested by the table.
3. Continue to the menu.

Packages and pricing are controlled by Nexus. The tablet only records the package choice.

### 4.3 Add menu items

1. Browse menu categories.
2. Tap add controls for requested items.
3. Review unavailable or upgrade-locked states.
4. Check the cart/order summary before submission.

### 4.4 Submit an order

1. Open the review screen.
2. Confirm guest count, package, and items.
3. Tap the submit/place-order action.
4. Wait for the order submission overlay to finish.
5. Do not close the app while the order is submitting.

If the tablet shows a friendly failure message, ask a staff member or admin to verify Nexus connectivity. Do not assume the order was accepted unless it appears in Nexus or prints.

### 4.5 Use the in-session screen

After submission, the tablet moves to the in-session screen. Use it to:

- Review order summary.
- Add more items where allowed.
- Send refills.
- Request service.
- Watch session status.

### 4.6 End or recover a session

The backend/POS owns session-end truth. The tablet reacts to session reset, completed, cancelled, or voided events.

If the tablet appears stuck:

1. Check whether the order/session is still active in Nexus.
2. Refresh the tablet once.
3. If the tablet shell is stale, use `/sw-reset`.
4. Re-register only if device/token state is invalid.

## 5. Admin and manager guide

### 5.1 Log in to Nexus

1. Open `https://<PUBLIC_HOST>`.
2. Enter admin credentials.
3. Use the left navigation to access operational pages.

### 5.2 Main pages

| Page | Use it for |
|---|---|
| Dashboard | Daily overview of orders, sessions, and devices. |
| Orders | Current and historical order monitoring. |
| POS | POS/Krypton tables and order actions. |
| Menus | Menu availability, filters, and images. |
| Packages | Package configuration. |
| Devices | Tablet and relay registration, tokens, table assignment, last seen. |
| Service Requests | Staff/customer service request monitoring. |
| Reports | Sales, guest, menu, status, discount/tax, print audit. |
| Monitoring | Queue, database, Reverb, print latency, session controls. |
| Reverb Service | WebSocket service visibility and controls. |
| Manual | In-app documentation guides. |

### 5.3 Register a tablet

1. In Nexus, go to **Devices**.
2. Create a device record for the tablet.
3. Assign a table if needed.
4. Generate a token.
5. Copy the token immediately.
6. On the tablet, open settings and paste/register the token.
7. Confirm Nexus shows a recent **Last Seen** timestamp.

### 5.4 Register a Print Bridge device

1. In Nexus, go to **Devices**.
2. Create a device record for the relay.
3. Generate a token.
4. Enter the token in the Print Bridge app.
5. Confirm heartbeat appears in Nexus.

### 5.5 Manage menu and package content

1. Use **Menus** to inspect and filter menu items by course, group, and image state.
2. Use **Packages** and package configuration screens to control available packages and allowed menu sets.
3. Test tablet menu browsing after major content changes.
4. Confirm unavailable items show correctly on the tablet.

### 5.6 Monitor orders and print health

During service:

1. Watch **Orders** for active order state.
2. Watch **Monitoring** for print latency and stuck-event indicators.
3. Watch **Devices** for tablet and relay heartbeat.
4. Use **Reports -> Print Audit** to check print status.
5. Use reset or force-end only after confirming the operational reason.

## 6. Print Bridge operator guide

### 6.1 Configure the relay

1. Install the Print Bridge APK on the Android relay device.
2. Open the app.
3. Enter server URL, for example `https://<PUBLIC_HOST>`.
4. Enter the device token from Nexus.
5. Save and connect.

### 6.2 Pair the printer

1. Pair the thermal printer in Android Bluetooth settings.
2. Open Print Bridge settings.
3. Select the paired printer.
4. Run a test print.

### 6.3 Understand relay states

| State or metric | Meaning | Action |
|---|---|---|
| Connected heartbeat | Relay can reach Nexus. | Continue monitoring. |
| Queue pending | Jobs are waiting to print or ACK. | Check printer status and network. |
| ACK backlog | Jobs printed locally but not yet acknowledged by Nexus. | Keep relay online; investigate server/network if age grows. |
| Dead-letter jobs | Jobs exceeded retry/terminal handling rules. | Review, requeue, or discard intentionally. |
| Printer disconnected | Bluetooth printer is not connected. | Power printer, pair again, or reconnect in app. |

### 6.4 Resolve failed print jobs

1. Open the queue or dead-letter screen.
2. Identify the affected `print_event_id`.
3. Check printer power, paper, cover, and Bluetooth connection.
4. Retry only if a duplicate physical print is acceptable or the previous print did not complete.
5. If the order already printed, avoid reprinting and resolve the server ACK/audit state instead.

## 7. Deployment/operator guide

### 7.1 First-time setup

1. Clone or place the platform repo at the intended deployment path.
2. Confirm `woosoo-nexus/` and `tablet-ordering-pwa/` are present as sibling app repos under the platform root.
3. Create environment files from templates.
4. Configure public host, Reverb keys, database values, POS host, and device passcode.
5. Install local certificate on tablets if using LAN HTTPS.
6. Start Docker services.
7. Run smoke checks.
8. Register tablets and Print Bridge.
9. Place one end-to-end test order.

### 7.2 Common platform commands

Run from the platform root:

```bash
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml ps
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 app nginx reverb
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 mysql redis queue scheduler
```

### 7.3 Smoke checks

```bash
curl -k https://<PUBLIC_HOST>
curl -k https://<PUBLIC_HOST>:4443/build-info.json
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan route:list
```

Operational smoke test:

1. Place order on tablet.
2. Confirm Nexus order appears.
3. Confirm POS/Krypton receives the order.
4. Confirm Print Bridge receives and prints.
5. Confirm ACK/print audit state.

### 7.4 Rollback

1. Stop accepting new tablet orders if possible.
2. Record current branch/commit and active sessions.
3. Revert to the last known-good branch/commit using the deployment process.
4. Restart affected services.
5. Run smoke checks.
6. Review any in-flight print jobs and POS orders.
7. Document the incident and follow-up fix.

## 8. Troubleshooting and FAQ

### 8.1 Tablet cannot load

Checks:

- Tablet Wi-Fi is connected to the restaurant LAN.
- Nexus/Tablet host is reachable.
- Certificate is installed and trusted.
- Tablet build endpoint responds.
- Service worker is not stuck on an old shell.

Actions:

1. Refresh the browser/app.
2. Open `https://<PUBLIC_HOST>:4443/build-info.json`.
3. Use `/sw-reset` only if normal refresh fails.
4. Re-register only if token/device state is invalid.

### 8.2 Tablet cannot submit order

Checks:

- Device token is valid.
- Nexus API is reachable.
- POS/Krypton is reachable.
- No raw technical error is shown to customers.

Actions:

1. Check Nexus **Orders** and logs before retrying.
2. If no order exists, retry from tablet.
3. If an order exists but tablet did not advance, recover using Nexus order/session state.

### 8.3 Reverb/WebSocket is not updating clients

Checks:

- Reverb service/container is running.
- `REVERB_APP_KEY`, host, port, scheme, and path match client config.
- Nginx routes `/app` WebSocket traffic.
- Device auth or channel authorization is passing.

Actions:

1. Check Reverb logs.
2. Check browser or Flutter logs.
3. Restart Reverb only after capturing relevant errors.
4. Verify fallback polling if available.

### 8.4 POS/Krypton connection errors

Checks:

- POS PC is powered on.
- POS host IP is correct.
- POS database port is open.
- Nexus app logs do not show connection failures.

Actions:

1. Ping the POS host.
2. Check Nexus logs.
3. Avoid manual database edits unless approved.
4. Reconcile toward POS truth after the connection is restored.

### 8.5 Orders do not print

Checks:

- Print Bridge heartbeat is current.
- Printer is powered on, paired, and has paper.
- Queue has pending, failed, or dead-letter jobs.
- Nexus print-event API and Reverb events are available.

Actions:

1. Open the Print Bridge status screen.
2. Run a test print.
3. Check queue/dead-letter list.
4. Review print audit in Nexus.
5. Requeue or retry only with operator intent.

### 8.6 Duplicate or stuck print jobs

Cause categories:

- Repeated WebSocket/polling payload.
- ACK failure after physical print.
- Stale reserved job.
- Manual retry after physical receipt already printed.

Actions:

1. Identify `print_event_id`.
2. Check local Print Bridge queue status.
3. Check Nexus print audit.
4. Avoid duplicate physical print unless needed.
5. Use ACK/retry/dead-letter handling instead of clearing records blindly.

### 8.7 Deployment script failure

Checks:

- Running from platform root.
- Env file exists and contains required non-secret placeholders replaced.
- Docker is running.
- App repos are on expected branches.
- Compose file validates.

Actions:

1. Capture exact script output.
2. Do not rerun blindly if a migration or deploy partially completed.
3. Check `docker compose ... ps`.
4. Check app/nginx/reverb logs.
5. Roll back if service is degraded and fix is not immediate.

## 9. Glossary

| Term | Meaning |
|---|---|
| Nexus | Laravel backend/admin/API application. |
| Tablet PWA | Nuxt tablet ordering application. |
| Print Bridge | Flutter Android printer relay. |
| Reverb | Laravel WebSocket server used for realtime events. |
| Krypton | Third-party POS database/system. |
| ACK | Acknowledgment that a print job physically printed and was reported back. |
| Dead letter | Local queue state for jobs that need operator review after retry/terminal handling. |
| Intent-only payload | Tablet payload that contains choices only, not backend-owned pricing/state. |
| POS-first authority | Rule that POS rows remain authoritative after POS-side success; do not compensate with destructive deletes. |

## 10. Acceptance checklist

- [ ] Nexus admin opens.
- [ ] Tablet PWA opens.
- [ ] Tablet device is registered.
- [ ] Print Bridge is registered.
- [ ] Printer test print succeeds.
- [ ] POS host is reachable.
- [ ] Test order submits from tablet.
- [ ] Order appears in Nexus.
- [ ] Receipt prints.
- [ ] Print ACK/audit state is visible.
- [ ] Monitoring shows no unresolved blocker.

## 11. Revision history and changelog basis

| Date | Version | Source basis | Notes |
|---|---|---|---|
| 2026-06-02 | 1.0 | Live source, contracts, local `git log`, and user-provided changelog intake | Created role-based user documentation. |
