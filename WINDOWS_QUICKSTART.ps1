# DHI-HARDENED BI PLATFORM - WINDOWS POWERSHELL QUICKSTART
# Run commands one at a time (copy entire block, paste in PowerShell)

# ============================================================================
# STEP 1: Build Docker Image (First Time Only)
# ============================================================================

docker build -f Dockerfile.processor.dhi -t bi-processor:dhi .

# Verify it built:
docker images | Select-String "bi-processor"

# ============================================================================
# STEP 2: Start the Service (Docker Compose)
# ============================================================================

docker-compose up -d bi-processor

# Check if it's running:
docker-compose ps

# View logs:
docker-compose logs -f bi-processor

# ============================================================================
# STEP 3: Deploy SQL Views (First Time Only)
# ============================================================================

# For Krypton POS database (192.168.100.7:3308):
mysql -h 192.168.100.7 -P 3308 -u woosoo_pos -ppassword krypton_woosoo < krypton_woosoo_bi_views.sql

# Verify views were deployed:
mysql -h 192.168.100.7 -P 3308 -u woosoo_pos -ppassword krypton_woosoo -e "SHOW VIEWS LIKE 'bi_%';"

# ============================================================================
# STEP 4: Generate Your First Drift Report
# ============================================================================

# PowerShell: Single-line version (copy entire block at once)
docker exec bi-processor python /app/bi_processor.py --dialect mysql --host 192.168.100.7 --user woosoo_pos --password "password" --database krypton_woosoo --refresh --report --output /app/reports/drift_report.json

# View the report:
cat reports/drift_report.json | ConvertFrom-Json | ConvertTo-Json

# ============================================================================
# STEP 5: Query the Views
# ============================================================================

# Check order 19643 reconciliation (replace 19643 with your order ID):
mysql -h 192.168.100.7 -P 3308 -u woosoo_pos -ppassword krypton_woosoo -e "SELECT krypton_order_id, woosoo_order_id, crosswalk_status, amount_reconciliation, severity FROM bi_krypton_woosoo_order_fusion WHERE krypton_order_id = 19643 LIMIT 1;"

# Find all HIGH-severity drift:
mysql -h 192.168.100.7 -P 3308 -u woosoo_pos -ppassword krypton_woosoo -e "SELECT krypton_order_id, woosoo_order_id, issue_type_crosswalk, issue_type_amount, severity FROM bi_order_drift_detection WHERE severity = 'HIGH' LIMIT 10;"

# ============================================================================
# STEP 6: View Logs
# ============================================================================

# Follow logs live:
docker-compose logs -f bi-processor

# View report file:
ls -lh reports/

# ============================================================================
# TROUBLESHOOTING
# ============================================================================

# Container won't start?
docker-compose logs bi-processor

# Test database connection:
mysql -h 192.168.100.7 -P 3308 -u woosoo_pos -ppassword -e "SELECT 1;"

# Views not found?
mysql -h 192.168.100.7 -P 3308 -u woosoo_pos -ppassword krypton_woosoo -e "SHOW TABLES LIKE 'bi_%';"

# Stop the service:
docker-compose down

# Restart:
docker-compose up -d bi-processor

# ============================================================================
# KEY DIFFERENCES FROM BASH/LINUX
# ============================================================================
# - Use ` (backtick) for line continuation in PowerShell, not \
# - Use -e for mysql commands instead of pipes
# - Use " instead of ' for strings with spaces
# - Use cat and ConvertFrom-Json for JSON viewing (or use notepad)
# - Use ls instead of find for directory listing

