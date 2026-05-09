# How to Monitor System Health

The **Monitoring** dashboard shows real-time status of all critical system components: server, database, WebSocket, printer network, and Pi resources. Use this to catch and troubleshoot issues before they affect operations.

---

## Why Monitoring Matters

If monitoring shows a problem, you can fix it **before** guests encounter issues:

| Component | If Down... |
|-----------|-----------|
| **Web Server** | Admin panel shows "500 error" |
| **Database** | Orders can't be saved, tablets get stuck |
| **WebSocket (Reverb)** | Real-time updates stop, printers don't receive orders |
| **Printer Network** | Bluetooth tablets can't find printers |
| **Disk Space** | System crashes when disk fills up |
| **Pi Server CPU** | System becomes slow and unresponsive |

---

## How to Access Monitoring

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Monitoring** from the left menu (under Configuration)
4. The monitoring dashboard appears with live status indicators

---

## Understanding Monitoring Status Indicators

### Status Colors

| Color | Meaning | Action |
|-------|---------|--------|
| 🟢 **Green** | Healthy | No action needed |
| 🟡 **Yellow** | Warning (degraded) | Monitor closely, fix soon |
| 🔴 **Red** | Critical (down) | Fix immediately |

---

## System Components Dashboard

### Web Server (PHP-FPM)

**Shows:** Health of the admin panel and API server.

- **Status:** Green = responding to requests, Red = not responding
- **Uptime:** How long since last restart
- **Active Workers:** Number of PHP processes handling requests

**If Red:**
1. Go to Pi terminal: `ssh woosoo@woosoo-server.local`
2. Check status: `sudo systemctl status php8.4-fpm`
3. Restart: `sudo systemctl restart php8.4-fpm`

---

### Database (MariaDB)

**Shows:** Connection to the main application database.

- **Status:** Green = connected, Red = unreachable
- **Connection Count:** How many active connections
- **Query Performance:** Slow if >1 sec

**If Red:**
1. Check if MariaDB is running: `sudo systemctl status mariadb`
2. Restart: `sudo systemctl restart mariadb`
3. Check disk space: `df -h` — if full, delete old logs/backups

---

### Krypton Connection (POS)

**Shows:** Connection to legacy POS system (if configured).

- **Status:** Green = synced, Yellow = lagging, Red = disconnected
- **Last Sync:** When the last POS data was pulled
- **Pending Orders:** How many orders waiting to sync to POS

**If Yellow/Red:**
1. Check POS server is running (outside Woosoo — contact POS admin)
2. Verify network between Pi and POS server: `ping <POS-IP>`
3. Check endpoint in `.env`: `POS_API_URL`

---

### WebSocket Service (Reverb)

**Shows:** Real-time communication channel (used for live order updates, device connections).

- **Status:** Green = running, Red = down
- **Connected Clients:** Tablets and printers currently connected
- **Messages/sec:** Data flow rate (should be active during service)

**If Red:**
1. Restart: `sudo supervisorctl restart laravel-reverb`
2. Check logs: `tail -f /var/log/woosoo/reverb.log`
3. Verify port 6001 is open: `sudo netstat -tulpn | grep 6001`

---

### Queue Worker

**Shows:** Background job processor (prints, broadcasts, backups).

- **Status:** Green = processing jobs, Yellow = slow, Red = stuck
- **Jobs Processed:** Count of completed jobs
- **Pending Jobs:** How many jobs waiting

**If Yellow/Red:**
1. Restart: `sudo supervisorctl restart laravel-queue`
2. Check logs: `tail -f /var/log/woosoo/queue.log`
3. If stuck, force restart: `sudo supervisorctl reread && sudo supervisorctl update`

---

## Resource Usage Indicators

### CPU Usage

- **Healthy:** <50% most of the time
- **Warning:** 50-75% (degraded but functional)
- **Critical:** >75% (system struggling, may freeze)

**If Critical:**
1. Check what's using CPU: `top` or `htop` on Pi
2. Restart resource-heavy services: `sudo supervisorctl restart all`
3. Check if a runaway process exists: kill it manually

---

### Memory (RAM) Usage

- **Healthy:** <60% (Pi has 8GB RAM)
- **Warning:** 60-80%
- **Critical:** >80% (system may crash)

**If Critical:**
1. Clear cache: `sudo -u www-data php /srv/woosoo/nexus/artisan cache:clear`
2. Restart services: `sudo supervisorctl restart all`
3. Reboot Pi if persistent: `sudo reboot`

---

### Disk Usage

- **Healthy:** <70% full
- **Warning:** 70-85% full
- **Critical:** >85% full (system may crash)

**If Critical:**
1. Identify large files: `du -sh /* | sort -hr | head -10`
2. Delete old backups: `find /srv/woosoo/backups -name 'woosoo-*.sql.gz' -mtime +7 -delete` (removes files older than 7 days)
3. Clear old logs: `sudo truncate -s 0 /var/log/woosoo/*.log`

---

## Device Status Panel

### Registered Tablets

**Shows:** All tablet devices and their connection status.

| Device Name | Status | Last Seen | Branch |
|-------------|--------|-----------|--------|
| Tablet 1 | 🟢 Connected | 2 min ago | Downtown |
| Tablet 2 | 🟡 Idle | 30 min ago | Downtown |
| Kiosk A | 🔴 Offline | 2 hours ago | Airport |

**If Red (Offline):**
1. Physically check the tablet (is it powered on?)
2. Check WiFi connection (WiFi icon in tablet status bar)
3. If WiFi is on but offline here, restart the tablet app

---

### Registered Printers (Relays)

**Shows:** All Bluetooth printer devices and their connection status.

| Device Name | Status | Printer | Last Seen |
|-------------|--------|---------|-----------|
| Relay 1 (Kitchen) | 🟢 Connected | Zebra TLP2844 | 1 min ago |
| Relay 2 (Bar) | 🟡 Idle | Brother QL-1110 | 45 min ago |

**If Red (Offline):**
1. Check if relay device (Android tablet) is powered on
2. Check if Bluetooth printer is powered on and in range
3. Restart the Print Bridge app on the relay device

---

## Network Status

### WiFi SSID & Strength

**Shows:** The WiFi network the Pi is broadcasting.

- **SSID:** "woosoo" or custom name
- **Strength:** Signal quality (should be 80%+)
- **Channel:** WiFi channel (2.4 GHz or 5 GHz)

**If unstable:**
1. Check for interference: move Pi away from microwave, metal objects
2. Switch WiFi channel: contact IT admin

---

### DNS Resolution

**Shows:** Whether `woosoo.local` resolves correctly.

- **Status:** Green = resolves, Red = DNS failing
- **Resolved IP:** Should be `192.168.100.42` (or your Pi IP)

**If Red:**
1. On a tablet, check WiFi DNS settings — should be set to Pi IP
2. On Pi, check dnsmasq: `sudo systemctl status dnsmasq`
3. Restart: `sudo systemctl restart dnsmasq`

---

## Alerts & Notifications

### Alert Types

| Alert | Trigger | Action |
|-------|---------|--------|
| **Component Down** | Service stops responding | Restart service or check hardware |
| **High CPU** | CPU >80% for 5+ min | Investigate runaway process |
| **Low Disk** | Disk >85% full | Delete old data |
| **Slow Response** | API >2 sec | Optimize queries or restart services |

### Alert Channels

Alerts can be sent to:
- 📧 Email (to admin address)
- 🔔 Browser notification (in admin panel)
- 📱 SMS (if configured)

---

## Best Practices

✅ **Check monitoring daily** — spend 1 minute reviewing status every morning.

✅ **Act on yellow alerts quickly** — don't wait for red to fix it.

✅ **Monitor during peak hours** — CPU/memory should spike but recover after rush.

✅ **Document issues** — if a component frequently goes yellow, investigate the root cause.

✅ **Keep backups** — disk full is recoverable if you have backups.

---

## Common Monitoring Scenarios

### Scenario: Red alert appears during lunch rush

**Problem:** WebSocket service went down, tablets are not updating orders in real-time.

**Immediate fix:**
1. Restart Reverb: `sudo supervisorctl restart laravel-reverb`
2. Wait 10 seconds, refresh monitoring page
3. Should turn green

**Root cause analysis:**
1. Check logs: `tail -n 30 /var/log/woosoo/reverb.log`
2. Look for memory exhaustion or crash
3. Increase Reverb workers if needed (config in `.env`)

---

### Scenario: Disk space is at 88%, showing critical

**Problem:** System may crash when disk is completely full.

**Immediate fix:**
1. Delete old backups: `ls -lh /srv/woosoo/backups/ | tail -n 5` (keep 5 most recent)
2. Remove old backup: `rm /srv/woosoo/backups/woosoo-2026-03-01.sql.gz`
3. Check disk again: `df -h /srv`
4. Should show more available space

**Root cause analysis:**
1. Set up automatic backup cleanup: backups older than 7 days delete automatically (configured in cron)
2. Monitor disk weekly

---

### Scenario: A printer shows offline but it's actually on

**Problem:** Relay device lost connection but monitoring hasn't updated.

**Solution:**
1. On the relay device, go to **Operational Tools → Connect Printer**
2. Re-establish connection
3. Monitoring should turn green within 1 minute
4. If not, restart relay app

---

## Troubleshooting

**Problem:** Monitoring page itself is not loading (shows "Cannot reach server").

**Diagnosis:**
1. Web server is down

**Solution:**
1. SSH to Pi: `ssh woosoo@woosoo-server.local`
2. Check PHP-FPM: `sudo systemctl status php8.4-fpm`
3. Restart: `sudo systemctl restart php8.4-fpm`
4. Retry monitoring page

---

## Next Steps

- [Troubleshooting Guide](../admin/troubleshoot.md) — deep dive on specific issues
- [Event Logs Guide](event-logs.md) — see detailed history of component health changes
- [Pi Commands](../pi-commands.md) — manual commands for advanced diagnostics
