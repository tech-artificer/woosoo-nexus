#!/usr/bin/env bash
set -euo pipefail

NEXUS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TABLET_DIR="${TABLET_DIR:-$(cd "$NEXUS_DIR/.." && pwd)/tablet-ordering-pwa}"

echo "=== Nexus repository ==="
cd "$NEXUS_DIR"
echo "Path: $NEXUS_DIR"
echo "Branch: $(git branch --show-current)"
echo "Commit: $(git rev-parse --short HEAD)"
echo "Status:"
git status --short

if [ -n "$(git status --porcelain)" ] && [ "${ALLOW_DIRTY:-0}" != "1" ]; then
  echo "Nexus working tree is dirty. Commit, stash, or rerun with ALLOW_DIRTY=1."
  exit 1
fi

echo ""
echo "=== Tablet repository ==="
cd "$TABLET_DIR"
echo "Path: $TABLET_DIR"
echo "Branch: $(git branch --show-current)"
echo "Commit: $(git rev-parse --short HEAD)"
echo "Status:"
git status --short

if [ -n "$(git status --porcelain)" ] && [ "${ALLOW_DIRTY:-0}" != "1" ]; then
  echo "Tablet working tree is dirty. Commit, stash, or rerun with ALLOW_DIRTY=1."
  exit 1
fi

echo ""
echo "=== Compose tablet-pwa config ==="
cd "$NEXUS_DIR"
docker compose config | sed -n '/tablet-pwa:/,/^[a-zA-Z0-9_-]*:/p'

echo ""
echo "=== Effective PUBLIC_HOST ==="
grep -E '^PUBLIC_HOST=' .env .env.docker 2>/dev/null || true

echo ""
echo "=== Effective tablet Dockerfile ==="
echo "TABLET_DOCKERFILE=${TABLET_DOCKERFILE:-Dockerfile}"
