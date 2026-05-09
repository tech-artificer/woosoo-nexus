# How to Check Connection Status

During service, the Print Bridge app must maintain two live connections: one to the Woosoo Nexus server (via WebSocket) and one to the thermal printer (via Bluetooth). This guide explains how to read the status indicators and verify everything is working.

---

## Status Screen — Main Indicators

Open the app and navigate to the **Status** screen (first tab / home screen).

| Indicator | Green (🟢) | Yellow (🟡) | Red (🔴) |
|-----------|-----------|------------|---------|
| **Server** | Connected and receiving events | Reconnecting | Offline — cannot receive print jobs |
| **Printer** | Connected and ready | Paired but not active | Not paired or disconnected |
| **Queue** | Empty — no pending jobs | Jobs waiting to print | Jobs failing repeatedly |

All three indicators should be green during normal service.

---

## What Each Screen Tells You

### Status Screen
- **Device name** and last-4 of registration code
- **Server connection** with last heartbeat timestamp
- **Printer connection** with device name (e.g., `Xprinter-40`)
- **Jobs today** — count of successful prints this session
- **Uptime** — how long the app has been running

### Queue Screen
- Shows print jobs that are waiting to be sent to the printer.
- Each job shows: Order ID, table number, timestamp queued, status.
- A healthy queue is empty or clears within seconds.
- If jobs are accumulating: check printer connection.

### Metrics Screen
- **Jobs per hour** — average throughput
- **Success rate** — percentage of jobs that printed on first attempt
- **Average latency** — time from server receiving job to printer receiving it
- **Retry count** — how many jobs needed retrying

### Logs Screen
- Raw application event log.
- Filter by level: INFO, WARNING, ERROR.
- Useful for diagnosing intermittent issues.
- ERROR entries appear in red — investigate these if printing is unreliable.

### Orders History Screen
- Complete list of all print jobs received this session.
- Each entry shows: order number, table, timestamp, print status (Success / Failed).
- Tap any entry to see the full job details and a **Reprint** button.

### Dead Letter Screen
- Print jobs that **failed all retry attempts** are moved here.
- Each dead letter shows: order ID, failure reason, timestamp.
- To recover: tap the job and tap **Reprint** — the job is sent to the printer again.
- If the same jobs keep failing, check the printer connection and paper supply.

---

## Start-of-Shift Checklist

Run through these checks at the beginning of each service:

1. **Status screen** — all indicators green.
2. **Queue screen** — empty (no stuck jobs from previous session).
3. **Dead Letter screen** — empty or no new failures.
4. **Tools → Test Print** — prints a test receipt successfully.
5. Verify the **physical printer** has enough paper loaded.

---

## During Service — What to Watch

- Glance at the **Status screen** after the first order — the server indicator should remain green.
- If the printer indicator goes yellow or red mid-service, check the Bluetooth connection (printer may have been moved too far away or powered off).
- If orders are not printing, check the **Queue** screen immediately — jobs should be there if the server connection is fine.

---

## Checking Status from the Admin Dashboard

The admin can also check relay device status:
1. Log into the admin dashboard at `https://woosoo.local`.
2. Go to **Monitoring** in the sidebar.
3. The Monitoring page shows:
   - Queue pending and failed counts
   - Print events (successful and failed)
4. Go to **Devices** to see the relay's last seen timestamp and connection status.