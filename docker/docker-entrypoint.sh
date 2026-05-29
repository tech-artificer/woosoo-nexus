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

# Build Vite assets on web server startup so public/build/ is always in sync
# with the current source tree. Runs only for the php-fpm process (not for
# queue, scheduler, or reverb which share this entrypoint via CMD override).
# node_modules comes from the image's anonymous volume (compose.yaml), so
# this works identically on Linux (Pi) and Docker Desktop Windows.
if [ "${1:-}" = "php-fpm" ]; then
  echo "[entrypoint] Building Vite assets..."
  npm run build && echo "[entrypoint] Vite build complete." \
    || echo "[entrypoint] WARNING: Vite build failed; serving existing assets from nexus_build volume"
fi

exec "$@"
