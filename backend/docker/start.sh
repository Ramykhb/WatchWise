#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be set in the Render environment variables." >&2
    exit 1
fi

export PORT="${PORT:-10000}"

# The bundled Reverb process is private to this container. Apache exposes its
# WebSocket endpoint on the same public port as the HTTP API.
export REVERB_HOST=127.0.0.1
export REVERB_PORT=18080
export REVERB_SCHEME=http
export REVERB_SERVER_HOST=127.0.0.1
export REVERB_SERVER_PORT=18080

if [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]; then
    export REVERB_PUBLIC_HOST="${REVERB_PUBLIC_HOST:-$RENDER_EXTERNAL_HOSTNAME}"
    export REVERB_PUBLIC_PORT="${REVERB_PUBLIC_PORT:-443}"
    export REVERB_PUBLIC_SCHEME="${REVERB_PUBLIC_SCHEME:-https}"
fi

cd /var/www/html

# Cache runtime configuration only after Render injects environment variables.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisor/watchwise.conf
