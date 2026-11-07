#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be set in the Render environment variables." >&2
    exit 1
fi

export PORT="${PORT:-10000}"

cd /var/www/html

# Cache runtime configuration only after Render injects environment variables.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
