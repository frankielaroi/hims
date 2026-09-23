#!/usr/bin/env bash
#
# App Service startup command:  bash /home/site/wwwroot/.azure/startup.sh
#
# Runs on every container start (deploy, restart, scale-out) before php-fpm
# begins serving traffic.

APP_ROOT=/home/site/wwwroot
STORAGE_ROOT="${LARAVEL_STORAGE_PATH:-$APP_ROOT/storage}"

echo "[startup] Installing nginx site config"
cp "$APP_ROOT/.azure/nginx.conf" /etc/nginx/sites-available/default
service nginx reload

echo "[startup] Preparing storage at $STORAGE_ROOT"
mkdir -p \
    "$STORAGE_ROOT/app/private" \
    "$STORAGE_ROOT/app/public" \
    "$STORAGE_ROOT/framework/cache/data" \
    "$STORAGE_ROOT/framework/sessions" \
    "$STORAGE_ROOT/framework/views" \
    "$STORAGE_ROOT/logs"

if [ "${DB_CONNECTION:-}" = "sqlite" ] && [ -n "${DB_DATABASE:-}" ] && [ ! -f "$DB_DATABASE" ]; then
    echo "[startup] Creating SQLite database at $DB_DATABASE"
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
fi

cd "$APP_ROOT" || exit 1

echo "[startup] Running migrations"
php artisan migrate --force --no-interaction || echo "[startup] WARNING: migrations failed - check the log stream"

echo "[startup] Caching config, routes, views and events"
php artisan optimize:clear --no-interaction
php artisan optimize --no-interaction

echo "[startup] Done"
