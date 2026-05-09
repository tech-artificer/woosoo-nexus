# How to Manage Branches

**Branches** represent multiple physical locations (restaurants, kiosks, franchise units) under one Woosoo system. Each branch has its own menu, devices, and reporting, but shares the same admin dashboard.

---

## What Is Branch Management?

If you operate:
- **Multiple restaurant locations** (Downtown, Airport, Mall locations)
- **Franchise units** (each franchise has its own tablets and kitchen)
- **Central ordering with distributed fulfillment** (orders route to the right location)

Then each physical location is a **Branch** in Woosoo.

**Features per branch:**
- Separate device registration (each location's tablets)
- Independent menu availability (one location can disable items)
- Location-specific reports (sales by location)
- Multi-location consolidated view (see all branches from admin)

---

## How to Access Branches

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Branches** from the left menu (under Configuration)
4. The branches list appears

---

## Understanding the Branches List

### Branch Card Layout

Each branch shows:

| Field | Meaning |
|-------|---------|
| **Branch Name** | Display name (e.g., "Downtown Location", "Airport Kiosk") |
| **Location** | City or address for easy identification |
| **Active Status** | Green (Active) or Gray (Inactive) |
| **Devices** | Count of tablets/relays registered to this branch |
| **Menu Items** | Count of active menu items for this branch |
| **Last Updated** | When branch settings were last modified |

---

## How to Add a New Branch

### Step 1: Click "Add Branch"

1. On the Branches page, click **+ Add Branch** button
2. A form appears with required fields

### Step 2: Enter Branch Details

| Field | Required? | Example |
|-------|-----------|---------|
| **Branch Name** | Yes | "Downtown Restaurant" |
| **Location** | Yes | "123 Main St, Downtown" |
| **Manager Email** | Yes | "manager@restaurant.com" |
| **Phone** | No | "+1 (555) 123-4567" |
| **Timezone** | Yes | "America/New_York" (for local reports) |
| **Currency** | Yes | "USD" |

### Step 3: Configure Branch Settings

| Setting | Purpose |
|---------|---------|
| **Active** | Toggle to enable/disable this branch |
| **POS Terminal Mapping** | If using multi-location POS, map this branch to a Krypton terminal |
| **Email Notifications** | Where to send orders alerts for this location |
| **Order Print Destination** | Which kitchen printer this branch's orders go to |

### Step 4: Save

Click **Create Branch** to finalize.

**Expected result:** New branch appears in the list and is ready for device registration.

---

## How to Edit a Branch

1. Click a branch card from the list
2. Branch details open in an edit panel
3. Modify any field (name, location, settings, timezone)
4. Click **Save**

**Note:** Changing timezone affects how this branch's daily reports are calculated (report day ends at midnight in that timezone).

---

## How to Register Devices to a Branch

Once a branch is created, register its tablets and printers to it:

1. Go to **Devices** menu
2. Click **+ Add Device**
3. Select **Branch** from the dropdown (e.g., "Downtown Restaurant")
4. Choose device type (Tablet or Relay Printer)
5. Continue with device registration steps

**Result:** Device is now associated with this branch and will receive its menu.

---

## Multi-Location Menu Management

### How It Works

1. **Menu** is global — all items are created once in **Menus** section
2. **Branch** controls which items are available at each location
3. **Menu Availability** toggles items on/off per branch (see [Menu Availability guide](menu-availability.md))

### Example Workflow

- **Item:** "Lobster Tail" (expensive, available only at premium locations)
- **Downtown Branch:** Enabled ✅
- **Airport Kiosk Branch:** Disabled ❌
- **Result:** Guests at Airport Kiosk see no Lobster Tail option

---

## Multi-Location Reporting

### View Reports by Branch

1. Go to **Reports** → choose a report (Daily Sales, Hourly Sales, etc.)
2. Click the **Branch Filter** dropdown
3. Select one branch or "All Branches"
4. Report data refreshes for that branch

### Example

- **Downtown Location Daily Sales:** $5,200 (from 120 orders)
- **Airport Kiosk Daily Sales:** $1,800 (from 45 orders)
- **Total:** $7,000

---

## Common Branch Scenarios

### Scenario: Opening a new location

**Steps:**
1. Create a new branch: **Branches → + Add Branch**
2. Enter name, location, manager email
3. Configure timezone and currency
4. Save
5. Go to **Menu → Availability**, select new branch, enable items for this location
6. Register tablets/relays to this branch: **Devices → + Add Device**
7. Tablets will download the menu automatically

---

### Scenario: Temporarily closing a location

**Solution:**
1. Click the branch in the Branches list
2. Toggle **Active: OFF**
3. Save

**Result:**
- This branch's menu stops syncing to devices
- Orders cannot be placed at this location
- Historical reports still show this branch (for archive)

---

### Scenario: Merging two locations into one

**Problem:** Two branch entries for the same physical location need to consolidate.

**Solution:**
1. Manually move all devices from "Old Branch" to "New Branch" (**Devices** page, edit each device)
2. Update Menu Availability for "New Branch" if needed
3. Deactivate "Old Branch" (toggle Active: OFF)
4. Do NOT delete "Old Branch" — keep it for historical reporting

---

## Branch Settings Detail

### Settings Panel (Advanced)

Each branch has a **Settings** button to configure:

| Setting | Impact |
|---------|--------|
| **Items Per Page** | Pagination size for this branch's tablet menu |
| **Email Notifications** | Enable/disable alerts to manager email |
| **Order Alerts** | Manager receives email when orders arrive |
| **Sound Alerts** | Browser (admin panel) plays a ding when orders arrive at this branch |
| **POS System** | "Krypton" or "Custom" — which POS this branch syncs with |
| **API Base URL** | Advanced — custom API endpoint for this branch (usually not needed) |
| **WebSocket URL** | Advanced — custom WebSocket endpoint (usually not needed) |

---

## Troubleshooting

**Problem:** A newly registered device doesn't show the menu.

**Diagnosis:**
1. Verify the device is registered to the correct branch
2. Verify the branch has menu items enabled (**Menu Availability** page)
3. Check tablet WebSocket connection (**Monitoring** page)

**Solution:**
- On the tablet, go to Settings and manually refresh the menu (pull down or restart app)
- Restart the tablet app
- Check server logs: `tail -f /var/log/woosoo/reverb.log`

---

**Problem:** Reports for a branch show $0 sales even though orders were placed.

**Diagnosis:**
1. Check if orders exist in **Orders** page — filter by branch
2. Check if orders have "Completed" status
3. Check if branch timezone is correct (might be grouping orders into wrong date)

**Solution:**
- Verify order status is "Completed" (not "Pending" or "Failed")
- Check branch timezone matches physical location timezone
- Manually review individual orders: **Orders** page → click branch filter

---

## Best Practices

✅ **Name branches by physical location** — e.g., "Downtown", "Airport", "Mall Food Court"

✅ **Set correct timezone** — ensures daily reports match location's local date

✅ **Assign a manager per branch** — use manager email for location-specific alerts

✅ **Test menu availability** — when launching a new branch, verify guests see correct items on tablet

✅ **Monitor branch-specific reports** — check branch sales daily to spot anomalies

---

## Next Steps

- [Menu Availability](menu-availability.md) — control which items show per branch
- [Reports Guide](reports.md) — analyze sales by branch
- [Monitoring](monitoring.md) — ensure branch devices stay connected
