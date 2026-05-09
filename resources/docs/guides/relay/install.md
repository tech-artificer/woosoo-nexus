# How to Install Woosoo Print Bridge

Woosoo Print Bridge is distributed as an Android APK file (sideloaded — not from the Play Store). This guide walks through installation and first-time setup.

---

## Step 1 — Get the APK File

Obtain the APK file from your system admin. It will be named something like `woosoo-print-bridge-v1.x.x.apk`.

Transfer the file to the Android device via:
- USB cable (copy to Downloads folder)
- Google Drive / shared folder
- Bluetooth file transfer

---

## Step 2 — Allow Installation from Unknown Sources

Android blocks installations from outside the Play Store by default.

1. Open **Settings** on the Android device.
2. Go to **Security** (or **Privacy** on newer Android versions).
3. Find **Install Unknown Apps** or **Special App Access → Install Unknown Apps**.
4. Select your file manager app (or the browser you used to download the APK).
5. Toggle **Allow from this source** to **ON**.

---

## Step 3 — Install the APK

1. Open the **Files** app (or any file manager).
2. Navigate to **Downloads**.
3. Tap on the `woosoo-print-bridge-v1.x.x.apk` file.
4. Tap **Install** when prompted.
5. Android will scan the file — tap **Install** again to confirm.
6. Tap **Done** (not "Open" yet — finish the setup steps first).

---

## Step 4 — Grant Permissions

On first launch, the app will request several permissions. Grant all of them:

| Permission | Why It's Needed |
|------------|----------------|
| **Bluetooth** | To discover and connect to the thermal printer |
| **Nearby Devices** (Android 12+) | Required for Bluetooth scanning on newer Android |
| **Storage** | For caching print jobs locally |
| **Network / Internet** | To connect to the Woosoo Nexus server |

If you accidentally denied a permission:
1. Go to **Settings → Apps → Woosoo Print Bridge → Permissions**.
2. Grant any denied permissions manually.

---

## Step 5 — Configure the App (Settings Screen)

1. Open the **Woosoo Print Bridge** app.
2. Tap the **Settings** icon in the bottom navigation bar.
3. Fill in the following fields:

| Field | Value |
|-------|-------|
| **API URL** | `https://woosoo.local` (or `http://192.168.100.42`) |
| **WebSocket Key** | Provided by your system admin (the Reverb VITE_REVERB_APP_KEY from the Nexus `.env`) |
| **Registration Code** | The Security Code generated when you registered this device in the admin panel |

4. Tap **Save** or **Register**.

**Expected result:** The Status screen shows:
- 🟢 **Server: Connected**
- ⚪ **Printer: Not connected** (printer pairing comes next)

---

## Step 6 — Disable Battery Optimization

To prevent Android from stopping the app during service:

1. Go to **Settings → Apps → Woosoo Print Bridge**.
2. Tap **Battery**.
3. Select **Unrestricted** (or disable battery optimization).

Also enable "Stay Awake" if the device has developer options:
1. Enable developer options (tap **Build Number** 7 times in Settings → About Phone).
2. Go to **Developer Options → Stay Awake**.
3. Enable it.

---

## Troubleshooting Installation

### Verify the APK Before Installing

Before tapping "Install Anyway" on the Play Protect warning, confirm the file is authentic:

1. On your PC, generate the SHA-256 hash of the downloaded APK:
   - **Windows:** `certutil -hashfile woosoo-print-bridge-v1.x.x.apk SHA256`
   - **Linux / Mac:** `sha256sum woosoo-print-bridge-v1.x.x.apk`
2. Compare the output to the hash displayed on the **Admin → Devices → Download Relay** page.
3. If the hashes match, proceed with installation.
4. If they do **not** match — **do not install**. Contact your system administrator immediately.

| Problem | Fix |
|---------|-----|
| "App not installed" error | Ensure enough storage space. Try redownloading the APK — it may be corrupted. |
| "Blocked by Play Protect" | Verify the APK hash matches the official release (see above), then tap **Install Anyway**. |
| App crashes on launch | Ensure the device runs Android 8.0+. Try restarting the device before opening. |
| Registration code rejected | Codes expire after 10 minutes. Generate a new one from admin → Devices. |
| App shows "Disconnected" after registration | Check WiFi — device and server must be on the same network. Verify the API URL is correct. |