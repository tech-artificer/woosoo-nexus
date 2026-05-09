# Tablet Settings — Staff Access Guide

The tablet settings screen is PIN-protected and intended for staff only. Guests should not access this area.

---

## Accessing Settings

1. Look for the **Settings** icon — typically a gear icon (⚙) in a corner of the screen, or accessible via a long-press gesture on the header area.
2. A **PIN prompt** appears.
3. Enter the **4-digit staff PIN** (provided by your manager — keep it confidential).
4. The Settings screen opens.

> The PIN prevents guests from accidentally or intentionally changing device configuration.

---

## Available Settings

### Device Information
| Setting | Description |
|---------|-------------|
| **Device Name** | The registered name of this tablet (e.g., "Table 5 Tablet") |
| **Table Number** | Which table this device is assigned to |
| **Connection Status** | Shows whether the app is connected to the Woosoo Nexus server |
| **Last Synced** | Timestamp of last successful server sync |

### Server Configuration
| Setting | Description |
|---------|-------------|
| **API URL** | The base URL of the Woosoo Nexus server (`https://woosoo.local`) — only change if server address changes |
| **WebSocket Key** | The Reverb WebSocket key for real-time updates — baked in at build time, shown here for reference |

### Display & Behavior
| Setting | Description |
|---------|-------------|
| **Screen Timeout** | How long before the tablet dims to the welcome screen (when idle) |
| **Kiosk Mode** | Enables full-screen, no-navigation lockdown for guest-facing use |

### Maintenance
| Action | Description |
|--------|-------------|
| **Clear Cache** | Refreshes all locally cached menu data — use after major menu changes |
| **Reload App** | Force-reloads the PWA (equivalent to a hard refresh) |
| **Reset Registration** | Unregisters this device — requires a new Security Code to reconnect |

---

## Exiting Settings

- Tap **Done** or **Close** (top right of settings screen).
- Settings automatically lock after **2 minutes of inactivity** — the PIN will be required again to re-enter.

---

## Resetting the Tablet

If you need to wipe the device and start fresh (e.g., moving the tablet to a different table):

1. Enter Settings (PIN required).
2. Tap **Reset Registration**.
3. Confirm the reset.
4. The tablet returns to the Registration screen.
5. Enter a new Security Code from the admin panel to re-register to the correct table.

---

## Security Best Practices

- **Do not share the PIN** with guests.
- **Change the PIN** if you suspect it has been seen by someone unauthorized.
- **Lock the tablet screen** when leaving the area unattended for extended periods.
- PIN changes are managed by the system admin — contact them if you need the PIN updated.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Forgot the PIN | Contact your Admin — the PIN can be reset from the admin dashboard under Device settings. |
| Settings screen won't open | Try long-pressing the header area slowly. If still unresponsive, reload the app. |
| "Cannot connect to server" shown in Device Info | Check WiFi connection. If WiFi is fine, the server may be down — contact your system admin. |
| App seems stuck after clearing cache | Tap **Reload App** after clearing cache. The app will restart with fresh data. |