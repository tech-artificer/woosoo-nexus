---
status: canonical
last_reviewed: 2026-05-26
scope: ecosystem
---

# Woosoo Restaurant Operations and Handover Manual

This is the maintainable source for the restaurant handover manual. The final deliverable is `docs/deployment/woosoo-restaurant-operations-handover-manual.docx`.

---

## Restaurant Network Values

| Item | Value |
|---|---|
| Woosoo server / Pi public host | `192.168.1.31` |
| Krypton Woosoo PC / POS host | `192.168.1.32` |
| Krypton subnet mask | `255.255.255.0` |
| Krypton gateway | `192.168.1.1` |
| POS database | `krypton_woosoo` |
| POS database port | `2121` |

Only restaurant network values are listed here. Do not use older sample values (e.g. `192.168.100.7`) for any production handover.

---

## Business Requirements

- The restaurant ordering system runs on the local LAN; internet connectivity is not required for order flow.
- Woosoo Nexus owns business truth: pricing, packages, POS/Krypton writes, sessions, orders, realtime events, and print events.
- The Tablet Ordering PWA sends customer intent only — it does not hold authoritative state.
- The Print Bridge confirms the last-mile print result through heartbeat and acknowledgement flows.
- Operators must be able to deploy, redeploy, check logs, troubleshoot, roll back, and prove handover readiness without reading source code.

---

## App Responsibilities

| App | Role | Operator Usage |
|---|---|---|
| Woosoo Nexus | Laravel admin/API, POS integration, sessions, orders, Reverb, print events | Admin panel, device management, order monitoring, configuration checks |
| Tablet Ordering PWA | Customer-facing tablet app | Register device, start session, select package/menu, submit order/refills/service requests |
| Woosoo Print Bridge | Android printer relay app | Keep printer online, receive print events, ACK/failed lifecycle |
| Krypton Woosoo PC | POS database host | Static IPv4 setup, POS database availability |
| Platform Docker Stack | Runtime orchestration | Compose services, deployment scripts, certificates, logs |

---

## How To Navigate The Apps

### Woosoo Nexus Admin

Open a browser on any LAN device and navigate to `https://192.168.1.31`. Log in with your admin credentials.

After login, use the left sidebar. Admin users see three groups:

| Group | Pages | When to use |
|---|---|---|
| Main | Dashboard, Orders, POS, Menus, Packages, User Management, Devices, Service Requests | Daily operations, device setup, order monitoring, staff/user work, menu/package updates |
| Analytics | Reports, Daily Sales, Hourly Sales, Guest Count, Menu Items, Order Status, Print Audit, Discount & Tax | End-of-day checks, sales analysis, print audit, guest and menu reporting |
| Configuration | Branches, Access Control, Accessibility, Event Logs, Reverb Service, Monitoring | System setup, role/permission control, audit logs, realtime health, queue/database checks |

**Key pages for daily use:**
- **Dashboard** — live overview of current sessions, orders, and active devices
- **Orders** — live order list and full order history with status
- **Devices** — register new tablets and print relay apps, copy device tokens, see last heartbeat
- **Monitoring** — queue/database/Reverb health at a glance
- **Manual** — in-app guide library (if configured)

### Tablet Ordering PWA

The customer-facing tablet path follows this sequence:

1. Open the welcome screen at `/`.
2. If the tablet is not registered, tap the gear icon to open **Settings**, enter or create the PIN, then complete the device registration (see _Tablet Setup and Registration_ below).
3. Tap **Begin the Feast**.
4. Select guest count on `/order/start`.
5. Choose a dining package on `/order/packageSelection`.
6. Browse meats, sides, desserts, and drinks on `/menu`.
7. Open the order summary and continue to `/order/review`.
8. Submit the order — the app sends a request to the server.
9. The tablet moves to `/order/in-session` for submitted items, refills, service requests, and session status.
10. When the session ends, the tablet shows `/order/session-ended` for a few seconds then returns to the welcome screen for the next table.

**Staff-only maintenance** is under `/settings`. The emergency reset route is `/sw-reset` — use it only when normal settings maintenance cannot refresh a stuck tablet.

### Woosoo Print Bridge (Android)

The Print Bridge is an Android app that runs on a phone or tablet connected to the Bluetooth or USB printer.

- Open the app and verify the **Server URL** is set to `https://192.168.1.31`.
- Verify the **Device Token** matches the token issued in Nexus Admin → Devices.
- The app shows a **heartbeat status indicator** — green means it is communicating with the server.
- When an order is placed, the Bridge receives a WebSocket event and sends a print job to the connected printer.
- Confirm each new print by checking the **Print Log** tab inside the app.

---

## Tablet Setup and Registration

Complete these steps once for each physical tablet before handing it over for service.

### Step 1 — Install the certificate (first tablet only, per network)

1. On the Pi server, locate `docker/certs/fullchain.pem`.
2. Transfer the certificate file to the tablet (email, USB, or network share).
3. On the Android tablet: go to **Settings → Security → Install from storage**, select the `.pem` file, and install it as a **CA certificate** (not a Wi-Fi or VPN certificate).
4. Name it something recognisable (e.g. `Woosoo Local CA`).

> Skip this step if the tablet already trusts the certificate from a previous setup.

### Step 2 — Open the app in Chrome

1. Open **Chrome** on the tablet.
2. Navigate to `https://192.168.1.31:4443`.
3. If the browser shows a certificate warning, tap **Advanced → Proceed** (this only happens once after installing the CA).
4. The Woosoo welcome screen should appear.

### Step 3 — Create a device record in Admin

1. On a PC or laptop, open `https://192.168.1.31` and log in.
2. Go to **Devices** in the left sidebar.
3. Click **Add Device** (or **New Device**).
4. Enter:
   - **Name**: a descriptive label, e.g. `Table 3 Tablet`
   - **IP address**: the tablet's current LAN IP (visible in Android Settings → Wi-Fi → your network)
   - **Table**: assign the table this tablet covers (optional but recommended)
5. Save the device record.
6. After saving, click the device row and find **Generate Token**. Click it.
7. **Copy the token immediately** — it is shown only once.

### Step 4 — Register the device on the tablet

1. On the welcome screen, tap the **gear icon** (bottom corner) to open Settings.
2. Enter the Settings PIN. If setting a PIN for the first time, choose a numeric PIN that staff will remember.
3. In Settings, find **Device Registration** and paste the token you copied in Step 3.
4. Tap **Register**. The tablet should confirm registration with a success message.
5. Return to the welcome screen. The tablet is now ready for service.

### Step 5 — Add the app to the home screen (kiosk mode)

1. In Chrome, tap the **three-dot menu** (top right).
2. Tap **Add to Home screen**.
3. Confirm with **Add**.
4. Close Chrome and launch the app from the home screen shortcut — it should open full-screen with no browser controls visible.
5. Enable Android **Screen Pinning** (Settings → Security → Screen Pinning) and pin the app so guests cannot navigate outside it.

### Step 6 — Verify the tablet is operational

1. In Admin → Devices, find the tablet entry. The **Last Seen** timestamp should be recent.
2. On the tablet, tap **Begin the Feast** and place a test order.
3. Confirm the order appears in Admin → Orders.
4. Confirm the order reaches the POS (Krypton).

---

## Print Bridge Setup

Complete this once for each Android device that will act as a print relay.

### Step 1 — Install and open the Print Bridge app

1. Install the Woosoo Print Bridge APK on the Android device.
2. Open the app.

### Step 2 — Configure server connection

1. In the app, go to **Settings** (gear icon).
2. Set **Server URL** to `https://192.168.1.31`.
3. Leave the WebSocket port as default unless the server is configured otherwise.

### Step 3 — Create a device record for the Print Bridge

1. In Nexus Admin → Devices, click **Add Device**.
2. Enter:
   - **Name**: e.g. `Kitchen Printer Relay`
   - **IP address**: the Android device's LAN IP
   - **Type**: Printer (if the type field is available)
3. Save and click **Generate Token**. Copy the token.

### Step 4 — Enter the device token in the app

1. In the Print Bridge app Settings, paste the token into **Device Token**.
2. Tap **Connect** or **Save**.
3. The status indicator should turn green (connected).

### Step 5 — Pair the Bluetooth or USB printer

1. For Bluetooth: pair the printer through Android Bluetooth settings before opening the app.
2. In the Print Bridge app, go to **Printer Settings** and select the paired printer from the list.
3. Tap **Test Print** — a test receipt should print.

### Step 6 — Verify end-to-end print flow

1. Place a test order from a registered tablet.
2. The Print Bridge app should receive the print event and show it in the **Print Log**.
3. Confirm the physical printer prints the receipt.
4. In Admin → Print Audit, verify the order shows `is_printed = true`.

---

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

---

## Common Commands

Run commands from `/opt/woosoo/woosoo-platform` unless a step says otherwise.

```bash
# Navigate to platform root
cd /opt/woosoo/woosoo-platform

# Check service status
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml ps

# Tail logs for each service
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 app
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 nginx
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 reverb
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=100 mysql redis

# Run deployment helpers
sudo bash scripts/deployment/doctor.sh
sudo bash scripts/deployment/apply-woosoo-config.sh
sudo bash scripts/deployment/deploy.sh
```

---

## Smoke Checks

Run these after every deployment to confirm the system is healthy.

```bash
# Ping the POS host
ping 192.168.1.32

# Check Nexus admin is reachable
curl -k https://192.168.1.31

# Check Tablet PWA is reachable and has the correct build
curl -k https://192.168.1.31:4443/build-info.json

# Verify Laravel routes are loaded
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan route:list

# Clear cached config if environment changes were made
docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan config:clear
```

---

## Workflows

### First-Time Restaurant Setup

1. Set the Krypton Woosoo PC IPv4 to `192.168.1.32`, subnet mask `255.255.255.0`, gateway `192.168.1.1`.
2. Confirm the Woosoo server/Pi is reachable at `192.168.1.31`.
3. Confirm `/etc/woosoo/woosoo.env` exists and contains restaurant-specific values (not sample/placeholder values).
4. Run `sudo bash scripts/deployment/doctor.sh` — resolve any failures before continuing.
5. Apply config: `sudo bash scripts/deployment/apply-woosoo-config.sh`.
6. Deploy: `sudo bash scripts/deployment/deploy.sh`.
7. Open `https://192.168.1.31` (Nexus admin) and `https://192.168.1.31:4443` (Tablet PWA) in a browser.
8. Register all tablets (see _Tablet Setup and Registration_).
9. Set up the Print Bridge on the kitchen Android device (see _Print Bridge Setup_).
10. Place a test order end-to-end: tablet → Nexus → POS → print.

### Daily Startup Check

Perform before the restaurant opens for the day.

1. **Check Docker services are up:**
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml ps
   ```
   All services (`nginx`, `app`, `queue`, `reverb`, `mysql`, `redis`, `tablet-pwa`) must show `Up` or `healthy`. If any service shows `Exit` or `Restarting`, restart it:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml restart <service-name>
   ```

2. **Open Nexus admin** at `https://192.168.1.31` and verify the Dashboard loads correctly.

3. **Open the Tablet PWA** at `https://192.168.1.31:4443` on at least one tablet and verify the welcome screen appears.

4. **Ping the POS host:**
   ```bash
   ping 192.168.1.32
   ```
   Must return responses. If it fails, check Krypton PC power and network cable.

5. **Check recent logs for errors:**
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=50 app nginx reverb
   ```
   Look for `ERROR` or `CRITICAL` lines. Investigate any that appeared since the last shutdown.

6. **Verify Print Bridge is online:**
   - Open the Print Bridge app on the kitchen Android device.
   - Confirm the status indicator is green.
   - Check Admin → Devices for the relay device — **Last Seen** should be within the last few minutes.

7. **Run a smoke check** (optional but recommended before busy service):
   ```bash
   curl -k https://192.168.1.31:4443/build-info.json
   ```
   Confirms the tablet PWA is serving the expected build.

### Deployment and Redeployment

1. Run `sudo bash scripts/deployment/doctor.sh` — fix any preflight failures.
2. Confirm no uncommitted emergency changes exist in the deployed repos.
3. Run `sudo bash scripts/deployment/deploy.sh`.
4. After the script completes, check Docker service health with `docker compose ... ps`.
5. Clear and recache Laravel config if environment variables changed:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan config:clear
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan config:cache
   ```
6. Confirm the tablet build is updated:
   ```bash
   curl -k https://192.168.1.31:4443/build-info.json
   ```
7. Run the acceptance checklist (see _Final Handover Acceptance_).

### Software Update (New Code Without Full Re-setup)

Run this workflow when a new bugfix or feature is released and pushed to the `staging` branch.
This is the routine update path and does not require system reconfiguration.

1. **SSH into the Pi:**
   ```bash
   ssh pi@192.168.1.31
   ```

2. **Navigate to the Nexus repo:**
   ```bash
   cd /opt/woosoo/woosoo-nexus
   ```

3. **Run the update script:**
   ```bash
   sudo bash scripts/deployment/update-client.sh
   ```
   The script will:
   - Pull the latest code from `staging` branch for both `woosoo-nexus` and `tablet-ordering-pwa`
   - Verify both repos are on the correct branch
   - Build new Docker images
   - Start/restart services
   - Run database migrations
   - Clear and recache Laravel config, routes, and views
   - Show container status
   - Run health checks at the end

   A backup snapshot is automatically saved to `/opt/woosoo/backups/update-YYYYMMDD-HHMMSS/` before pulling new code — **note this directory name in case rollback is needed**.

4. **Check the output for errors:**
   - Look for `ERROR` lines in the script output.
   - If migrations fail or Docker build fails, the update may be incomplete. See **Rollback** below to revert.

5. **Confirm services are healthy:**
   ```bash
   docker compose ps
   ```
   All services should show `Up` status.

6. **Run post-update smoke checks:**
   ```bash
   curl -k https://192.168.1.31/api/health
   curl -k https://192.168.1.31:4443/build-info.json
   ```
   Both should return HTTP 200 or valid JSON.

7. **Verify the Tablet PWA version:**
   - Open `https://192.168.1.31:4443` in Chrome on a tablet or browser.
   - The welcome screen should load without errors.
   - Tap **Begin the Feast** to confirm navigation works.

### Rollback

Revert to the previous commit if an update breaks something and you need to recover quickly.

1. **Find the backup directory from the failed update:**
   ```bash
   ls /opt/woosoo/backups/
   ```
   Look for the most recent `update-YYYYMMDD-HHMMSS` directory. (If you ran the update earlier today, it will have today's date.)

2. **Run the rollback script with the backup directory:**
   ```bash
   sudo bash scripts/deployment/rollback-client.sh /opt/woosoo/backups/update-YYYYMMDD-HHMMSS
   ```
   Replace `update-YYYYMMDD-HHMMSS` with the actual directory name. The script will:
   - Restore both git repos to their pre-update commits
   - Restore the `.env` configuration file
   - Rebuild Docker images
   - Restart services
   - Clear and recache Laravel config, routes, and views
   - Display container status

3. **Confirm services are healthy:**
   ```bash
   docker compose ps
   ```
   All services should show `Up` status.

4. **Re-run smoke checks:**
   ```bash
   curl -k https://192.168.1.31/api/health
   curl -k https://192.168.1.31:4443/build-info.json
   ```

5. **Verify tablet and admin are operational:**
   - Open `https://192.168.1.31` (Nexus admin) and confirm login works.
   - Open `https://192.168.1.31:4443` (Tablet PWA) and confirm the welcome screen appears.

6. **Notify the developer:**
   - Report the failed update and the rollback to the developer responsible for the code so the issue can be investigated.

---

## Emergency Procedures

### Server Unreachable — Admin Panel / Tablet App Not Loading

1. SSH into the Pi server.
2. Check Docker service status:
   ```bash
   cd /opt/woosoo/woosoo-platform
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml ps
   ```
3. If `nginx` is down, restart it:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml restart nginx
   ```
4. If `app` is down:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml restart app
   ```
5. If all services are down or the Pi has rebooted, bring everything back up:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml up -d
   ```
6. Wait 60 seconds then re-test `curl -k https://192.168.1.31`.

### Tablets Cannot Connect or Show an Error Screen

1. Confirm the Wi-Fi on the tablet is connected to the restaurant LAN.
2. From the tablet's Chrome browser, navigate to `https://192.168.1.31` to check if the server itself is reachable.
3. If the server is reachable but the PWA is broken, try a hard refresh:
   - Chrome on Android: tap the three-dot menu → **Reload**.
   - If a refresh does not fix it: tap the three-dot menu → **Settings → Privacy → Clear browsing data** (cookies and cached files), then reload.
4. If the tablet is stuck and cannot be refreshed normally, navigate to `https://192.168.1.31:4443/sw-reset` to perform an emergency service-worker reset.
5. After reset, return to `https://192.168.1.31:4443` and re-register the device if prompted.

### Orders Not Printing

1. Open the Print Bridge app and check the status indicator — if it is red, the relay lost connection to the server.
   - Tap **Reconnect** or restart the app.
2. Check Admin → Devices for the relay device — **Last Seen** shows when the last heartbeat arrived.
3. If the printer is offline:
   - Check Bluetooth is enabled and the printer is powered on.
   - In Print Bridge app → Printer Settings, re-select the printer and tap **Test Print**.
4. If print events are being received but not printing:
   - In Print Bridge app → Print Log, look for failed entries and tap the entry to retry.
5. In Nexus Admin → Print Audit, find affected orders. Mark them manually printed if needed to keep the kitchen informed.

### POS Connection Fails — Orders Not Reaching Krypton

1. `ping 192.168.1.32` — if this fails, the Krypton PC is unreachable. Check the PC is powered on and the Ethernet cable is connected.
2. If the PC is on but unreachable, verify its IP has not changed (must be `192.168.1.32` static).
3. Check Nexus app logs for database connection errors:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=50 app | grep -i "POS\|krypton\|2121"
   ```
4. If the POS service on Krypton has stopped, restart the Krypton Woosoo service on the PC.

### Reverb / WebSocket Errors — Realtime Updates Not Working

1. Check Reverb service:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml logs --tail=50 reverb
   ```
2. Restart Reverb if it has crashed:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml restart reverb
   ```
3. Clear and recache the app config:
   ```bash
   docker compose --env-file ./woosoo-nexus/.env -f compose.yaml exec -T app php artisan config:clear
   ```
4. In Admin → Configuration → Reverb Service, verify the status shows **running**.

---

## Troubleshooting Matrix

| Symptom | First Check | Command / Action |
|---|---|---|
| Nexus admin unreachable | nginx / app health | `docker compose ... logs --tail=100 nginx app` |
| Tablet app unreachable | tablet container and HTTPS port | `curl -k https://192.168.1.31:4443/build-info.json` |
| Tablet shows certificate warning | CA not installed on tablet | Install `docker/certs/fullchain.pem` as CA on tablet |
| Tablet stuck on welcome screen after order | Session or device token issue | Check Admin → Devices for last-seen; re-register if token expired |
| POS connection fails | Krypton PC network and DB port | `ping 192.168.1.32` |
| Orders not appearing in POS | App logs for DB errors | `docker compose ... logs --tail=50 app \| grep krypton` |
| Reverb / WebSocket fails | Reverb logs and app env | `docker compose ... logs --tail=100 reverb app` |
| MySQL / Redis unhealthy | Service status and logs | `docker compose ... logs --tail=100 mysql redis` |
| Tablet stuck on old build | Build-info endpoint | `curl -k https://192.168.1.31:4443/build-info.json` |
| Orders do not print | Print Bridge heartbeat and print events | Check Print Bridge app status; check Admin → Print Audit |
| Print Bridge shows red / disconnected | Device token or server URL | Verify token in Print Bridge matches Nexus Admin → Devices |
| Deployment script fails | Preflight variables | `sudo bash scripts/deployment/doctor.sh` |
| Queue not processing jobs | Queue service status | `docker compose ... logs --tail=50 queue` then restart if needed |
| Service requests not arriving | Session active on tablet | Confirm tablet is in `/order/in-session`; check realtime connection |

---

## Screenshot Checklist

Capture these screenshots during setup and attach to the handover report.

- Windows adapter list with the active Ethernet adapter selected.
- IPv4 dialog showing IP `192.168.1.32`, mask `255.255.255.0`, gateway `192.168.1.1`.
- `/etc/woosoo/woosoo.env` open with sensitive values hidden.
- `docker compose ps` output showing all expected services.
- Nexus admin dashboard loaded in browser.
- Tablet PWA loaded at `https://192.168.1.31:4443`.
- Admin → Devices showing registered tablet(s) with a recent **Last Seen** timestamp.
- Print Bridge app status screen showing green (connected).
- A test receipt printed by the Print Bridge.
- POS connectivity confirmed (ping or Krypton UI).

---

## Final Handover Acceptance

All items below must be confirmed before handover is complete.

- [ ] Nexus admin reachable at `https://192.168.1.31`.
- [ ] Tablet PWA reachable at `https://192.168.1.31:4443`.
- [ ] POS host `192.168.1.32` reachable (ping responds).
- [ ] All tablets registered in Admin → Devices with recent Last Seen.
- [ ] One full end-to-end test order placed: tablet → Nexus → POS → receipt printed.
- [ ] Print Bridge online and heartbeat confirmed in Admin → Devices.
- [ ] Logs reviewed with no unresolved deployment blockers.
- [ ] Rollback path and backup location explained to the operator.
- [ ] Operator has confirmed they can log in to Nexus admin independently.
- [ ] Operator has the Settings PIN for at least one registered tablet.
