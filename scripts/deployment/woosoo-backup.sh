#!/usr/bin/env bash
set -euo pipefail

CONFIG_FILE="/etc/woosoo/woosoo.env"

if [[ ! -f "$CONFIG_FILE" ]]; then
  echo "Missing $CONFIG_FILE"
  exit 1
fi

set -a
source "$CONFIG_FILE"
set +a

WOOSOO_DOCKER_COMPOSE="${WOOSOO_DOCKER_COMPOSE:-docker compose}"
WOOSOO_NEXUS_PATH="${WOOSOO_NEXUS_PATH:-/opt/woosoo/woosoo-nexus}"
WOOSOO_BACKUP_DIR="${WOOSOO_BACKUP_DIR:-/opt/woosoo/backups}"
MYSQL_SERVICE="${WOOSOO_MYSQL_SERVICE:-mysql}"
DB_NAME="${WOOSOO_DB_DATABASE:-woosoo}"
DB_USER="${WOOSOO_DB_USERNAME:-woosoo}"
DB_PASS="${WOOSOO_DB_PASSWORD:-change_this_password}"

mkdir -p "$WOOSOO_BACKUP_DIR/db"

if [[ ! -d "$WOOSOO_NEXUS_PATH" ]]; then
  echo "Nexus path missing: $WOOSOO_NEXUS_PATH"
  exit 1
fi

cd "$WOOSOO_NEXUS_PATH"

BACKUP_FILE="$WOOSOO_BACKUP_DIR/db/${DB_NAME}_$(date +%F_%H%M%S).sql.gz"

echo "Creating DB backup: $BACKUP_FILE"

if $WOOSOO_DOCKER_COMPOSE exec -T "$MYSQL_SERVICE" sh -lc 'command -v mariadb-dump >/dev/null 2>&1'; then
  DUMP_CMD="mariadb-dump"
else
  DUMP_CMD="mysqldump"
fi

$WOOSOO_DOCKER_COMPOSE exec -T "$MYSQL_SERVICE" \
  "$DUMP_CMD" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  | gzip > "$BACKUP_FILE"

echo "Backup complete: $BACKUP_FILE"

echo "Deleting backups older than 14 days..."
find "$WOOSOO_BACKUP_DIR/db" -name "*.sql.gz" -type f -mtime +14 -delete

echo "Remaining backups:"
ls -lh "$WOOSOO_BACKUP_DIR/db" | tail || true
