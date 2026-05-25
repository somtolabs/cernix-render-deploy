#!/usr/bin/env sh
set -eu

cd /var/www/html

APP_PORT="${PORT:-10000}"

case "$APP_PORT" in
    ''|*[!0-9]*)
        echo "Invalid PORT value '$APP_PORT'. Falling back to 10000."
        APP_PORT=10000
        ;;
esac

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

php artisan storage:link || true
php artisan migrate --force

if [ "${RENDER_SKIP_SEED:-false}" != "true" ]; then
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="$APP_PORT"
