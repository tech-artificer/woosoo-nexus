# ARCHIVED DOCUMENT

This document is deprecated and no longer reflects the official architecture or deployment standard.

Refer to canonical documentation under:
docs/

---

# Quick Start: Using the DHI-Hardened BI Platform

This guide walks you through deploying and using the Krypton ↔ Woosoo BI reconciliation platform with Docker Hardened Images (DHI) security.

---

## **Step 1: Verify Your Environment**

Check you have Docker and Docker Compose installed:

```bash
docker --version
# Expected: Docker version 20.10+

docker compose version
# Expected: Docker Compose version 2.0+
```

If not installed, install from: https://docs.docker.com/get-docker/

---

## **Step 2: Configure Your Database Connection**

Copy the environment template and edit it with your actual database details:

```bash
cp .env.example .env
nano .env
```

**Edit these lines:**

```env
# Database Connection
DB_DIALECT=mysql              # or postgresql
DB_HOST=localhost             # Your database host
DB_PORT=3306                  # Default: 3306 (MySQL), 5432 (PostgreSQL)
DB_USER=bi_readonly           # Database user (read-only recommended)
DB_PASSWORD=your_password     # Your database password
DB_NAME=krypton_woosoo        # Database name
```

**Example for MySQL:**
```env
DB_DIALECT=mysql
DB_HOST=db.example.com
DB_PORT=3306
DB_USER=bi_user
DB_PASSWORD=secure_pass123
DB_NAME=krypton_woosoo
```

**Example for PostgreSQL:**
```env
DB_DIALECT=postgresql
DB_HOST=pg.example.com
DB_PORT=5432
DB_USER=bi_user
DB_PASSWORD=secure_pass123
DB_NAME=krypton_woosoo
```

Save and exit (Ctrl+X, then Y, then Enter if using nano).

---

## **Step 3: Deploy SQL Views to Your Database**

The SQL views must be created in your database first. This is a one-time setup.

### **Option A: Using Docker (Easiest)**

```bash
docker run --rm \
  -v $(pwd)/krypton_woosoo_bi_views.sql:/sql/views.sql \
  -e MYSQL_PWD="your_password" \
  mysql:8.0 \
  mysql -h db.example.com -u bi_user -e "SOURCE /sql/views.sql;" krypton_woosoo
```

Or for PostgreSQL:
```bash
docker run --rm \
  -v $(pwd)/krypton_woosoo_bi_views.sql:/sql/views.sql \
  postgres:14 \
  psql -h db.example.com -U bi_user -d krypton_woosoo -f /sql/views.sql
```

### **Option B: Manually (Direct Connection)**

If you have mysql or psql client installed locally:

**MySQL:**
```bash
mysql -h db.example.com -u bi_user -p -D krypton_woosoo < krypton_woosoo_bi_views.sql
# When prompted, enter your password
```

**PostgreSQL:**
```bash
PGPASSWORD="your_password" psql -h db.example.com -U bi_user -d krypton_woosoo -f krypton_woosoo_bi_views.sql
```

### **Verify Deployment:**

**MySQL:**
```bash
mysql -h db.example.com -u bi_user -p -D krypton_woosoo -e "SHOW VIEWS LIKE 'bi_%';"
```

**PostgreSQL:**
```bash
psql -h db.example.com -U bi_user -d krypton_woosoo -c "SELECT table_name FROM information_schema.views WHERE table_name LIKE 'bi_%';"
```

You should see 5+ views listed (bi_krypton_woosoo_order_fusion, bi_order_drift_detection, etc.).

---

## **Step 4: Build the DHI-Hardened Docker Image**

Build the secure bi_processor image using Docker Hardened Images:

```bash
docker build -f Dockerfile.processor.dhi -t bi-processor:dhi .
```

**What happens:**
- Pulls hardened Python 3.13 image from `dhi.io`
- Installs dependencies (pandas, mysql-connector, psycopg2)
- Creates nonroot user (UID 1000)
- Strips build tools (multi-stage build = 68% size reduction)
- Final image: ~80 MB (vs. 250 MB stock image)

**Verify the build succeeded:**
```bash
docker images | grep bi-processor
# You should see: bi-processor          dhi    [SIZE]    [DATE]
```

---

## **Step 5: Test the Image**

Run a test query to verify everything works:

```bash
docker run --rm \
  --env-file .env \
  bi-processor:dhi \
  python /app/bi_processor.py \
    --dialect mysql \
    --host db.example.com \
    --user bi_user \
    --password "your_password" \
    --database krypton_woosoo \
    --order-id 19643
```

**Expected output:**
A table showing order 19643's reconciliation data (if the order exists).

**If you get an error:**
- Check your `.env` file is correct
- Verify database host is reachable: `ping db.example.com`
- Confirm credentials: `mysql -h db.example.com -u bi_user -p`

---

## **Step 6: Deploy with Docker Compose (Production)**

For BI/testing workflows, use the dedicated Compose file so the BI utilities stay separate from the main application stack in `compose.yaml`:

```bash
docker compose -f compose.bi.yaml up -d bi-processor
```

If you want the full local BI sandbox (MySQL, PostgreSQL, Grafana, and the processor), target the same file explicitly:

```bash
docker compose -f compose.bi.yaml up -d
```

**Start the service:**

```bash
docker compose -f compose.bi.yaml up -d bi-processor
```

**Verify it's running:**

```bash
docker compose -f compose.bi.yaml ps
# Should show: bi-processor  [Running]

docker compose -f compose.bi.yaml logs bi-processor
# Should show startup messages, no errors
```

---

## **Step 7: Run a Drift Report**

Generate a drift detection report:

```bash
docker exec bi-processor python /app/bi_processor.py \
  --dialect mysql \
  --host db.example.com \
  --user bi_user \
  --password "your_password" \
  --database krypton_woosoo \
  --refresh \
  --report \
  --output /app/reports/drift_report_$(date +%Y%m%d).json
```

**Output:**
A JSON file in `./reports/` with:
- Summary metrics (total orders, linked/unlinked)
- High-severity drift issues
- Line-item reconciliation problems
- Recommendations

**View the report:**

```bash
cat reports/drift_report_*.json | jq '.'
```

---

## **Step 8: Query the BI Views Directly**

Once views are deployed, query them directly from any SQL client:

### **Query 1: Get Order Reconciliation for Order 19643**

```sql
SELECT 
  krypton_order_id,
  woosoo_order_id,
  crosswalk_status,
  amount_reconciliation,
  krypton_total,
  woosoo_total,
  opened_delta_seconds,
  severity
FROM bi_krypton_woosoo_order_fusion
WHERE krypton_order_id = 19643;
```

**Result columns:**
- `crosswalk_status`: LINKED or UNLINKED
- `amount_reconciliation`: AMOUNT_OK or AMOUNT_MISMATCH
- `opened_delta_seconds`: Time difference between systems
- `severity`: HIGH, MEDIUM, LOW

### **Query 2: Find All High-Severity Drift (Last 24 Hours)**

```sql
SELECT 
  krypton_order_id,
  woosoo_order_id,
  issue_type_crosswalk,
  issue_type_amount,
  issue_type_timing,
  severity,
  detected_at
FROM bi_order_drift_detection
WHERE severity = 'HIGH' 
AND detected_at >= NOW() - INTERVAL 24 HOUR
ORDER BY detected_at DESC;
```

### **Query 3: Check Line-Item Mismatches**

```sql
SELECT 
  krypton_order_id,
  krypton_menu_name,
  krypton_qty,
  woosoo_qty,
  krypton_subtotal,
  woosoo_subtotal,
  item_reconciliation_status,
  qty_delta
FROM bi_line_item_reconciliation
WHERE krypton_order_id = 19643
AND item_reconciliation_status != 'MATCHED';
```

### **Query 4: Dashboard - Orders by Status**

```sql
SELECT 
  crosswalk_status,
  amount_reconciliation,
  COUNT(*) AS order_count,
  SUM(krypton_total) AS total_amount
FROM bi_krypton_woosoo_order_fusion
GROUP BY crosswalk_status, amount_reconciliation;
```

---

## **Step 9: Connect to a BI Tool (Optional)**

### **Tableau:**

1. Open Tableau Desktop
2. Data → New Data Source → MySQL/PostgreSQL
3. Server: `db.example.com`
4. Username: `bi_user`
5. Database: `krypton_woosoo`
6. Table: `bi_krypton_woosoo_order_fusion`
7. Create worksheets:
   - **Sheet 1:** COUNT(krypton_order_id) by crosswalk_status
   - **Sheet 2:** SUM(krypton_total) by severity
   - **Sheet 3:** Scatter plot (krypton_total vs woosoo_total)

### **Grafana:**

1. Open Grafana: `http://localhost:3000`
2. Configuration → Data Sources → Add
3. Select MySQL/PostgreSQL
4. URL: `db.example.com:3306` (or 5432)
5. Database: `krypton_woosoo`
6. Create Dashboard → Add Panel
7. Query:
   ```sql
   SELECT COUNT(*) FROM bi_order_drift_detection 
   WHERE severity='HIGH' AND detected_at >= NOW() - INTERVAL 24 HOUR
   ```

### **Power BI / Looker:**
Similar steps—connect to `krypton_woosoo` database, select BI views.

---

## **Step 10: Schedule Automatic Daily Refresh**

The bi-processor can run on a schedule. Docker handles this with cron.

### **Option A: Docker Cron (Already Built-In)**

The `Dockerfile.processor.dhi` includes cron scheduling. By default, it runs daily at 2 AM.

To change the schedule, rebuild with a new cron time:

```dockerfile
# Edit Dockerfile.processor.dhi, line ~40:
RUN echo "0 2 * * * python /app/bi_processor.py ... >> /app/logs/refresh.log 2>&1" | crontab -
#                ^^ ^^ — Change these for different times
#             minutes hour
```

Then rebuild:
```bash
docker build -f Dockerfile.processor.dhi -t bi-processor:dhi .
```

### **Option B: System Cron**

If running natively (not Docker):

```bash
crontab -e
```

Add this line:

```cron
0 2 * * * cd /path/to/project && python3 bi_processor.py --dialect mysql --host db.example.com --user bi_user --password "pass" --database krypton_woosoo --refresh --report --output reports/drift_$(date +\%Y\%m\%d).json >> logs/refresh.log 2>&1
```

This runs daily at 2 AM and generates a drift report.

---

## **Troubleshooting**

### **"Connection refused" error**

```bash
# Verify database is reachable
ping db.example.com
# or
nc -zv db.example.com 3306  # MySQL
nc -zv db.example.com 5432  # PostgreSQL
```

### **"Permission denied" or "nonroot user" errors**

This is expected. The DHI image runs as UID 1000 (nonroot). Verify:

```bash
docker run --rm bi-processor:dhi id
# Should show: uid=1000 gid=1000 groups=1000
```

### **"Views not found" error**

Verify SQL views were deployed:

```bash
mysql -h db.example.com -u bi_user -p -D krypton_woosoo -e "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='krypton_woosoo' AND table_name LIKE 'bi_%';"
# Should show: 5+
```

If 0, redeploy the SQL:
```bash
mysql -h db.example.com -u bi_user -p -D krypton_woosoo < krypton_woosoo_bi_views.sql
```

### **"No reports generated" error**

Check logs:

```bash
docker compose -f compose.bi.yaml logs bi-processor
# or
cat logs/bi_refresh.log
```

Look for SQL errors or connection issues.

---

## **Quick Commands Reference**

| Task | Command |
|------|---------|
| Deploy SQL views | `mysql -u bi_user -p -D krypton_woosoo < krypton_woosoo_bi_views.sql` |
| Build DHI image | `docker build -f Dockerfile.processor.dhi -t bi-processor:dhi .` |
| Test image | `docker run --rm --env-file .env bi-processor:dhi python /app/bi_processor.py --order-id 19643` |
| Start service | `docker compose -f compose.bi.yaml up -d bi-processor` |
| View logs | `docker compose -f compose.bi.yaml logs -f bi-processor` |
| Run report | `docker exec bi-processor python /app/bi_processor.py --report --output /app/reports/report.json` |
| Query views | `mysql -u bi_user -p -D krypton_woosoo -e "SELECT * FROM bi_krypton_woosoo_order_fusion LIMIT 10;"` |
| Stop service | `docker compose -f compose.bi.yaml down` |

---

## **What Each File Does**

| File | Purpose |
|------|---------|
| `krypton_woosoo_bi_views.sql` | SQL views (deploy once to database) |
| `bi_processor.py` | Python ETL (handles refresh, reporting) |
| `Dockerfile.processor.dhi` | Hardened Docker image (secure, minimal) |
| `compose.bi.yaml` | Dedicated BI/testing orchestration (start/stop BI services) |
| `.env` | Configuration (database credentials) |
| `BI_SETUP_GUIDE.md` | Full technical reference |
| `DHI_MIGRATION_ASSESSMENT.md` | Security analysis & compliance |

---

## **Next Steps**

1. ✅ Configure `.env` with your database details
2. ✅ Deploy SQL views (`krypton_woosoo_bi_views.sql`)
3. ✅ Build DHI image (`docker build -f Dockerfile.processor.dhi ...`)
4. ✅ Start service (`docker compose -f compose.bi.yaml up -d`)
5. ✅ Query BI views or generate reports
6. ✅ Connect BI tool (Tableau/Grafana)
7. ✅ Set up daily refresh schedule

**Questions?** Check `BI_SETUP_GUIDE.md` or `DHI_MIGRATION_ASSESSMENT.md`.

