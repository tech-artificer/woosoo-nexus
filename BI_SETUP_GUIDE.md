# Krypton ↔ Woosoo BI Platform: Setup & Implementation Guide

## Overview

This BI platform provides a **read-only, unified reconciliation layer** that fuses Krypton POS and Woosoo Nexus order data via a crosswalk. It enables real-time drift detection, historical snapshots, and dimensional analysis for your business intelligence tools.

### Key Artifacts

- **krypton_woosoo_bi_views.sql** - SQL views and materialized tables (MySQL & PostgreSQL)
- **bi_processor.py** - Python ETL for automated refresh and reporting
- **bi_drift_report.json** - Generated drift report (sample output)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│ Krypton POS Domain          │ Crosswalk        │ Woosoo Nexus Domain │
├─────────────────────────────┼──────────────────┼─────────────────────┤
│ orders                      │                  │ orders              │
│ ordered_menus (line items)  │ woosoo_crosswalk │ device_orders       │
│ order_checks                │ _orders          │ device_order_items  │
│ menus (catalog)             │                  │ (tablet data)       │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│ BI LAYER (READ-ONLY VIEWS)                                          │
├─────────────────────────────────────────────────────────────────────┤
│ Real-Time Views:                                                     │
│  • bi_krypton_woosoo_order_fusion        → Order-level fusion       │
│  • bi_order_drift_detection              → Drift identification     │
│  • bi_line_item_reconciliation           → Item-level alignment     │
│                                                                      │
│ Materialized Snapshots (Daily):                                     │
│  • bi_order_reconciliation_snapshot      → Point-in-time records    │
│                                                                      │
│ Dashboard Views:                                                     │
│  • bi_order_summary_by_status            → Status aggregates        │
│  • bi_drift_issues_summary               → Issue counts by severity │
│  • bi_krypton_order_metadata             → Krypton dimensions       │
│  • bi_woosoo_order_metadata              → Woosoo dimensions        │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
        ┌─────────────────────┴─────────────────────┐
        ↓                                           ↓
    BI Tools                                  Custom Reports
  (Tableau, Looker,                    (JSON, CSV exports)
   Grafana, etc.)
```

---

## Installation & Setup

### Step 1: Deploy SQL Schema

```bash
# For MySQL:
mysql -h your-mysql-host -u root -p krypton_woosoo < krypton_woosoo_bi_views.sql

# For PostgreSQL:
psql -h your-postgres-host -U postgres -d krypton_woosoo -f krypton_woosoo_bi_views.sql
```

### Step 2: Verify Views

```sql
-- MySQL
SHOW VIEWS LIKE 'bi_%';

-- PostgreSQL
SELECT table_name FROM information_schema.views 
WHERE table_schema = 'public' AND table_name LIKE 'bi_%';
```

### Step 3: Create BI Read-Only User (Optional but Recommended)

#### MySQL:
```sql
CREATE USER 'bi_readonly'@'%' IDENTIFIED BY 'your_secure_password';
GRANT SELECT ON krypton_woosoo.* TO 'bi_readonly'@'%';
GRANT SELECT ON woosoo.* TO 'bi_readonly'@'%';
FLUSH PRIVILEGES;
```

#### PostgreSQL:
```sql
CREATE ROLE bi_readonly WITH LOGIN PASSWORD 'your_secure_password';
GRANT CONNECT ON DATABASE krypton_woosoo TO bi_readonly;
GRANT USAGE ON SCHEMA public TO bi_readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO bi_readonly;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO bi_readonly;
```

### Step 4: Install Python Dependencies

```bash
pip install -r requirements.txt
```

**requirements.txt:**
```
pandas>=1.3.0
mysql-connector-python>=8.0.30
psycopg2-binary>=2.9.0
```

---

## Usage

### Option A: Direct SQL Queries (Ad-Hoc Analysis)

#### Get Order Fusion for Order 19643:
```sql
SELECT * FROM bi_krypton_woosoo_order_fusion 
WHERE krypton_order_id = 19643;
```

**Result Columns:**
| Column | Description |
|--------|-------------|
| `krypton_order_id` | Krypton order ID |
| `crosswalk_status` | LINKED or UNLINKED |
| `amount_reconciliation` | AMOUNT_OK or AMOUNT_MISMATCH |
| `opened_delta_seconds` | Time delta at order open |
| `closed_delta_seconds` | Time delta at order close |
| `severity` | HIGH, MEDIUM, LOW |

#### Detect All High-Severity Drift (Last 7 Days):
```sql
SELECT * FROM bi_order_drift_detection 
WHERE severity = 'HIGH' 
AND detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

#### Line-Item Reconciliation for Order 19643:
```sql
SELECT * FROM bi_line_item_reconciliation 
WHERE krypton_order_id = 19643 
AND item_reconciliation_status != 'MATCHED';
```

#### Dashboard: Order Count by Status:
```sql
SELECT * FROM bi_order_summary_by_status;
```

### Option B: Automated Python ETL

#### Full Drift Report (with Refresh):
```bash
python bi_processor.py \
  --dialect mysql \
  --host localhost \
  --user bi_readonly \
  --password 'secure_password' \
  --database krypton_woosoo \
  --refresh \
  --report \
  --output drift_report_$(date +%Y%m%d).json
```

#### Fetch Specific Order's Fusion Data:
```bash
python bi_processor.py \
  --dialect mysql \
  --host localhost \
  --user bi_readonly \
  --password 'secure_password' \
  --order-id 19643
```

#### Get High-Severity Drift Issues:
```bash
python bi_processor.py \
  --dialect postgresql \
  --host pg-server.example.com \
  --user bi_readonly \
  --password 'secure_password' \
  --database krypton_woosoo \
  --drift HIGH
```

---

## Materialized Snapshot Refresh Strategy

### MySQL: Scheduled Event

```sql
CREATE EVENT IF NOT EXISTS refresh_bi_snapshot_daily
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP + INTERVAL 1 DAY
DO CALL refresh_bi_order_reconciliation_snapshot();

-- Verify event
SELECT * FROM information_schema.EVENTS 
WHERE EVENT_NAME = 'refresh_bi_snapshot_daily';
```

### PostgreSQL: pg_cron Extension

```sql
-- Install extension (requires superuser)
CREATE EXTENSION IF NOT EXISTS pg_cron;

-- Schedule daily refresh at 2 AM
SELECT cron.schedule('refresh_bi_snapshot_daily', '0 2 * * *', 
  'SELECT refresh_bi_order_reconciliation_snapshot();');

-- List schedules
SELECT * FROM cron.job;
```

### Docker: Cron Container

```dockerfile
FROM python:3.10
RUN pip install -r requirements.txt
RUN apt-get update && apt-get install -y cron
COPY bi_processor.py /app/
COPY crontab /etc/cron.d/bi_cron
RUN chmod 0644 /etc/cron.d/bi_cron && crontab /etc/cron.d/bi_cron
CMD ["cron", "-f"]
```

**crontab:**
```
# Refresh materialized snapshot daily at 2 AM
0 2 * * * python /app/bi_processor.py --dialect mysql --host db --user root --password pass --refresh --report >> /var/log/bi_refresh.log 2>&1
```

---

## BI Tool Integration Examples

### Tableau

#### Data Source Setup:
1. Create new data source → MySQL/PostgreSQL connector
2. Select database: `krypton_woosoo`
3. Choose table/view: `bi_krypton_woosoo_order_fusion`
4. Tableau auto-detects columns as dimensions/measures

#### Sample Dashboard Worksheets:
```
Sheet 1: Order Status Distribution
  • Dimension: crosswalk_status
  • Measure: COUNT(krypton_order_id)
  • Color: amount_reconciliation

Sheet 2: Drift Timeline
  • Dimension: krypton_opened_at (bin by day)
  • Measure: COUNT (filtered severity='HIGH')
  • Bar chart with trend line

Sheet 3: Amount Reconciliation
  • Scatter: krypton_total (X-axis) vs woosoo_total (Y-axis)
  • Size: time delta
  • Color: crosswalk_status
```

### Looker (LookML Model)

```yaml
connection: "krypton_woosoo"

view: bi_order_fusion {
  sql_table_name: public.bi_krypton_woosoo_order_fusion ;;

  dimension: krypton_order_id {
    primary_key: yes
    type: number
    sql: ${TABLE}.krypton_order_id ;;
  }

  dimension: crosswalk_status {
    type: string
    sql: ${TABLE}.crosswalk_status ;;
  }

  dimension: severity {
    type: string
    suggestions: ["HIGH", "MEDIUM", "LOW"]
    sql: ${TABLE}.severity ;;
  }

  measure: order_count {
    type: count
  }

  measure: total_amount_delta {
    type: sum
    sql: ${TABLE}.krypton_total - ${TABLE}.woosoo_total ;;
  }
}

explore: bi_order_fusion {}
```

### Grafana

#### Data Source:
1. Configuration → Data Sources → Add data source
2. Select MySQL/PostgreSQL
3. Configure connection to `krypton_woosoo`

#### Panel: Drift Issues (Gauge):
```json
{
  "targets": [
    {
      "rawSql": "SELECT COUNT(*) FROM bi_order_drift_detection WHERE severity = 'HIGH' AND detected_at >= NOW() - INTERVAL '24 HOUR'",
      "format": "table"
    }
  ],
  "fieldConfig": {
    "defaults": {
      "thresholds": {
        "steps": [
          { "color": "green", "value": 0 },
          { "color": "red", "value": 1 }
        ]
      }
    }
  }
}
```

#### Panel: Amount Mismatches (Time Series):
```json
{
  "targets": [
    {
      "rawSql": "SELECT DATE(krypton_opened_at) as date, COUNT(*) FROM bi_order_drift_detection WHERE issue_type_amount IS NOT NULL GROUP BY date ORDER BY date DESC LIMIT 30",
      "format": "time_series"
    }
  ]
}
```

---

## Data Dictionary

### bi_krypton_woosoo_order_fusion

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| `krypton_order_id` | INT | Krypton order ID (PK) | 19643 |
| `krypton_order_uuid` | VARCHAR(255) | Krypton order UUID | `a1b2c3d4-...` |
| `krypton_opened_at` | DATETIME | When order was opened in Krypton | 2025-01-15 14:23:45 |
| `krypton_closed_at` | DATETIME | When order was closed in Krypton | 2025-01-15 14:45:30 |
| `krypton_total` | DECIMAL(12,2) | Total amount in Krypton | 125.50 |
| `woosoo_order_id` | INT | Woosoo order ID (FK) | 9876 |
| `woosoo_order_uuid` | VARCHAR(255) | Woosoo order UUID | `x9y8z7w6-...` |
| `woosoo_opened_at` | DATETIME | When order was opened in Woosoo | 2025-01-15 14:23:46 |
| `woosoo_closed_at` | DATETIME | When order was closed in Woosoo | 2025-01-15 14:45:31 |
| `woosoo_total` | DECIMAL(12,2) | Total amount in Woosoo | 125.50 |
| `crosswalk_status` | VARCHAR(50) | LINKED or UNLINKED | LINKED |
| `amount_reconciliation` | VARCHAR(50) | AMOUNT_OK or AMOUNT_MISMATCH | AMOUNT_OK |
| `opened_delta_seconds` | INT | Time difference at open (sec) | 1 |
| `closed_delta_seconds` | INT | Time difference at close (sec) | 1 |
| `crosswalk_linked_at` | DATETIME | When crosswalk link was created | 2025-01-15 14:23:50 |
| `view_generated_at` | DATETIME | When view was refreshed | NOW() |

### bi_order_drift_detection

| Column | Type | Description |
|--------|------|-------------|
| `krypton_order_id` | INT | Krypton order ID |
| `issue_type_crosswalk` | VARCHAR(50) | NULL or 'UNLINKED_CROSSWALK' |
| `issue_type_amount` | VARCHAR(50) | NULL or 'AMOUNT_MISMATCH' |
| `issue_type_timing` | VARCHAR(50) | NULL or 'OPENED_TIME_DRIFT' |
| `severity` | VARCHAR(50) | HIGH, MEDIUM, or LOW |
| `detected_at` | DATETIME | Detection timestamp |

### bi_line_item_reconciliation

| Column | Type | Description |
|--------|------|-------------|
| `krypton_order_id` | INT | Krypton order ID |
| `krypton_item_id` | INT | Krypton line item ID |
| `krypton_menu_name` | VARCHAR(255) | Menu item name |
| `krypton_qty` | INT | Quantity ordered in Krypton |
| `krypton_subtotal` | DECIMAL(12,2) | Subtotal in Krypton |
| `woosoo_item_id` | INT | Woosoo line item ID |
| `woosoo_qty` | INT | Quantity ordered in Woosoo |
| `woosoo_subtotal` | DECIMAL(12,2) | Subtotal in Woosoo |
| `item_reconciliation_status` | VARCHAR(50) | MATCHED, QTY_MISMATCH, PRICE_MISMATCH, UNKNOWN |
| `qty_delta` | INT | Quantity difference (Krypton - Woosoo) |
| `subtotal_delta` | DECIMAL(12,2) | Subtotal difference |

---

## Drift Analysis & Remediation

### Common Drift Patterns

#### 1. Unlinked Crosswalk (Severity: HIGH)
**Issue:** Order exists in Krypton but no entry in `woosoo_crosswalk_orders`.

**Root Causes:**
- Crosswalk creation failed during order sync
- Manual order entry in only one system
- Late arrival due to network latency

**Remediation:**
```sql
-- Identify unlinked orders
SELECT * FROM bi_krypton_woosoo_order_fusion 
WHERE crosswalk_status = 'UNLINKED' AND krypton_opened_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Manually link (if you've identified the correct Woosoo order)
INSERT INTO woosoo_crosswalk_orders (krypton_order_id, woosoo_order_id, krypton_order_uuid, woosoo_order_uuid, created_at)
VALUES (19643, 9876, 'krypton-uuid', 'woosoo-uuid', NOW());
```

#### 2. Amount Mismatch (Severity: HIGH)
**Issue:** `krypton_total` ≠ `woosoo_total`.

**Root Causes:**
- Tax calculated differently
- Discount applied in only one system
- Item price change post-sync
- Voided/removed item not reflected everywhere

**Remediation:**
```sql
-- Find mismatches with details
SELECT 
  k.krypton_order_id, 
  k.krypton_total,
  w.woosoo_total,
  ABS(k.krypton_total - w.woosoo_total) AS delta
FROM bi_krypton_woosoo_order_fusion k
WHERE k.amount_reconciliation = 'AMOUNT_MISMATCH'
ORDER BY delta DESC LIMIT 10;

-- Audit line items
SELECT * FROM bi_line_item_reconciliation 
WHERE krypton_order_id = 19643 
AND item_reconciliation_status != 'MATCHED';
```

#### 3. Timestamp Drift (Severity: MEDIUM)
**Issue:** `opened_delta_seconds` or `closed_delta_seconds` > 300 sec.

**Root Causes:**
- System clock skew between Krypton and Woosoo
- Batch sync creates delayed entries
- Timezone mismatch (UTC vs local)

**Remediation:**
```sql
-- Monitor clock skew
SELECT 
  ROUND(AVG(ABS(opened_delta_seconds)), 0) AS avg_open_delta,
  ROUND(AVG(ABS(closed_delta_seconds)), 0) AS avg_close_delta,
  MAX(ABS(opened_delta_seconds)) AS max_open_delta
FROM bi_krypton_woosoo_order_fusion
WHERE krypton_opened_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Sync system clocks
# Linux
sudo ntpdate -s time.nist.gov

# Or use timedatectl (systemd)
sudo timedatectl set-ntp true
```

#### 4. Line-Item Quantity Mismatch (Severity: MEDIUM)
**Issue:** Item quantity differs between Krypton and Woosoo.

**Root Causes:**
- Partial item removed in one system
- Device order split across multiple tablets
- Manual adjustment on tablet not synced

**Remediation:**
```sql
-- Find items with quantity delta
SELECT * FROM bi_line_item_reconciliation 
WHERE qty_delta != 0 AND krypton_order_id = 19643;

-- Check device orders to see if item was split
SELECT 
  do.device_id,
  do.order_number,
  COUNT(DISTINCT doi.id) AS item_count,
  SUM(doi.quantity) AS total_qty
FROM woosoo.device_orders do
LEFT JOIN woosoo.device_order_items doi ON do.id = doi.order_id
GROUP BY do.device_id, do.order_number;
```

---

## Performance Tuning

### Index Strategy

```sql
-- Ensure crosswalk is indexed on both directions
CREATE INDEX idx_woosoo_crosswalk_krypton ON woosoo_crosswalk_orders (krypton_order_id);
CREATE INDEX idx_woosoo_crosswalk_woosoo ON woosoo_crosswalk_orders (woosoo_order_id);

-- Index line items for joins
CREATE INDEX idx_krypton_ordered_menus_order ON krypton_woosoo.ordered_menus (order_id, menu_id);
CREATE INDEX idx_woosoo_device_items_order ON woosoo.device_order_items (order_id, menu_id);

-- Index for filtering by time ranges
CREATE INDEX idx_krypton_opened ON krypton_woosoo.orders (date_time_opened);
CREATE INDEX idx_woosoo_opened ON woosoo.orders (date_time_opened);
```

### View Materialization

For high-volume BI queries, periodically materialize the fusion view:

```sql
-- MySQL: Create materialized table from view
CREATE TABLE bi_order_fusion_materialized AS
SELECT * FROM bi_krypton_woosoo_order_fusion;

CREATE INDEX idx_mat_krypton ON bi_order_fusion_materialized (krypton_order_id);
CREATE INDEX idx_mat_woosoo ON bi_order_fusion_materialized (woosoo_order_id);

-- Refresh daily (add to cron)
TRUNCATE TABLE bi_order_fusion_materialized;
INSERT INTO bi_order_fusion_materialized SELECT * FROM bi_krypton_woosoo_order_fusion;
```

---

## Monitoring & Alerting

### Alert Setup (Example: High Drift)

#### MySQL Event + Email:
```sql
CREATE EVENT IF NOT EXISTS alert_high_drift_daily
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP + INTERVAL 1 DAY
DO
BEGIN
  DECLARE drift_count INT;
  SELECT COUNT(*) INTO drift_count FROM bi_order_drift_detection 
  WHERE severity = 'HIGH' AND detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
  
  IF drift_count > 10 THEN
    -- Call stored procedure to send email/alert
    CALL send_alert_email('ops@example.com', CONCAT('High drift detected: ', drift_count, ' orders'));
  END IF;
END;
```

#### Grafana Alert Rule:
```json
{
  "uid": "drift_alert_1",
  "title": "High Severity Drift Alert",
  "condition": "B",
  "data": [
    {
      "refId": "A",
      "queryType": "",
      "model": {
        "rawSql": "SELECT COUNT(*) FROM bi_order_drift_detection WHERE severity='HIGH' AND detected_at >= NOW() - INTERVAL '1 hour'",
        "format": "table"
      }
    },
    {
      "refId": "B",
      "expression": "A > 5",
      "datasourceUid": "__expr__",
      "conditions": [
        { "evaluator": { "type": "gt", "params": [5] } }
      ]
    }
  ],
  "noDataState": "OK",
  "execErrState": "Alerting",
  "for": "5m",
  "annotations": {
    "description": "More than 5 high-severity drift issues detected in the last hour"
  }
}
```

---

## Backup & Recovery

### View Definition Export

```bash
# MySQL
mysqldump -h localhost -u root -p krypton_woosoo --no-data -d | grep "CREATE VIEW" > bi_views_backup.sql

# PostgreSQL
pg_dump -h localhost -U postgres -d krypton_woosoo --schema-only | grep "CREATE VIEW" > bi_views_backup.sql
```

### Snapshot Table Backup

```bash
# MySQL
mysqldump -h localhost -u root -p krypton_woosoo bi_order_reconciliation_snapshot > snapshot_backup_$(date +%Y%m%d).sql

# PostgreSQL
pg_dump -h localhost -U postgres -d krypton_woosoo -t bi_order_reconciliation_snapshot > snapshot_backup_$(date +%Y%m%d).sql
```

---

## FAQ

**Q: How often should I refresh the materialized snapshot?**
A: Daily is typical (2 AM off-peak). For real-time drift detection, query the views directly. For BI historical analysis, use snapshots.

**Q: Can I customize thresholds (e.g., amount_delta, time_delta)?**
A: Yes. Edit the view definitions in `krypton_woosoo_bi_views.sql`:
- Line ~30: Change `> 0.01` for amount precision
- Line ~40: Change `> 300` for time tolerance (in seconds)

**Q: What if I need to exclude certain orders (e.g., voided)?**
A: Add a WHERE clause to the view or create a filtered view:
```sql
CREATE VIEW bi_order_fusion_active AS
SELECT * FROM bi_krypton_woosoo_order_fusion
WHERE krypton_closed_at IS NOT NULL;
```

**Q: How do I validate the crosswalk linkage?**
A: Run this audit query:
```sql
SELECT 
  COUNT(*) AS total_krypton_orders,
  SUM(CASE WHEN woosoo_order_id IS NOT NULL THEN 1 ELSE 0 END) AS linked,
  SUM(CASE WHEN woosoo_order_id IS NULL THEN 1 ELSE 0 END) AS unlinked,
  ROUND(100.0 * SUM(CASE WHEN woosoo_order_id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 2) AS link_pct
FROM bi_krypton_woosoo_order_fusion;
```

**Q: Can I version the materialized snapshots (keep history)?**
A: Yes. Add a `snapshot_version` column to track monthly snapshots:
```sql
ALTER TABLE bi_order_reconciliation_snapshot ADD COLUMN snapshot_month VARCHAR(7) DEFAULT DATE_FORMAT(NOW(), '%Y-%m');
CREATE UNIQUE INDEX uk_monthly_order ON bi_order_reconciliation_snapshot (snapshot_month, krypton_order_id);
```

---

## Support & Contribution

For issues or improvements:
1. Check logs: `bi_refresh.log`
2. Validate SQL syntax with `mysql --syntax-check` or `psql -f <file>`
3. Report bugs with full error message + query context

---

## License

This BI platform is provided as-is for internal analytics use.

