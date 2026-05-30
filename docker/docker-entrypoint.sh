#!/usr/bin/env sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# Create storage symlink if missing (public/storage → storage/app/public)
php artisan storage:link --quiet 2>/dev/null || true

# Build Vite assets only when needed. Runs only for the php-fpm process (not
# for queue, scheduler, or reverb which share this entrypoint via CMD override).
# node_modules comes from the image's anonymous volume (compose.yaml), so this
# works identically on Linux (Pi) and Docker Desktop Windows.
#
# Build triggers ONLY when:
#   - public/build is missing/empty (fresh checkout — self-healing), OR
#   - WOOSOO_FORCE_VITE_BUILD=true (deploy of new code forces fresh assets).
# A plain container restart (crash/OOM/reboot) with assets already present
# SKIPS the build, so recovery is fast instead of a ~1-minute rebuild.
if [ "${1:-}" = "php-fpm" ]; then
  if [ "${WOOSOO_FORCE_VITE_BUILD:-false}" = "true" ] \
     || [ ! -d public/build ] \
     || [ -z "$(ls -A public/build 2>/dev/null)" ]; then
    echo "[entrypoint] Building Vite assets..."
    npm run build && echo "[entrypoint] Vite build complete." \
      || echo "[entrypoint] WARNING: Vite build failed; serving existing assets"
  else
    echo "[entrypoint] public/build present — skipping Vite build (set WOOSOO_FORCE_VITE_BUILD=true to force)."
  fi
fi

exec "$@"
