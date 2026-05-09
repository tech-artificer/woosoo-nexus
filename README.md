# Krypton ↔ Woosoo BI Platform

A production-ready, unified **read-only BI layer** that reconciles Krypton POS and Woosoo Nexus order data via a crosswalk. Enables real-time drift detection, historical snapshots, and seamless integration with BI tools.

## 📦 What You Get

| File | Purpose |
|------|---------|
| **krypton_woosoo_bi_views.sql** | Core SQL views + materialized tables (MySQL & PostgreSQL) |
| **bi_processor.py** | Python ETL for automated refresh, drift detection, reporting |
| **docker-compose.yml** | Full stack: MySQL/PostgreSQL + Python processor + Grafana |
| **Dockerfile.processor** | Container image for ETL service |
| **BI_SETUP_GUIDE.md** | Comprehensive 50+ page setup & integration guide |
| **quickstart.sh** | Automated setup script (Docker or native) |
| **.env.example** | Configuration template |
| **requirements.txt** | Python dependencies |

## 🚀 Quick Start (2 minutes)

### Option A: Docker (Recommended)

```bash
# Clone/download files to a directory
cd krypton-woosoo-bi

# Run setup with Docker
bash quickstart.sh docker

# Wait ~30 seconds for services to start
docker-compose logs -f bi-processor

# Access:
# - MySQL: localhost:3306 (bi_user / bi_password)
# - PostgreSQL: localhost:5432 (bi_user / bi_password)
# - Grafana: http://localhost:3000 (admin / admin_password)
```

### Option B: Native Installation

```bash
# Install prerequisites: python3, pip, mysql-client (or psql)

# Run setup
bash quickstart.sh

# Follow prompts for database connection details
# Views will be deployed automatically
```

## 🏗️ Architecture

```
Krypton POS              Crosswalk              Woosoo Nexus
    ↓                        ↓                      ↓
 orders              woosoo_crosswalk_orders    orders
 ordered_menus            (links)           device_orders
 order_checks                                device_order_items
    ↓                        ↓                      ↓
 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
            BI VIEWS (READ-ONLY)
 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    ↓
 bi_krypton_woosoo_order_fusion ← Order-level fusion
 bi_order_drift_detection       ← Drift identification
 bi_line_item_reconciliation    ← Item-level alignment
 bi_order_reconciliation_snapshot ← Daily snapshots
    ↓
 BI Tools (Tableau, Looker, Grafana, custom dashboards)
```

## 📊 Core Views

### 1. **bi_krypton_woosoo_order_fusion** (Real-Time)
Unified order view across both systems.

```sql
SELECT * FROM bi_krypton_woosoo_order_fusion 
WHERE krypton_order_id = 19643;
```

**Key columns:**
- `krypton_order_id`, `woosoo_order_id` (linked via crosswalk)
- `krypton_total`, `woosoo_total` (amount reconciliation)
- `crosswalk_status` (LINKED or UNLINKED)
- `amount_reconciliation` (AMOUNT_OK or AMOUNT_MISMATCH)
- `opened_delta_seconds`, `closed_delta_seconds` (timing alignment)

### 2. **bi_order_drift_detection** (Real-Time)
Identifies orders with reconciliation issues.

```sql
SELECT * FROM bi_order_drift_detection 
WHERE severity = 'HIGH' AND detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

**Detects:**
- Unlinked crosswalk entries (HIGH severity)
- Amount mismatches (HIGH severity)
- Timestamp drift > 5 minutes (MEDIUM severity)

### 3. **bi_line_item_reconciliation** (Real-Time)
Item-level alignment between Krypton ordered_menus and Woosoo device_order_items.

```sql
SELECT * FROM bi_line_item_reconciliation 
WHERE krypton_order_id = 19643 AND item_reconciliation_status != 'MATCHED';
```

**Detects:**
- Quantity mismatches
- Price mismatches
- Missing items

### 4. **bi_order_reconciliation_snapshot** (Daily Snapshot)
Point-in-time materialized table for historical analysis.

```sql
SELECT * FROM bi_order_reconciliation_snapshot 
WHERE snapshot_date = CURDATE();
```

### 5. Dashboard Summary Views
- `bi_order_summary_by_status` → Order counts by status
- `bi_drift_issues_summary` → Issue counts by severity
- `bi_krypton_order_metadata` → Krypton dimensions
- `bi_woosoo_order_metadata` → Woosoo dimensions

## 🔧 Usage Examples

### Get Order Fusion for Order 19643

```sql
SELECT 
  krypton_order_id, woosoo_order_id,
  krypton_total, woosoo_total,
  crosswalk_status, amount_reconciliation,
  opened_delta_seconds, severity
FROM bi_krypton_woosoo_order_fusion 
WHERE krypton_order_id = 19643;
```

### Find All High-Severity Drift (Last 24 Hours)

```sql
SELECT 
  krypton_order_id, woosoo_order_id,
  issue_type_crosswalk, issue_type_amount, issue_type_timing,
  severity, detected_at
FROM bi_order_drift_detection 
WHERE severity = 'HIGH' AND detected_at >= NOW() - INTERVAL '24 HOUR'
ORDER BY detected_at DESC;
```

### Python: Generate Daily Drift Report

```bash
python bi_processor.py \
  --dialect mysql \
  --host localhost \
  --user bi_readonly \
  --password 'password' \
  --database krypton_woosoo \
  --refresh \
  --report \
  --output drift_report_$(date +%Y%m%d).json
```

### Python: Fetch Specific Order's Data

```bash
python bi_processor.py \
  --dialect postgresql \
  --host pg.example.com \
  --user bi_readonly \
  --password 'password' \
  --order-id 19643
```

## 📈 Drift Analysis

### Common Issues

| Issue | Severity | Root Cause | Fix |
|-------|----------|-----------|-----|
| Unlinked crosswalk | HIGH | No entry in woosoo_crosswalk_orders | Manually insert crosswalk link |
| Amount mismatch | HIGH | Tax/discount/item delta | Audit line items, check order_checks |
| Timestamp drift | MEDIUM | System clock skew, network latency | Sync clocks (ntpdate), check network |
| Item qty mismatch | MEDIUM | Partial removal, device split | Check device_orders, order_checks |

### Audit Query for Order 19643

```sql
-- Check item reconciliation
SELECT * FROM bi_line_item_reconciliation 
WHERE krypton_order_id = 19643;

-- Check device orders (tablet data)
SELECT do.device_id, do.order_number, COUNT(*) as item_count
FROM woosoo.device_orders do
LEFT JOIN woosoo.device_order_items doi ON do.id = doi.order_id
WHERE do.order_id IN (
  SELECT woosoo_order_id FROM woosoo_crosswalk_orders 
  WHERE krypton_order_id = 19643
)
GROUP BY do.device_id, do.order_number;
```

## 🔄 Refresh Strategy

### Real-Time Views
Query directly for up-to-the-second data (minimal overhead, views are lightweight).

### Materialized Snapshot
Automatically refreshes daily at 2 AM (configurable).

#### MySQL: Scheduled Event
```sql
CREATE EVENT refresh_bi_snapshot_daily
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP + INTERVAL 1 DAY
DO CALL refresh_bi_order_reconciliation_snapshot();
```

#### PostgreSQL: pg_cron
```sql
CREATE EXTENSION pg_cron;
SELECT cron.schedule('refresh_bi_snapshot_daily', '0 2 * * *', 
  'SELECT refresh_bi_order_reconciliation_snapshot();');
```

#### Docker: Built-In
Cron job runs automatically in the `bi-processor` container.

## 📊 BI Tool Integration

### Tableau
1. Data Source → MySQL/PostgreSQL
2. Table/View → `bi_krypton_woosoo_order_fusion`
3. Dimensions: `crosswalk_status`, `amount_reconciliation`, `severity`
4. Measures: COUNT, SUM(krypton_total), AVG(opened_delta_seconds)

### Looker (LookML)
```yaml
view: bi_order_fusion {
  sql_table_name: bi_krypton_woosoo_order_fusion ;;
  
  dimension: krypton_order_id { primary_key: yes; type: number; }
  dimension: crosswalk_status { type: string; suggestions: ["LINKED", "UNLINKED"]; }
  
  measure: order_count { type: count; }
  measure: total_drift { type: sum; sql: ${TABLE}.opened_delta_seconds;; }
}
explore: bi_order_fusion {}
```

### Grafana
1. Data Source → MySQL/PostgreSQL
2. Query: `SELECT COUNT(*) FROM bi_order_drift_detection WHERE severity='HIGH' AND detected_at >= NOW() - INTERVAL '24 HOUR'`
3. Alert: Notify when count > threshold
4. Panel Types: Gauge (drift count), Time series (drift over time), Table (recent issues)

## 🛡️ Security

### Read-Only User

**MySQL:**
```sql
CREATE USER 'bi_readonly'@'%' IDENTIFIED BY 'secure_password';
GRANT SELECT ON krypton_woosoo.* TO 'bi_readonly'@'%';
GRANT SELECT ON woosoo.* TO 'bi_readonly'@'%';
FLUSH PRIVILEGES;
```

**PostgreSQL:**
```sql
CREATE ROLE bi_readonly WITH LOGIN PASSWORD 'secure_password';
GRANT SELECT ON ALL TABLES IN SCHEMA public TO bi_readonly;
```

### Network Security
- Use environment variables (`.env`) for passwords, never hardcode
- Restrict database access to internal networks (VPC/private subnets)
- Use SSL/TLS for remote database connections
- Enable query logging for audit trails

## 📋 Configuration

Edit `.env` for customization:

```bash
# Copy template
cp .env.example .env

# Edit
nano .env
```

**Key settings:**
- `DB_DIALECT` - mysql or postgresql
- `DB_HOST`, `DB_USER`, `DB_PASSWORD` - Connection details
- `AMOUNT_TOLERANCE` - Precision for amount matching (default: 0.01)
- `TIME_DELTA_TOLERANCE` - Max acceptable time delta in seconds (default: 300)
- `ALERT_EMAIL`, `ALERT_SLACK_WEBHOOK` - Notifications
- `REFRESH_SCHEDULE` - Cron format (default: `0 2 * * *` → 2 AM daily)

## 🐛 Troubleshooting

### Views Not Appearing

```bash
# MySQL
mysql -u root -p krypton_woosoo -e "SHOW VIEWS LIKE 'bi_%';"

# PostgreSQL
psql -U postgres -d krypton_woosoo -c "SELECT table_name FROM information_schema.views WHERE table_schema='public' AND table_name LIKE 'bi_%';"
```

### No Crosswalk Links Found

```sql
SELECT COUNT(*) FROM woosoo_crosswalk_orders;
-- If 0: manually link orders or check sync logs
```

### High Time Delta

```sql
SELECT 
  ROUND(AVG(ABS(opened_delta_seconds)), 0) AS avg_open_delta,
  MAX(ABS(opened_delta_seconds)) AS max_open_delta
FROM bi_krypton_woosoo_order_fusion
WHERE krypton_opened_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
-- If > 300s: check system clocks, network latency
```

### Python ETL Errors

```bash
# Check logs
tail -f logs/bi_refresh.log

# Test connection manually
python bi_processor.py --dialect mysql --host localhost --user bi_user --password 'pass' --order-id 19643
```

### Docker Issues

```bash
# View all logs
docker-compose logs -f

# Restart services
docker-compose restart bi-processor

# Rebuild images
docker-compose build --no-cache
```

## 📚 Full Documentation

See **BI_SETUP_GUIDE.md** for:
- Detailed view definitions & SQL explanations
- Materialized snapshot strategy
- Performance tuning & indexes
- Drift analysis deep-dives
- Monitoring & alerting setup
- Backup & recovery procedures
- FAQ & troubleshooting

## 📦 Files Summary

| File | Size | Purpose |
|------|------|---------|
| `krypton_woosoo_bi_views.sql` | ~21 KB | SQL views, materialized tables, indexes |
| `bi_processor.py` | ~13 KB | Python ETL processor |
| `docker-compose.yml` | ~2.6 KB | Full stack orchestration |
| `Dockerfile.processor` | ~1 KB | Container image for ETL |
| `BI_SETUP_GUIDE.md` | ~21 KB | Comprehensive setup & reference |
| `quickstart.sh` | ~5 KB | Automated setup |
| `requirements.txt` | ~90 B | Python dependencies |
| `.env.example` | ~1.1 KB | Configuration template |
| `README.md` | This file | Overview & quick reference |

## 🚀 Deployment Checklist

- [ ] SQL views deployed to database
- [ ] Crosswalk table populated with Krypton ↔ Woosoo links
- [ ] BI read-only user created with SELECT permissions
- [ ] Daily snapshot refresh job configured (cron or scheduled event)
- [ ] Python ETL processor tested with `--report` flag
- [ ] BI tool connected to database and views verified
- [ ] Sample dashboards created (status, drift, items)
- [ ] Alerting configured (email/Slack for HIGH severity)
- [ ] Logs configured and monitored
- [ ] Backup procedure documented (views + snapshot table)

## 📞 Support

For issues:
1. Check `logs/bi_refresh.log` and `logs/*.log`
2. Verify database connection: `mysql -h host -u user -p -e "SELECT 1;"`
3. Test view queries directly
4. Review BI_SETUP_GUIDE.md FAQ section
5. Confirm indexes are present and data is fresh

## 📄 License

Provided as-is for internal analytics. Customize for your environment.

---

**Ready to deploy?** Run:
```bash
bash quickstart.sh docker
```

**Need help?** See BI_SETUP_GUIDE.md or check logs.

