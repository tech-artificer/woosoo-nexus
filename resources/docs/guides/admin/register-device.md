# How to Register a Device

Each table tablet (Tablet Ordering PWA) and each print relay device (Woosoo Print Bridge) must be registered in the admin before it can connect to the system.

---

## Device Types

| Type | What it is |
|------|-----------|
| **Tablet** | The guest-facing ordering kiosk mounted on a table |
| **Relay** | The Android phone/tablet running Woosoo Print Bridge (connects Bluetooth printer) |

---

## Part 1 — Register the Device in Admin

1. Click **Devices** in the left sidebar.
2. Click **New Device** (top right).
3. Fill in the device form:

| Field | Description |
|-------|-------------|
| **Device Name** | A descriptive label, e.g. "Table 5 Tablet" or "Kitchen Relay" |
| **Device Type** | Choose Tablet or Relay |
| **Branch** | The branch this device belongs to |
| **Table Number** | (Tablets only) The table this device is assigned to |

4. Click **Register Device**.

> **⚠️ Important:** A **Security Code** is displayed immediately after registration. This code is shown **only once** and cannot be retrieved later. **Write it down or photograph it now.**

---

## Part 2 — Connect the Physical Device

### For Tablets (PWA)

1. Open Chrome on the tablet.
2. Navigate to: **`https://woosoo.local/tablet`** (or the correct tablet PWA URL).
3. The app will show a **Registration** screen on first launch.
4. Enter the **Security Code** from Part 1.
5. Tap **Register**.
6. The tablet is now registered and shows the guest ordering interface.

### For Print Relay Devices (Woosoo Print Bridge)

1. Open the **Woosoo Print Bridge** app on the Android device.
2. Tap the **Settings** screen (bottom navigation).
3. Enter the **Security Code** from Part 1 in the **Registration Code** field.
4. Tap **Register**.
5. The app connects to the server and shows **Connected** status.

---

## Verifying a Device is Connected

1. Click **Devices** in the admin sidebar.
2. Find the device in the list.
3. The **Status** column should show a green **Active** badge.
4. Click the device row to open its detail sheet, which shows last seen time, IP, and connection health.

---

## Managing Devices

| Action | How to Do It |
|--------|-------------|
| Rename a device | Click the device row → Edit → update name → Save |
| Reassign to a different table | Edit device → change Table Number |
| Revoke access | Edit device → click **Revoke Token** — the device will be disconnected immediately |
| Delete a device | Only possible if the device has been inactive for 24+ hours |

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Security code not working | Codes are case-sensitive and expire after 10 minutes. Regenerate a new code from the admin if needed. |
| Device shows "Offline" in admin | Check WiFi on the physical device. Both device and server must be on the same network. |
| Can't find "New Device" button | Your role may not have Devices management permission. |
| Device registered but no orders received | Check that the Reverb WebSocket server is running (`sudo supervisorctl status reverb`) on the Pi. |