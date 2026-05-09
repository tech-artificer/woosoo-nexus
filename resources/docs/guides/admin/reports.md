# How to View Reports & Analytics

The **Reports** dashboard provides real-time insights into sales, guest counts, menu performance, print history, and more. Use reports to make data-driven decisions about menu, staffing, and inventory.

---

## What Are Reports?

Reports aggregate order and transaction data into visualizations:

| Report | Shows |
|--------|-------|
| **Overview** | Key metrics dashboard (total sales, orders, guests today) |
| **Daily Sales** | Sales broken down by date (shows trends over time) |
| **Hourly Sales** | Sales by hour (identify peak hours) |
| **Guest Count** | Number of guests served per day/hour |
| **Menu Items** | Which menu items sold best, revenue per item |
| **Order Status** | How many orders completed, failed, or were voided |
| **Print Audit** | All print events (what was printed, when, to which printer) |
| **Discount & Tax** | Breakdown of discounts applied and tax collected |

---

## How to Access Reports

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Reports** from the left menu
4. Select a specific report from the submenu:
   - Overview
   - Daily Sales
   - Hourly Sales
   - Guest Count
   - Menu Items
   - Order Status
   - Print Audit
   - Discount & Tax

---

## Report Navigation & Filters

### Common Controls (all reports)

| Control | Purpose |
|---------|---------|
| **Date Range Picker** | Select start and end dates for the report |
| **Branch Filter** | Choose one branch or "All Branches" |
| **Export Button** | Download data as CSV or PDF |
| **Refresh** | Re-fetch latest data |

### Example Workflow

1. Open **Daily Sales** report
2. Click date picker, select "Last 7 days"
3. Click Branch dropdown, select "Downtown Location"
4. Report updates to show only Downtown's last 7 days of sales
5. Click **Export** to save as CSV

---

## Understanding Each Report

### Overview Report

**Dashboard view showing:**
- **Total Sales Today:** $3,450
- **Orders Completed:** 127
- **Guests Served:** 342
- **Average Order Value:** $27.17
- **Peak Hour:** 12:00 PM - 1:00 PM
- **Top Item:** Salmon Fillet (34 sold)

**Use for:** Quick pulse check. Open this first thing in the morning to set expectations for the day.

---

### Daily Sales Report

**Chart & table showing:**
- **Date** | **Total Sales** | **Orders** | **Avg Order Value** | **Discount %**
- May 1 | $4,200 | 145 | $28.97 | 2.1%
- May 2 | $3,800 | 132 | $28.79 | 1.8%
- May 3 | $5,100 | 176 | $28.98 | 2.3%

**Use for:**
- Identify sales trends (which days are busiest?)
- Compare performance week-over-week
- Detect anomalies (if May 2 is unusually low, investigate why)

---

### Hourly Sales Report

**Breakdown showing:**
- **Time Slot** | **Sales** | **Orders** | **Avg Check**
- 11:00 AM | $450 | 18 | $25
- 12:00 PM | $980 | 38 | $25.79 (peak)
- 1:00 PM | $720 | 28 | $25.71
- 2:00 PM | $240 | 9 | $26.67

**Use for:**
- Identify peak hours (when to staff up)
- Identify slow hours (opportunities for specials)
- Adjust kitchen prep based on expected volume

---

### Guest Count Report

**Shows:**
- **Date** | **Total Guests** | **Avg Guests per Order** | **Avg Guest Spend**
- May 1 | 342 | 2.36 | $12.27
- May 2 | 305 | 2.31 | $12.46
- May 3 | 410 | 2.33 | $12.44

**Use for:**
- Plan staffing (more guests = more servers needed)
- Analyze group size trends
- Forecast revenue based on expected guests

---

### Menu Items Report

**Detailed breakdown:**
- **Item Name** | **Qty Sold** | **Revenue** | **Avg Price** | **% of Total Sales**
- Salmon Fillet | 89 | $1,245 | $13.99 | 18.2%
- Chicken Caesar Wrap | 76 | $608 | $8.00 | 8.9%
- House Salad | 102 | $714 | $7.00 | 10.4%

**Use for:**
- Identify bestsellers (keep well-stocked, feature on specials)
- Identify underperformers (consider removing or re-pricing)
- Plan inventory (high-selling items need more prep)
- Cross-sell analysis (which items often appear together?)

---

### Order Status Report

**Pie chart & table:**
- **Completed:** 145 orders (92%)
- **Voided:** 10 orders (6%)
- **Failed:** 2 orders (1%)
- **Pending:** 1 order (0.6%)

**Use for:**
- Monitor order success rate (aim for 95%+)
- Investigate failures (if >5%, check if printer issues or staff voiding orders incorrectly)
- Track voided orders (legitimate voids vs. mistakes)

---

### Print Audit Report

**Complete log of all print events:**
- **Time** | **Printer** | **Order ID** | **Items** | **Status**
- 12:34 PM | Kitchen Printer 1 | ORD-0542 | 3 items | Printed ✅
- 12:35 PM | Bar Printer | ORD-0543 | 2 items | Failed ❌
- 12:36 PM | Kitchen Printer 1 | ORD-0544 | 4 items | Printed ✅

**Use for:**
- Audit trail (proof that order went to kitchen)
- Troubleshoot print failures
- Identify problematic printers (if one printer has many failures, it may need attention)

---

### Discount & Tax Report

**Breakdown of financial items:**
- **Total Discounts Applied:** $189.50
- **Discount %:** 2.7% of total sales
- **Total Tax Collected:** $418.42
- **Effective Tax Rate:** 6.0% (expected 6%, so compliant)
- **Discount by Type:** 
  - Promotional discount (30% off): $120
  - Staff meal discount: $69.50

**Use for:**
- Verify tax calculations for compliance
- Track discount spend (ensure it's within budget)
- Identify which discounts are used most

---

## Using Filters & Date Ranges

### Date Range Options

| Option | Period |
|--------|--------|
| **Today** | Last 24 hours |
| **Yesterday** | Previous day |
| **This Week** | Monday to today |
| **Last 7 Days** | Past 7 calendar days |
| **This Month** | 1st to today |
| **Last 30 Days** | Past 30 days |
| **Custom** | Pick start and end dates manually |

### Branch Filtering

All reports can be filtered by branch:

1. Click **Branch** dropdown
2. Select a specific branch (e.g., "Downtown") or "All Branches"
3. Report updates automatically

---

## Exporting Reports

### Export to CSV

1. Open any report
2. Click **Export** button
3. Choose **CSV**
4. File downloads (e.g., `daily-sales-2026-05-03.csv`)
5. Open in Excel or Google Sheets for further analysis

### Export to PDF

1. Click **Export** button
2. Choose **PDF**
3. Report prints as a professional document (charts included)
4. Save or email to stakeholders

---

## Common Reporting Scenarios

### Scenario: Analyze weekend vs. weekday performance

**Steps:**
1. Open **Daily Sales** report
2. Set date range to "Last 30 Days"
3. Manually inspect the data:
   - Fridays/Saturdays/Sundays (weekend)
   - Monday-Thursday (weekday)
4. Calculate average sales for each group
5. Compare and identify patterns

---

### Scenario: Find out which menu items to promote

**Steps:**
1. Open **Menu Items** report
2. Sort by **Qty Sold** (descending)
3. Top 5 items are bestsellers — feature these on specials
4. Bottom 5 items are underperforming — consider if they need better placement, lower price, or removal

---

### Scenario: Troubleshoot a low-sales day

**Steps:**
1. Open **Daily Sales** — spot the anomalous date
2. Open **Hourly Sales** — filter to that date — which hours were slowest?
3. Open **Order Status** — did many orders fail? Could indicate system issue
4. Open **Guest Count** — were fewer guests served? Indicates customer volume issue, not operational issue
5. Check manually: Was there a competitor event, was staff short, did equipment fail?

---

## Best Practices

✅ **Review reports daily** — check the Overview report before each shift.

✅ **Identify trends** — track weekly/monthly patterns, not just daily numbers.

✅ **Set goals** — use historical data to set realistic revenue targets.

✅ **Export and share** — send weekly reports to management or ownership.

✅ **Act on insights** — if a menu item underperforms, don't ignore it — investigate and fix.

✅ **Cross-reference data** — if Daily Sales is high but Guest Count is low, average check size increased (good pricing strategy).

---

## Troubleshooting

**Problem:** Report shows blank data or "No data available".

**Diagnosis:**
1. Check the date range — ensure it includes orders
2. Check if branch filter excludes all locations
3. Verify orders were actually placed and completed

**Solution:**
- Expand the date range to "Last 30 Days"
- Switch branch to "All Branches"
- Place a test order and verify it appears in reports within 2 minutes

---

**Problem:** Export button is grayed out.

**Diagnosis:**
1. Report may still be loading
2. User may not have export permission

**Solution:**
- Wait a few seconds and try again
- Contact admin to verify `report:export` permission is assigned

---

## Next Steps

- [Menu Availability](menu-availability.md) — adjust menu based on report insights
- [Monitoring Guide](monitoring.md) — ensure reporting system stays online
- [Event Logs](event-logs.md) — audit who created/edited each report
