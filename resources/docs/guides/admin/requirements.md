# System Requirements & Setup

The Woosoo Nexus admin dashboard runs on a Raspberry Pi 5 server and is accessed through a web browser on any device connected to the same local network.

---

## Network Requirements

- Device must be connected to the **same WiFi network** as the Raspberry Pi server.
- Default server address: **`https://woosoo.local`**
- Fallback IP address: **`http://192.168.100.42`** (use if `woosoo.local` does not resolve)
- The Pi's IP address may change when switching networks. Always use `woosoo.local` on the primary network.

---

## Browser Requirements

| Browser | Minimum Version |
|---------|----------------|
| Google Chrome | 90+ (recommended) |
| Microsoft Edge | 90+ |
| Firefox | 88+ |
| Safari | 14+ |

> **Tip:** Chrome or Edge on a desktop/laptop gives the best admin experience.

---

## SSL Certificate Trust (First-Time Setup)

The server uses a self-signed CA certificate. Most browsers will show a security warning the first time. To trust the certificate permanently:

### On Windows
1. Open Chrome and navigate to `https://woosoo.local`.
2. Click **Advanced** → **Proceed to woosoo.local (unsafe)** to temporarily access the site.
3. For permanent trust, download the CA cert from the Pi:
   - Ask your system admin for the file `woosoo-local-ca.crt` (located at `/srv/woosoo/certs/` on the Pi).
4. Double-click the `.crt` file → **Install Certificate** → **Local Machine** → **Trusted Root Certification Authorities**.

### On Android / iOS
- Open the `.crt` file on your device and follow the prompt to install a security certificate.
- On iOS: go to **Settings → General → About → Certificate Trust Settings** and enable the installed certificate.

---

## Prerequisites

- Valid admin account (email + password) — provided by your Super Admin.
- JavaScript and cookies must be **enabled** in the browser.
- Pop-up blocker should allow `woosoo.local` (some print functions use pop-ups).

---

## Supported Devices for Admin Access

| Device | Recommended Use |
|--------|----------------|
| Windows PC / Mac | Full admin, reports, user management |
| iPad / Android Tablet | Order monitoring, quick actions |
| Smartphone | Emergency monitoring only (limited screen space) |

---

## What the Admin Dashboard Manages

| Module | Purpose |
|--------|---------|
| Dashboard | Live sales stats, charts, and activity overview |
| Orders | Monitor, complete, print, and void orders |
| Menus | Manage menu items and availability |
| Packages | Set up dining packages (e.g., eat-all-you-can) |
| Devices | Register and manage tablets and relay devices |
| Users & Roles | Create staff accounts and assign permissions |
| Service Requests | View and respond to table call requests |
| Monitoring | System health, print queue, and orphaned orders |
| Event Logs | Application error and audit logs |
| Reports | Sales, guests, hourly trends, discount summaries |
| Configuration | POS (Krypton) database connection settings |