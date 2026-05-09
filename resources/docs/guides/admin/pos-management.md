# How to Manage the POS System

The **POS (Point of Sale) dashboard** shows live integration with your Krypton legacy system, displaying real-time order data, terminal status, and transaction history.

---

## What Is POS Management?

The POS page displays:
- **Terminal Status**: Active checkout terminals and their last transaction
- **Transaction History**: All sales from the connected POS system
- **Real-time Sync**: Orders placed on tablets automatically reflect in this view
- **Read-only View**: The Woosoo system reads from Krypton; this is for monitoring only

---

## How to Access POS Management

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with your admin credentials
3. Click **POS** from the left menu
4. The POS dashboard appears, showing live terminal data

---

## Understanding the POS Dashboard

### Terminals Section

| Column | Meaning |
|--------|---------|
| **Terminal ID** | Krypton terminal number (e.g., POI-1, POI-2) |
| **Last Sale** | Time and total of the most recent transaction |
| **Status** | Online/Offline — whether the terminal is currently active |
| **Transactions Today** | Count of sales processed by this terminal |

### Transaction History

Each transaction shows:
- **Date & Time**: When the sale was recorded in Krypton
- **Terminal**: Which POS terminal processed it
- **Total**: Sale amount (in local currency)
- **Items**: Number of menu items in the transaction
- **Source**: Marked as "POS" (from Krypton) or "Tablet" (from Woosoo Nexus)

---

## Real-time Sync: Tablet Orders → POS

When a guest completes an order on the tablet:

1. **Order Created** in Woosoo Nexus → immediately sent to Krypton POS
2. **Marked in Krypton** as a "Tablet Order" or "Woosoo Order"
3. **Appears Here** in the POS Transaction History within 1-2 seconds
4. **Printed** to the kitchen printer (via Woosoo Print Bridge)

**Why this matters:**
- Your POS system stays authoritative and synced with tablet sales
- All revenue (tablet + terminal) flows through Krypton
- Reports in Krypton include tablet orders automatically

---

## Common POS Scenarios

### Scenario: Orders not appearing in POS

**Problem:** An order was placed on the tablet, but it's not showing in the POS history.

**Diagnosis:**
1. Check the tablet order in **Woosoo Nexus → Orders** — verify it shows status "Completed"
2. Check **Woosoo Nexus → Monitoring** to see if the POS sync is healthy (Krypton connection should show green)
3. Check the **Event Logs** (`https://woosoo.local/event-logs`) for sync errors

**Solution:**
- If tablet order is "Pending" → manually complete it in Nexus → Orders
- If POS sync shows red → restart the sync service: `sudo supervisorctl restart laravel-queue`
- If still failing → contact your Krypton admin to verify the POS API connection

---

### Scenario: Terminal offline on POS page

**Problem:** A terminal shows "Offline" on the POS dashboard, but it's physically powered on.

**Diagnosis:**
1. Log into the Krypton POS system directly (not via Woosoo)
2. Check if the terminal is marked as offline there too
3. If offline in Krypton → it's a POS network issue, not Woosoo

**Solution:**
- Power cycle the terminal and wait 30 seconds
- If still offline, check network connectivity to the Krypton server
- Contact your Krypton admin

---

## Monitoring & Troubleshooting

**Check the Krypton Connection:**

1. Go to **Monitoring** → scroll to "Krypton Connection"
2. Status should show **green** (Connected)
3. If **yellow** or **red** → [see Monitoring guide](monitoring.md)

**View Sync Logs:**

On the Pi, check for POS sync errors:

```bash
tail -f /var/log/woosoo/queue.log | grep -i krypton
```

**Expected output:**
```
[INFO] Krypton sync job processed 0 failed orders
[INFO] Terminal status updated from Krypton
```

**Restart the Queue Worker (if sync is stuck):**

```bash
sudo supervisorctl restart laravel-queue
```

---

## Tips

✅ **Best Practice:** Check this page every 2-3 hours during service to confirm orders are syncing to Krypton.

✅ **At Close:** Reconcile tablet sales here against your Krypton end-of-day report — both should total the same.

⚠️ **Important:** This is a read-only view. To void or adjust a transaction, you must do it in the Krypton POS system, not here.

---

## Next Steps

- [Manage Orders](manage-orders.md) — handle tablet orders specifically
- [Monitoring Guide](monitoring.md) — check system health
- [Event Logs](event-logs.md) — audit all sync activities
