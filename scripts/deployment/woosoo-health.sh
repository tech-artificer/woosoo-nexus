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
WOOSOO_SCHEME="${WOOSOO_SCHEME:-https}"
WOOSOO_REVERB_APP_KEY="${WOOSOO_REVERB_APP_KEY:-}"

echo "=== Woosoo Health Check ==="
echo

echo "[1] Expected IP address"
ip -4 addr | grep -F "${WOOSOO_SERVER_IP}" || echo "WARNING: Expected IP not found: ${WOOSOO_SERVER_IP}"

echo
echo "[2] DNS local resolution"
dig "$WOOSOO_HOST" @127.0.0.1 +short || true

echo
echo "[3] dnsmasq status"
systemctl is-active dnsmasq || true

echo
echo "[4] Host port listeners"
ss -lntup | awk '$5 ~ /:(53|80|443)$/ || $5 ~ /:(53|80|443)\s/ {print}' || true

echo
echo "[5] HTTPS check"
curl -k -I --max-time 10 "${WOOSOO_SCHEME}://${WOOSOO_HOST}" || true

echo
echo "[6] Reverb proxy route check"
if [[ -n "$WOOSOO_REVERB_APP_KEY" ]]; then
  curl -k -I --max-time 10 "${WOOSOO_SCHEME}://${WOOSOO_HOST}/app/${WOOSOO_REVERB_APP_KEY}" || true
else
  echo "WOOSOO_REVERB_APP_KEY not set; skipping Reverb proxy route check"
fi

echo
echo "[7] Docker containers"
if [[ -d "$WOOSOO_NEXUS_PATH" ]]; then
  cd "$WOOSOO_NEXUS_PATH"
  $WOOSOO_DOCKER_COMPOSE ps || true
else
  echo "Nexus path missing: $WOOSOO_NEXUS_PATH"
fi

echo
echo "[8] Disk"
df -h

echo
echo "[9] Memory"
free -h

echo
echo "[10] Temperature"
if command -v vcgencmd >/dev/null 2>&1; then
  vcgencmd measure_temp || true
else
  echo "vcgencmd not installed"
fi

echo
echo "[11] Recent dnsmasq logs"
journalctl -u dnsmasq -n 30 --no-pager || true
