# Troubleshooting Printing Issues

Use this guide when print jobs are not arriving at the printer, the app is offline, or printing quality is poor.

---

## Quick Diagnosis Flowchart

```
Orders not printing?
       │
       ├─ Open Print Bridge app → Status screen
       │
       ├─ Server indicator Red?
       │    └─ Fix: Check WiFi → Reconnect server (see Problem 1)
       │
       ├─ Printer indicator Red/Yellow?
       │    └─ Fix: Reconnect Bluetooth printer (see Problem 2)
       │
       ├─ Both green but still not printing?
       │    └─ Check Queue screen → stuck jobs? (see Problem 3)
       │
       └─ Check Dead Letter screen → failed jobs? (see Problem 4)
```

---

## Problem 1 — App Shows "Server: Offline" or Disconnected

**Symptoms:** Red server indicator. No new jobs received since indicator turned red.

**Steps to fix:**
1. Check the WiFi connection on the Android device (pull down notification bar).
2. Open a browser and navigate to `https://woosoo.local` — if it loads, WiFi is fine.
3. In the Print Bridge app, go to **Tools → Reconnect Server**.
4. Wait 10 seconds — the server indicator should turn green.
5. If still offline, force-close the app (Android Recents → swipe away) and reopen it.
6. If the issue persists: the Raspberry Pi server may be down.
   - SSH into the Pi: `ssh woosoo@192.168.100.42`
   - Check services: `sudo supervisorctl status`
   - Restart if needed: `sudo supervisorctl restart all`

---

## Problem 2 — Printer Not Printing (App Shows "Printer: Disconnected")

**Symptoms:** Yellow or red printer indicator. Jobs may accumulate in the Queue.

**Steps to fix:**
1. Check that the printer is powered on (LED should be lit steadily).
2. Check that the printer is within **10 meters** of the Android device.
3. In the Print Bridge app, go to **Settings → Printer → Disconnect**, wait 5 seconds, then tap **Connect**.
4. If that doesn't work:
   - On Android: **Settings → Bluetooth** → find the printer → tap **Forget**.
   - Put the printer back in pairing mode (hold Feed button until LED blinks).
   - Re-pair from Bluetooth settings → open Print Bridge → Settings → Scan and select printer.
5. Send a **Test Print** from Tools screen to confirm.

---

## Problem 3 — Jobs Stuck in Queue (Not Printing Despite Green Status)

**Symptoms:** Queue screen shows pending jobs that don't move to history.

**Steps to fix:**
1. Navigate to the **Queue** screen.
2. Tap the stuck job → tap **Retry**.
3. If retry fails, try **Tools → Test Print** — if test print works, the original job format may be corrupted. Tap **Discard** on the stuck job and ask the admin to void and resubmit the order.
4. If the queue has many stuck jobs: tap **Tools → Clear Queue** (caution: jobs will be lost).
5. After clearing, notify admin — they can void and re-submit affected orders from the Orders page.

---

## Problem 4 — Jobs in Dead Letter (Failed After All Retries)

**Symptoms:** Dead Letter screen shows one or more failed jobs.

**Steps to fix:**
1. Open the **Dead Letter** screen.
2. Read the failure reason on each entry:
   - `"Bluetooth connection dropped"` → reconnect printer (Problem 2 above)
   - `"Printer paper out"` → reload paper in the printer, then retry
   - `"Printer cover open"` → close the printer cover, then retry
3. After fixing the root cause, tap the job → **Reprint**.
4. If repring succeeds, the job moves to Orders History.
5. If jobs keep landing in Dead Letter despite the printer being connected: contact your system admin.

---

## Problem 5 — Print Quality Issues

| Symptom | Fix |
|---------|-----|
| **Faint / light print** | Replace the thermal paper roll with a new one. The current roll may be near the end or low quality. |
| **Blank receipts** | Paper is loaded upside down — the thermal side faces the print head. Flip the roll. |
| **Partial print (cuts off)** | Check that the paper width matches the printer setting (58mm or 80mm). Contact admin to verify ESC/POS paper width config. |
| **Garbled / strange characters** | The app may be using the wrong ESC/POS encoding. Contact admin — the `PRINT_BRIDGE_CHARSET` setting in the server `.env` may need adjustment. |
| **Streaky horizontal lines** | Clean the print head with a cotton swab and isopropyl alcohol. Do not use water. |

---

## Problem 6 — App Keeps Crashing

**Steps to fix:**
1. Force stop: **Android Settings → Apps → Woosoo Print Bridge → Force Stop**.
2. Clear cache (not data): **Apps → Woosoo Print Bridge → Storage → Clear Cache**.
3. Restart the Android device.
4. Reopen the app — settings and registration should still be intact.
5. If the app crashes on launch after reinstalling: check that Android version is 8.0 or higher.

---

## Emergency Workaround — Print Without the Relay App

If the relay device is completely non-functional and service must continue:

1. Log into the admin dashboard at `https://woosoo.local`.
2. Go to **Orders → Live Orders**.
3. Click the affected order → **Print**.
4. Use the browser's print dialog to print from the admin computer's printer as a fallback.

This sends the order to any printer connected to the admin computer (not the thermal printer). Use it only as a temporary measure.

---

## When to Contact Your System Admin

Contact your system admin if:
- The Pi server is down and you cannot SSH in.
- Error codes or stack traces appear in the app that you don't understand.
- Dead letter jobs accumulate faster than you can handle them.
- The app crashes repeatedly after reinstallation.
- The thermal printer fails hardware self-test (hold Feed on boot to run self-test).