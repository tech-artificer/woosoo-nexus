# Tablet Ordering System — Requirements & Deployment

The Tablet Ordering PWA is a web application that runs in a browser on any tablet placed at a guest table. It connects to the Woosoo Nexus server on the local network.

---

## Hardware Requirements

| Requirement | Minimum |
|-------------|---------|
| Screen size | 10 inches or larger (recommended for comfortable guest use) |
| Touch screen | Required |
| RAM | 3 GB or more |
| Storage | 2 GB free (for PWA and browser cache) |
| Battery / Power | Keep tablet plugged in while in use |
| Stand / Mount | Required — place securely on table, screen facing guest |

**Supported device types:** Android tablet, iPad, Windows tablet

---

## Software Requirements

| Platform | Minimum Version |
|----------|----------------|
| Android | 10+ |
| iPadOS | 14+ |
| Windows | 10 |
| Chrome | 90+ (recommended browser for PWA) |
| Safari (iOS) | 14+ |

---

## Network Requirements

- The tablet must be connected to the **same WiFi network** as the Raspberry Pi server.
- Server domain: **`woosoo.local`** (resolved via LAN DNS — dnsmasq on the Pi).
- **WebSocket support** required for real-time order updates.
- No internet access required — everything works on the local network.

---

## One-Time Setup Steps (Per Device)

### Step 1 — Connect to the WiFi Network

Connect the tablet to the restaurant's WiFi that includes the Raspberry Pi.

### Step 2 — Install the CA Certificate

The server uses HTTPS with a local CA. Without this certificate, the browser will refuse the connection.

**On Android (Chrome):**
1. Copy the file `woosoo-local-ca.crt` to the tablet (via USB, email, or shared folder).
2. Open the file — Android will prompt to install it as a trusted certificate.
3. Set the name to `Woosoo Local CA` and confirm.
4. Go to **Chrome Settings → Privacy & Security → Manage Certificates** and verify it appears.

**On iPad (Safari):**
1. Copy `woosoo-local-ca.crt` to the iPad (AirDrop or Files app).
2. Tap the file — you'll see "Profile Downloaded" in Settings.
3. Go to **Settings → General → VPN & Device Management** → tap the profile → **Install**.
4. Then go to **Settings → General → About → Certificate Trust Settings** and enable the certificate.

### Step 3 — Open the Tablet App

1. Open Chrome (Android) or Safari (iPad).
2. Navigate to: **`https://woosoo.local/tablet`**
3. The Woosoo ordering app loads.

### Step 4 — Install as PWA (Add to Home Screen)

**Android Chrome:**
1. Tap the three-dot menu (⋮) → **Add to Home Screen**.
2. Confirm the name and tap **Add**.
3. The Woosoo icon appears on the home screen.
4. Always launch from this icon to use full-screen kiosk mode.

**iPad Safari:**
1. Tap the Share button (□↑) → **Add to Home Screen**.
2. Confirm and tap **Add**.

### Step 5 — Register the Device

1. Launch the PWA from the home screen icon.
2. A **Registration** screen appears on first launch.
3. Enter the **Security Code** provided by the admin (generated when registering the device in the admin panel).
4. Tap **Register**.

**Expected result:** The app advances past the registration screen and shows the guest Welcome screen, ready for ordering.

---

## Daily Use

- The tablet should stay powered on and connected to WiFi at all times.
- The PWA auto-reconnects if the WiFi drops briefly.
- If the app appears frozen, reload it from the browser or tap **Settings → Clear Cache** (PIN required).

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Browser says "Not Secure" / refuses connection | CA certificate not installed. Follow Step 2 above. |
| `woosoo.local` doesn't resolve | Check that the tablet is on the correct WiFi network. Try `http://192.168.100.42/tablet` as a fallback. |
| Registration code rejected | Codes expire after 10 minutes. Ask admin to generate a new one. |
| App shows blank screen | Reload the page. If it persists, check that the Pi server is running. |
| App not in full screen | Launch from the home screen PWA icon, not from the browser address bar. |