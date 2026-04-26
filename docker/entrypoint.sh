#!/bin/sh
set -e

# Bootstrap only runs for the main app container (php-fpm).
# Worker containers (queue, scheduler, reverb) receive a different CMD
# and skip this block, executing their command directly.
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Clearing config cache..."
    php artisan config:clear

    echo "[entrypoint] Running database migrations..."
    php artisan migrate --force --no-interaction

    echo "[entrypoint] Caching config, routes, views..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "[entrypoint] Bootstrap complete."
fi

exec "$@"
