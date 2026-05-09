# How to View Event Logs

**Event Logs** provide a complete audit trail of every action in the system: who logged in, what they changed, which orders were voided, when devices went offline, and more. Use logs to troubleshoot issues, audit changes, and ensure accountability.

---

## Why Event Logs Matter

Event logs answer critical questions:

- **"Who made this order?"** → Find user and timestamp
- **"When did the printer go offline?"** → Trace connectivity issues
- **"What settings did the manager change?"** → Audit trail for compliance
- **"How many orders failed today?"** → Root cause analysis

---

## How to Access Event Logs

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Event Logs** from the left menu (under Configuration)
4. The events list appears, showing latest events first

---

## Understanding Event Logs

### Event Log Entry Format

Each entry shows:

| Column | Meaning |
|--------|---------|
| **Timestamp** | Date and time the event occurred |
| **User** | Who triggered the action (email or system) |
| **Action** | What was done (e.g. `login`, `login_failed`, `logout`, `order:created`, `order:updated`, `device:offline`) |
| **Resource** | What was acted upon (Order, Device, User, Menu, etc.) |
| **Resource ID** | Specific ID of the item (e.g., order #12345) |
| **Details** | Additional info (before/after values, error messages) |
| **IP Address** | Where the request came from |

### Example Log Entries

```text
2026-05-03 13:45:22 | manager@rest.local | order:updated | Order | #ORD-8901 | Status changed: Pending → Completed | 192.168.1.50
2026-05-03 13:44:55 | system | device:offline | Device | tablet-1 | Connection lost | 192.168.100.42
2026-05-03 13:44:01 | admin@rest.local | user:created | User | #user-234 | New user: bartender@rest.local | 192.168.1.20
2026-05-03 13:43:30 | guest | order:created | Order | #ORD-8900 | 4 items, $65.99 | 192.168.100.45
2026-05-03 13:42:15 | system | backup:completed | Backup | backup-daily | 1.2 GB exported | 192.168.100.42
```

---

## Filtering & Searching Event Logs

### Filter by Action Type

| Action | What Happened |
|--------|---|
| `login` | User signed in |
| `login_failed` | Failed login attempt |
| `logout` | User signed out |
| `order:created` | Order placed |
| `order:updated` | Order edited/voided |
| `order:deleted` | Order deleted |
| `device:registered` | New device registered |
| `device:offline` | Device lost connection |
| `device:online` | Device reconnected |
| `user:created` | New user added |
| `user:updated` | User settings changed |
| `user:deleted` | User removed |
| `menu:updated` | Menu item changed |
| `backup:completed` | Backup finished |
| `backup:failed` | Backup error |
| `sync:completed` | POS sync successful |
| `sync:failed` | POS sync error |

### Filter by Resource Type

Click the **Resource** dropdown to filter by:
- **Order** — all order-related events
- **Device** — tablet/printer events
- **User** — user management events
- **Menu** — menu updates
- **Branch** — branch configuration
- **System** — backup, sync, service events

### Filter by Date Range

1. Click the **Date Range** picker
2. Select:
   - Today
   - Last 7 days
   - Last 30 days
   - Custom range

### Filter by User

1. Click the **User** filter
2. Select a specific user (e.g., "manager@rest.local")
3. See only actions by that user

### Search by Resource ID

1. Click the **Search** box
2. Enter an order ID (e.g., "ORD-8901") or device name (e.g., "Tablet 1")
3. Logs filter to matching events

---

## Common Event Log Scenarios

### Scenario: An order disappeared — find out who deleted it

**Steps:**
1. Open Event Logs
2. Filter Action = `order:deleted`
3. Look for the order ID in the Resource ID column
4. See the User column to identify who deleted it
5. Check Timestamp to know when

**Result:**
```text
2026-05-03 14:22:10 | manager@rest.local | order:deleted | Order | #ORD-8890 | Deleted | 192.168.1.50
```

**Action:** Contact the manager to verify if deletion was intentional.

---

### Scenario: A device keeps going offline — diagnose the pattern

**Steps:**
1. Open Event Logs
2. Filter Action = `device:offline` OR `device:online`
3. Filter Resource = "Device"
4. Filter Device Name = "Tablet 1"
5. Set date range to "Last 7 days"
6. Look at the pattern

**Example result:**
```text
2026-05-03 15:30:45 | system | device:offline | Device | Tablet-1 | Connection lost | 192.168.100.42
2026-05-03 13:22:10 | system | device:online | Device | Tablet-1 | Reconnected | 192.168.100.42
2026-05-02 15:15:33 | system | device:offline | Device | Tablet-1 | Connection lost | 192.168.100.42
2026-05-02 13:05:22 | system | device:online | Device | Tablet-1 | Reconnected | 192.168.100.42
```

**Analysis:** Device goes offline at ~3:30 PM every day. Could be:
- WiFi interference during afternoon peak
- Battery saving mode kicking in
- Tablet overheating

**Action:** Move tablet away from interference or restart daily maintenance.

---

### Scenario: Failed login attempts — security check

**Steps:**
1. Open Event Logs
2. Filter Action = `login_failed`
3. Set date range to "Last 24 hours"
4. Look for repeated attempts from same IP or user

**Example result (suspicious):**
```text
2026-05-03 23:14:02 | unknown@attacker.net | login_failed | User | N/A | Invalid credentials | 203.0.113.50
2026-05-03 23:14:01 | unknown@attacker.net | login_failed | User | N/A | Invalid credentials | 203.0.113.50
2026-05-03 23:13:59 | unknown@attacker.net | login_failed | User | N/A | Invalid credentials | 203.0.113.50
```

**Action:** Someone is trying to brute-force login. Contact IT to block the IP address.

---

### Scenario: Menu item has wrong price — who changed it?

**Steps:**
1. Open Event Logs
2. Filter Action = `menu:updated`
3. Filter Resource = "Menu"
4. Search for the item name (e.g., "Salmon Fillet")
5. See who updated it and when

**Example result:**
```text
2026-05-03 10:22:15 | manager@rest.local | menu:updated | Menu | Salmon Fillet | Price: $16.99 → $13.99 | 192.168.1.50
```

**Action:** Contact manager to confirm if price change was intentional (could be a sale or accidental edit).

---

### Scenario: POS sync failed — when and how often?

**Steps:**
1. Open Event Logs
2. Filter Action = `sync:failed`
3. Set date range to "Last 30 days"
4. Count how many failures and when they occur

**Example result:**
```text
2026-05-03 14:05:33 | system | sync:failed | Krypton | sync-job-#2847 | Connection timeout | 192.168.100.42
2026-05-01 02:05:12 | system | sync:failed | Krypton | sync-job-#2801 | Invalid auth token | 192.168.100.42
2026-04-28 14:05:44 | system | sync:failed | Krypton | sync-job-#2715 | Database locked | 192.168.100.42
```

**Analysis:** 3 failures in 30 days. If all happen at off-hours, not urgent. If happening during service, investigate.

**Action:** Check Monitoring page for Krypton connection status.

---

## Export Event Logs

### Export to CSV

1. Open Event Logs with desired filters
2. Click **Export** button
3. Choose **CSV**
4. File downloads (e.g., `event-logs-2026-05-03.csv`)
5. Open in Excel for detailed analysis

---

## Retention Policy

**Event logs are kept for:**
- 90 days by default (configurable)
- Older logs are automatically deleted
- Critical events (security, backups) may be archived longer

**To export logs for long-term archival:**
1. Export to CSV monthly
2. Store in secure backup location

---

## Best Practices

✅ **Review logs weekly** — check for anomalies on Monday morning.

✅ **Act on red flags** — repeated failed logins, sync failures, or device disconnections indicate problems.

✅ **Correlate with issues** — if orders failed, check logs to see what system errors occurred.

✅ **Archive important logs** — export and keep logs related to major incidents or changes.

✅ **Audit access** — for compliance, periodically review who accessed sensitive features.

---

## Troubleshooting

**Problem:** Event Logs page shows "No events found" even though events should exist.

**Diagnosis:**
1. Check if date range is correct (may have selected future dates)
2. Check if filters are too restrictive

**Solution:**
- Clear all filters: click "Reset"
- Set date range to "Last 30 days"
- Should show events

---

**Problem:** Export button is grayed out.

**Diagnosis:**
1. No results to export (date range has no events)
2. User may lack export permission

**Solution:**
- Broaden the date range
- Contact admin to verify permissions

---

## Next Steps

- [Monitoring Guide](monitoring.md) — view real-time system health
- [Access Control Guide](access-control.md) — manage who can access logs
- [Troubleshooting Guide](../admin/troubleshoot.md) — use logs to diagnose specific issues
