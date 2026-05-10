#!/bin/bash
# DHI-HARDENED BI PLATFORM - COPY & PASTE COMMANDS
# Replace values marked with [YOUR_...] with your actual values

# ============================================================================
# SECTION 1: INITIAL SETUP
# ============================================================================

# Step 1: Configure your database connection
cat > .env << 'EOF'
DB_DIALECT=mysql
DB_HOST=[YOUR_DATABASE_HOST]
DB_PORT=3306
DB_USER=[YOUR_DB_USER]
DB_PASSWORD=[YOUR_DB_PASSWORD]
DB_NAME=krypton_woosoo
REPORT_OUTPUT_DIR=./reports
LOG_OUTPUT_DIR=./logs
EOF

# Step 2: Deploy SQL views (CHOOSE ONE)

# For MySQL:
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo < krypton_woosoo_bi_views.sql

# For PostgreSQL:
PGPASSWORD=[YOUR_DB_PASSWORD] psql -h [YOUR_DATABASE_HOST] -U [YOUR_DB_USER] -d krypton_woosoo -f krypton_woosoo_bi_views.sql

# Step 3: Verify SQL views were deployed
# For MySQL:
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "SHOW VIEWS LIKE 'bi_%';"

# For PostgreSQL:
PGPASSWORD=[YOUR_DB_PASSWORD] psql -h [YOUR_DATABASE_HOST] -U [YOUR_DB_USER] -d krypton_woosoo -c "SELECT table_name FROM information_schema.views WHERE table_name LIKE 'bi_%';"

# ============================================================================
# SECTION 2: BUILD & TEST DOCKER IMAGE
# ============================================================================

# Build the DHI-hardened image
docker build -f Dockerfile.processor.dhi -t bi-processor:dhi .

# Verify image exists and size
docker images | grep bi-processor

# Test the image with a sample query (replace order ID)
docker run --rm \
  --env-file .env \
  bi-processor:dhi \
  python /app/bi_processor.py \
    --dialect mysql \
    --host [YOUR_DATABASE_HOST] \
    --user [YOUR_DB_USER] \
    --password '[YOUR_DB_PASSWORD]' \
    --database krypton_woosoo \
    --order-id 19643

# Verify it runs as nonroot user
docker run --rm bi-processor:dhi id
# Expected output: uid=1000 gid=1000 groups=1000

# ============================================================================
# SECTION 3: DEPLOY WITH DOCKER COMPOSE
# ============================================================================

# Start the bi-processor service
docker compose -f compose.bi.yaml up -d bi-processor

# Check if it's running
docker compose -f compose.bi.yaml ps

# View logs (live, Ctrl+C to exit)
docker compose -f compose.bi.yaml logs -f bi-processor

# View logs from a specific time
docker compose -f compose.bi.yaml logs --since 2h bi-processor

# Stop the service
docker compose -f compose.bi.yaml down

# Restart the service
docker compose -f compose.bi.yaml restart bi-processor

# ============================================================================
# SECTION 4: RUN ETL & GENERATE REPORTS
# ============================================================================

# Generate a drift report (with refresh)
docker exec bi-processor python /app/bi_processor.py \
  --dialect mysql \
  --host [YOUR_DATABASE_HOST] \
  --user [YOUR_DB_USER] \
  --password '[YOUR_DB_PASSWORD]' \
  --database krypton_woosoo \
  --refresh \
  --report \
  --output /app/reports/drift_report_$(date +%Y%m%d_%H%M%S).json

# Generate report without refresh (faster)
docker exec bi-processor python /app/bi_processor.py \
  --dialect mysql \
  --host [YOUR_DATABASE_HOST] \
  --user [YOUR_DB_USER] \
  --password '[YOUR_DB_PASSWORD]' \
  --database krypton_woosoo \
  --report \
  --output /app/reports/drift_report_$(date +%Y%m%d_%H%M%S).json

# View generated reports
ls -lh reports/

# View report contents (pretty-print JSON)
cat reports/drift_report_*.json | jq '.'

# Extract specific metrics from report
cat reports/drift_report_*.json | jq '.metrics'

# Get high-severity issues only
cat reports/drift_report_*.json | jq '.drift_issues[] | select(.severity=="HIGH")'

# ============================================================================
# SECTION 5: QUERY BI VIEWS DIRECTLY
# ============================================================================

# Query 1: Get order reconciliation (replace order ID)
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
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
"

# Query 2: Find all HIGH-severity drift (last 24 hours)
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT 
  krypton_order_id,
  woosoo_order_id,
  issue_type_crosswalk,
  issue_type_amount,
  severity,
  detected_at
FROM bi_order_drift_detection
WHERE severity = 'HIGH' 
AND detected_at >= NOW() - INTERVAL 24 HOUR
ORDER BY detected_at DESC
LIMIT 20;
"

# Query 3: Check line-item mismatches for specific order
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT 
  krypton_menu_name,
  krypton_qty,
  woosoo_qty,
  item_reconciliation_status,
  qty_delta
FROM bi_line_item_reconciliation
WHERE krypton_order_id = 19643
AND item_reconciliation_status != 'MATCHED';
"

# Query 4: Dashboard - order counts by status
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT 
  crosswalk_status,
  amount_reconciliation,
  COUNT(*) AS order_count,
  SUM(krypton_total) AS total_amount,
  AVG(opened_delta_seconds) AS avg_time_delta
FROM bi_krypton_woosoo_order_fusion
GROUP BY crosswalk_status, amount_reconciliation;
"

# Query 5: Drift summary by severity
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT 
  severity,
  COUNT(*) AS issue_count,
  MIN(detected_at) AS first_detected,
  MAX(detected_at) AS last_detected
FROM bi_order_drift_detection
GROUP BY severity
ORDER BY FIELD(severity, 'HIGH', 'MEDIUM', 'LOW');
"

# ============================================================================
# SECTION 6: MAINTENANCE & TROUBLESHOOTING
# ============================================================================

# Check container health
docker compose -f compose.bi.yaml ps bi-processor

# View detailed container stats (CPU, memory)
docker stats bi-processor --no-stream

# Check logs for errors
grep -i error logs/bi_refresh.log

# Test database connection from container
docker run --rm \
  --env-file .env \
  bi-processor:dhi \
  python -c "
import mysql.connector
conn = mysql.connector.connect(
  host='[YOUR_DATABASE_HOST]',
  user='[YOUR_DB_USER]',
  password='[YOUR_DB_PASSWORD]',
  database='krypton_woosoo'
)
print('✓ Connection successful')
conn.close()
"

# Verify views exist in database
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT COUNT(*) as view_count 
FROM information_schema.views 
WHERE table_schema='krypton_woosoo' 
AND table_name LIKE 'bi_%';
"

# Redeploy SQL views (if they got deleted)
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo < krypton_woosoo_bi_views.sql

# Check report generation timestamps
stat reports/drift_report_*.json

# Clear old reports (keep last 7 days)
find reports/ -name "drift_report_*.json" -mtime +7 -delete

# View real-time logs while running report
docker compose -f compose.bi.yaml logs -f bi-processor &
docker exec bi-processor python /app/bi_processor.py --report

# ============================================================================
# SECTION 7: PERFORMANCE & MONITORING
# ============================================================================

# Get order reconciliation metrics
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT 
  COUNT(*) as total_orders,
  SUM(CASE WHEN woosoo_order_id IS NOT NULL THEN 1 ELSE 0 END) as linked,
  SUM(CASE WHEN woosoo_order_id IS NULL THEN 1 ELSE 0 END) as unlinked,
  ROUND(100.0 * SUM(CASE WHEN woosoo_order_id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 2) as link_pct,
  ROUND(AVG(ABS(opened_delta_seconds)), 2) as avg_time_delta_sec
FROM bi_krypton_woosoo_order_fusion;
"

# Count HIGH severity issues
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT COUNT(*) as high_severity_count 
FROM bi_order_drift_detection 
WHERE severity = 'HIGH';
"

# Get snapshot refresh status
mysql -h [YOUR_DATABASE_HOST] -u [YOUR_DB_USER] -p -D krypton_woosoo -e "
SELECT 
  snapshot_date,
  COUNT(*) as records,
  MIN(created_at) as oldest,
  MAX(created_at) as newest
FROM bi_order_reconciliation_snapshot
GROUP BY snapshot_date
ORDER BY snapshot_date DESC
LIMIT 7;
"

# ============================================================================
# SECTION 8: SETUP AUTOMATED DAILY REFRESH (Linux/Mac)
# ============================================================================

# Edit crontab
crontab -e

# Add this line (runs at 2 AM daily):
0 2 * * * cd /path/to/project && docker exec bi-processor python /app/bi_processor.py --dialect mysql --host [YOUR_DATABASE_HOST] --user [YOUR_DB_USER] --password '[YOUR_DB_PASSWORD]' --database krypton_woosoo --refresh --report --output /app/reports/drift_$(date +\%Y\%m\%d).json >> logs/cron.log 2>&1

# View crontab
crontab -l

# Remove crontab entry
crontab -r

# ============================================================================
# SECTION 9: IMAGE SECURITY VERIFICATION
# ============================================================================

# Check image details
docker inspect bi-processor:dhi | jq '.[] | {Architecture, Os, RootFS}'

# Verify nonroot user
docker run --rm bi-processor:dhi whoami
# Expected: nonroot

# Check image size reduction
docker images bi-processor:dhi --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}"

# Scan image for vulnerabilities (requires Trivy)
trivy image bi-processor:dhi

# ============================================================================
# NOTES
# ============================================================================
#
# Replace these placeholders with YOUR values:
#   [YOUR_DATABASE_HOST]    → your-db.example.com
#   [YOUR_DB_USER]          → bi_user
#   [YOUR_DB_PASSWORD]      → your_secure_password
#   [YOUR_...]              → Any placeholder
#
# Important:
#   - Keep .env file secure (don't commit to git)
#   - Use read-only database user for security
#   - Monitor logs regularly for errors
#   - Set up alerts for HIGH severity drift
#   - Rotate passwords regularly
#
# For more help:
#   - See QUICKSTART_DHI_BI.md for detailed guide
#   - See BI_SETUP_GUIDE.md for full reference
#   - See DHI_MIGRATION_ASSESSMENT.md for security details
#

