#!/bin/bash
# Quick-start script for Krypton ↔ Woosoo BI Platform
# Supports both Docker and native setup

set -e

echo "=========================================="
echo "Krypton ↔ Woosoo BI Platform Setup"
echo "=========================================="

# Check for required commands
check_command() {
    if ! command -v $1 &> /dev/null; then
        echo "ERROR: $1 is not installed. Please install it first."
        exit 1
    fi
}

# Option 1: Docker Setup
if [ "$1" == "docker" ]; then
    echo "Setting up with Docker..."
    check_command "docker"
    check_command "docker-compose"
    
    echo "Building Docker images..."
    docker-compose build
    
    echo "Starting services..."
    docker-compose up -d
    
    echo "Waiting for databases to be healthy..."
    sleep 10
    
    echo "Verifying MySQL..."
    docker-compose exec -T mysql-bi mysql -u root -proot_password -e "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='krypton_woosoo' AND TABLE_NAME LIKE 'bi_%';" || echo "Views not yet created"
    
    echo ""
    echo "=========================================="
    echo "Docker setup complete!"
    echo "=========================================="
    echo "Access points:"
    echo "  MySQL BI: localhost:3306 (bi_user / bi_password)"
    echo "  PostgreSQL: localhost:5432 (bi_user / bi_password)"
    echo "  Grafana: http://localhost:3000 (admin / admin_password)"
    echo ""
    echo "To view logs:"
    echo "  docker-compose logs -f bi-processor"
    echo ""
    exit 0
fi

# Option 2: Native Setup
echo "Setting up native installation..."
check_command "python3"
check_command "pip"

# Read database configuration
read -p "Database dialect (mysql/postgresql): " DB_DIALECT
read -p "Database host [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}
read -p "Database user [bi_readonly]: " DB_USER
DB_USER=${DB_USER:-bi_readonly}
read -sp "Database password: " DB_PASSWORD
echo ""
read -p "Database name [krypton_woosoo]: " DB_NAME
DB_NAME=${DB_NAME:-krypton_woosoo}

# Validate database connection
echo "Validating database connection..."
if [ "$DB_DIALECT" == "mysql" ]; then
    check_command "mysql"
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD -e "SELECT 1;" > /dev/null || {
        echo "ERROR: Cannot connect to MySQL. Please check credentials."
        exit 1
    }
else
    check_command "psql"
    PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "SELECT 1;" > /dev/null || {
        echo "ERROR: Cannot connect to PostgreSQL. Please check credentials."
        exit 1
    }
fi

echo "Connection successful!"

# Install Python dependencies
echo "Installing Python dependencies..."
pip install -r requirements.txt

# Create directories
mkdir -p logs reports

# Create .env file
echo "Creating .env file..."
cat > .env << EOF
DB_DIALECT=$DB_DIALECT
DB_HOST=$DB_HOST
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
DB_NAME=$DB_NAME
REPORT_OUTPUT_DIR=./reports
LOG_OUTPUT_DIR=./logs
ALERT_ENABLED=false
EOF

# Deploy SQL views
echo "Deploying BI views to database..."
if [ "$DB_DIALECT" == "mysql" ]; then
    mysql -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME < krypton_woosoo_bi_views.sql
else
    PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f krypton_woosoo_bi_views.sql
fi

# Test ETL processor
echo "Testing ETL processor..."
python3 bi_processor.py \
    --dialect $DB_DIALECT \
    --host $DB_HOST \
    --user $DB_USER \
    --password $DB_PASSWORD \
    --database $DB_NAME \
    --report \
    --output reports/test_report.json

if [ -f reports/test_report.json ]; then
    echo "✓ ETL test successful!"
    echo "Sample report: reports/test_report.json"
else
    echo "✗ ETL test failed. Check logs/bi_refresh.log"
fi

# Setup cron job
read -p "Install daily cron job (2 AM)? (y/n): " INSTALL_CRON
if [ "$INSTALL_CRON" == "y" ]; then
    CRON_CMD="0 2 * * * cd $(pwd) && python3 bi_processor.py --dialect $DB_DIALECT --host $DB_HOST --user $DB_USER --password $DB_PASSWORD --database $DB_NAME --refresh --report --output reports/drift_report_\$(date +\%Y\%m\%d).json >> logs/bi_refresh.log 2>&1"
    (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
    echo "✓ Cron job installed"
    crontab -l | grep "bi_processor"
fi

echo ""
echo "=========================================="
echo "Native setup complete!"
echo "=========================================="
echo "Configuration saved to: .env"
echo "Test report: reports/test_report.json"
echo "Logs: logs/bi_refresh.log"
echo ""
echo "Next steps:"
echo "  1. Review reports/test_report.json"
echo "  2. Connect your BI tool (Tableau, Looker, Grafana) to $DB_DIALECT://$DB_HOST/$DB_NAME"
echo "  3. Query views: bi_krypton_woosoo_order_fusion, bi_order_drift_detection, etc."
echo ""

