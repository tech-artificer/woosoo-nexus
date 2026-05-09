# How to Manage Reverb Service (WebSocket)

**Reverb** is the real-time messaging service that keeps your tablets and printers connected to the admin dashboard. This guide explains what Reverb is, how to monitor it, and how to troubleshoot connection issues.

---

## What Is Reverb?

Reverb is a **WebSocket server** that enables live, two-way communication:

**Tablets ↔ Reverb ↔ Admin Dashboard**

| Event | Flow |
|-------|------|
| **Order placed on tablet** | Tablet → Reverb → Admin sees it instantly |
| **Admin voids an order** | Admin → Reverb → Printer stops printing |
| **Printer goes offline** | Printer → Reverb → Admin sees red status |
| **Guest calls for staff** | Tablet → Reverb → Admin gets notification |

**Without Reverb:** Updates would only appear after refreshing the page (bad user experience).

**With Reverb:** Everything updates in real-time (professional experience).

---

## How to Access Reverb Service

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Reverb Service** from the left menu (under Configuration)
4. The Reverb status page appears

---

## Understanding Reverb Dashboard

### Connection Status

| Status | Meaning | Action |
|--------|---------|--------|
| 🟢 **Connected** | WebSocket server is running and accepting connections | None needed |
| 🟡 **Degraded** | Server running but experiencing high latency | Monitor, may restart if persistent |
| 🔴 **Offline** | Server not responding | Restart immediately |

### Live Statistics

| Stat | Meaning |
|------|---------|
| **Active Connections** | Number of tablets/printers connected right now |
| **Messages/sec** | Data flow rate (should spike during orders) |
| **Uptime** | How long since last restart |
| **Server Port** | Docker/compose: 8080; local/non-Docker: 6001 |

---

## Reverb Features

### Broadcasting Channels

Reverb uses "channels" to organize real-time data:

| Channel | Who Uses It | What It Broadcasts |
|---------|-----------|---|
| `orders.{branch}` | Tablets & Admin | New orders, status updates |
| `devices.{branch}` | Admin & Relays | Device online/offline events |
| `menus.{branch}` | Admin & Tablets | Menu changes (live availability) |
| `print.{branch}` | Admin & Relays | Print jobs to specific printers |
| `notifications` | Admin | Service requests, alerts |

### Example

When a tablet places an order:
1. **Tablet** sends data to the `/api/v1/device-orders` endpoint
2. **Server** creates the order and broadcasts to `orders.downtown` channel
3. **Admin dashboard** receives broadcast instantly
4. **Kitchen printer** (also listening to `print.downtown`) receives print job
5. All happens in <200ms

---

## Monitoring Reverb Health

### From the Reverb Service Page

1. **Connected Status** should show green 🟢
2. **Active Connections** should match:
   - # of active tablets + # of active relay printers + admin users logged in
   - During lunch rush: might be 5 tablets + 2 printers + 3 admins = 10 connections

### From the Monitoring Page

1. Go to **Monitoring** (under Configuration)
2. Scroll to **WebSocket Service (Reverb)**
3. Status should be green
4. If yellow/red, see troubleshooting below

---

## Common Reverb Scenarios

### Scenario: Reverb is offline (red status)

**Signs:**
- Orders on tablets don't appear in admin instantly
- Printer doesn't receive jobs automatically
- Admin panel shows "Connection lost" banner

**Immediate Fix:**

1. Go to **Reverb Service** page
2. Click **Restart Reverb** button
3. Wait 5 seconds
4. Status should turn green 🟢

**If restart button not visible:**

Use terminal on Pi:

```bash
sudo supervisorctl restart laravel-reverb
```

Expected output:
```text
laravel-reverb: stopped
laravel-reverb: started
```

**Verify fix:**
1. Refresh Reverb Service page
2. Status should now show green and "Connected"

---

### Scenario: Reverb is showing yellow (degraded)

**Signs:**
- Real-time updates are slow (10-30 second delay)
- Admin sees stale data

**Diagnosis:**

1. Check **Active Connections** count — if unusually high (>50), Reverb might be overloaded
2. Check **Messages/sec** — if very high, network might be congested

**Solution:**

**Option 1:** Graceful restart (wait for current jobs to finish)

```bash
sudo supervisorctl restart laravel-reverb
```

**Option 2:** Check for memory issues

```bash
free -h
```

If memory is >80% full:
1. Restart all workers: `sudo supervisorctl restart all`
2. Clear cache: `sudo -u www-data php /srv/woosoo/nexus/artisan cache:clear`

---

### Scenario: Tablets are not receiving menu updates

**Problem:** A menu item's availability was toggled in Admin, but tablets still show the old menu.

**Cause:** Reverb didn't broadcast the change (could be network issue or old app version).

**Solution:**

1. **In Admin:** Go to **Menu Availability** and toggle the item again (triggers re-broadcast)
2. **On Tablet:** Pull down to refresh (forces a menu re-download)
3. **If still not working:** Restart the tablet app

---

### Scenario: Printers are not receiving print jobs

**Problem:** Orders appear in Admin and on tablets, but kitchen printer doesn't print.

**Cause:** Relay device (printer) is not connected to Reverb, or Reverb is not routing jobs to the right channel.

**Diagnosis:**

1. Check if relay device is online: **Monitoring** → scroll to "Registered Printers" → status should be 🟢
2. Check relay app status: go to the Print Bridge app on the relay device → **Status Screen** → "WebSocket:" should show green

**Solution:**

- If relay shows offline:
  1. Restart relay app
  2. Go to **Operational Tools** → **Restart WebSocket**
  
- If Reverb page shows red:
  1. Restart Reverb: `sudo supervisorctl restart laravel-reverb`

---

## Advanced: Reverb Configuration

### Environment Variables

Reverb is configured in `/etc/woosoo/.env`:

```bash
REVERB_APP_ID=woosoo
REVERB_APP_KEY=<secret-key>
REVERB_HOST=woosoo.local
REVERB_PORT=6001
REVERB_SCHEME=https
```

**If you change these:**

1. Edit the file: `sudo nano /etc/woosoo/.env`
2. Save (Ctrl+X → Y → Enter)
3. Restart Reverb: `sudo supervisorctl restart laravel-reverb`
4. Update relay device Settings to match new REVERB_APP_KEY

---

### View Reverb Logs

```bash
tail -f /var/log/woosoo/reverb.log
```

**Expected log lines (healthy):**

```text
[2026-05-03 14:00:00] INFO: Connection established from 192.168.100.45 (Tablet 1)
[2026-05-03 14:00:01] INFO: Subscription to channel: orders.downtown
[2026-05-03 14:00:15] INFO: Broadcast to orders.downtown: 1 subscribers
```

**Error log lines (problematic):**

```text
[2026-05-03 14:00:30] ERROR: Connection timeout from 192.168.100.50
[2026-05-03 14:00:31] ERROR: Failed to route message to print channel
```

---

## Best Practices

✅ **Check Reverb status daily** — spend 10 seconds viewing the Reverb Service page.

✅ **Monitor during peak hours** — Reverb may struggle if >30 concurrent connections; add another worker if needed.

✅ **Restart weekly** — scheduled restart keeps Reverb fresh (configure in cron if desired).

✅ **Document outages** — if Reverb goes down, note the time and duration for troubleshooting patterns.

---

## Troubleshooting

**Problem:** Reverb Service page shows "Cannot connect to WebSocket".

**Diagnosis:**
1. Reverb server may not be running
2. Network/firewall may be blocking port 6001

**Solution:**

On Pi:
```bash
sudo supervisorctl status laravel-reverb  # Check if running
sudo supervisorctl start laravel-reverb   # Start it
sudo netstat -tulpn | grep 6001  # Verify port is open
```

If port not open, check firewall:
```bash
sudo ufw status
sudo ufw allow 6001
```

---

**Problem:** Reverb restarts frequently (appears in logs many times per hour).

**Diagnosis:**
1. Memory leak in Reverb process
2. Reverb worker exhausting resources

**Solution:**

1. Check Reverb logs: `tail -n 50 /var/log/woosoo/reverb.log`
2. Look for memory errors or "out of memory"
3. If confirmed, increase worker processes or upgrade server RAM

---

## Next Steps

- [Monitoring Guide](monitoring.md) — check Reverb health alongside other components
- [Troubleshooting Guide](../admin/troubleshoot.md) — resolve real-time connectivity issues
- [Tablet Requirements](../tablet/requirements.md) — ensure tablets have correct Reverb configuration
