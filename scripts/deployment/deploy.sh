#!/usr/bin/env bash
# =============================================================================
# Woosoo Pi5 — One-Command Deploy
# =============================================================================
# Usage (from the Pi5 console, as root):
#   sudo bash scripts/deployment/deploy.sh
#
# What it does:
#   1. Pulls latest code from git (staging branch by default)
#   2. Runs apply-woosoo-config.sh to enforce all runtime config into .env
#   3. Rebuilds Docker images (no stale cache)
#   4. Starts all services
#   5. Warms Laravel caches
#   6. Shows service status
#
# First-time setup? Read docs/DOCKER_DEPLOYMENT.md first — you need
# /etc/woosoo/woosoo.env in place before running this.
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
BRANCH="${WOOSOO_DEPLOY_BRANCH:-staging}"
CONFIG_SCRIPT="$SCRIPT_DIR/apply-woosoo-config.sh"
COMPOSE_CMD="${WOOSOO_DOCKER_COMPOSE:-docker compose -f compose.yaml}"
APP_SERVICE="${WOOSOO_APP_SERVICE:-app}"

# ── Guards ────────────────────────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
  echo "ERROR: Run as root: sudo bash scripts/deployment/deploy.sh"
  exit 1
fi

if [[ ! -f /etc/woosoo/woosoo.env ]]; then
  echo "ERROR: /etc/woosoo/woosoo.env not found."
  echo "  This is the Pi5 configuration file. See docs/DOCKER_DEPLOYMENT.md."
  exit 1
fi

if [[ ! -f "$CONFIG_SCRIPT" ]]; then
  echo "ERROR: apply-woosoo-config.sh not found at $CONFIG_SCRIPT"
  exit 1
fi

cd "$REPO_ROOT"

echo "========================================"
echo "  Woosoo Pi5 Deploy"
echo "  Branch: $BRANCH"
echo "  Path:   $REPO_ROOT"
echo "========================================"
echo

# ── Step 1: Pull latest code ──────────────────────────────────────────────────
echo ">>> [1/5] Pulling latest code from origin/$BRANCH ..."
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  git fetch origin
  git checkout "$BRANCH"
  git reset --hard "origin/$BRANCH"
  echo "OK: Code updated to $(git rev-parse --short HEAD)"
else
  echo "WARNING: Not a git repo. Skipping git pull — deploy from current files."
fi
echo

# ── Step 2: Apply config (writes correct values into .env) ───────────────────
echo ">>> [2/5] Applying Pi5 config (apply-woosoo-config.sh) ..."
# Run with WOOSOO_RESTART_DOCKER=false — we control Docker below for better output
WOOSOO_RESTART_DOCKER=false bash "$CONFIG_SCRIPT"
echo "OK: Config applied — .env is authoritative for this host"
echo

# ── Step 3: Build Docker images ───────────────────────────────────────────────
echo ">>> [3/5] Building Docker images ..."
$COMPOSE_CMD build
echo "OK: Images built"
echo

# ── Step 4: Start / restart services ─────────────────────────────────────────
echo ">>> [4/5] Starting services ..."
$COMPOSE_CMD up -d --remove-orphans
echo "OK: Services started"
echo

# ── Step 5: Warm Laravel caches ──────────────────────────────────────────────
echo ">>> [5/5] Warming Laravel caches ..."
# Wait for PHP-FPM to be ready (up to 3 min on first boot)
echo "  Waiting for app service..."
WAIT_ATTEMPTS=90
WAIT_DELAY=2
for i in $(seq 1 "$WAIT_ATTEMPTS"); do
  if $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan --version >/dev/null 2>&1; then
    echo "  OK: app service ready"
    break
  fi
  if [[ "$i" -eq "$WAIT_ATTEMPTS" ]]; then
    echo "  WARNING: app service not ready after ${WAIT_ATTEMPTS}x${WAIT_DELAY}s — skipping cache warm."
    echo "  Run manually: $COMPOSE_CMD exec $APP_SERVICE php artisan config:cache"
    echo
    # Don't exit — services may still be starting
    break
  fi
  sleep "$WAIT_DELAY"
done

if $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan --version >/dev/null 2>&1; then
  $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan config:clear  || true
  $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan cache:clear   || true
  $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan route:clear   || true
  $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan view:clear    || true
  $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan config:cache  || true
  $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan route:cache   || true
  $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan view:cache    || true
  echo "OK: Caches warmed"
fi
echo

# ── Summary ───────────────────────────────────────────────────────────────────
echo "========================================"
echo "  Deploy complete"
echo "========================================"
$COMPOSE_CMD ps
echo

# Load config to show the URLs
source /etc/woosoo/woosoo.env 2>/dev/null || true
WOOSOO_HOST="${WOOSOO_HOST:-<host>}"
WOOSOO_SCHEME="${WOOSOO_SCHEME:-https}"

echo "  Admin panel : ${WOOSOO_SCHEME}://${WOOSOO_HOST}"
echo "  Tablet PWA  : ${WOOSOO_SCHEME}://${WOOSOO_HOST}:4443"
echo
echo "  Tablet DNS must point to: $(ip -4 addr | grep -Eo '192\.[0-9]+\.[0-9]+\.[0-9]+' | head -n1 || echo '<server-ip>')"
echo
