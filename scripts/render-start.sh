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

php artisan optimize:clear || true
php artisan view:clear || true

php artisan storage:link || true

# Production activity must live in Render PostgreSQL. Refuse to boot with the
# ephemeral container filesystem as the database.
if [ "${APP_ENV:-production}" = "production" ]; then
    if [ "${DB_CONNECTION:-}" != "pgsql" ]; then
        echo "CERNIX production requires DB_CONNECTION=pgsql."
        exit 1
    fi

    if [ -z "${DATABASE_URL:-${DB_URL:-}}" ]; then
        echo "CERNIX production requires DATABASE_URL or DB_URL for Render PostgreSQL."
        exit 1
    fi

    if [ -z "${APP_KEY:-}" ]; then
        echo "CERNIX production requires APP_KEY."
        exit 1
    fi

    if [ -z "${APP_JWT_SECRET:-${JWT_SECRET:-}}" ]; then
        echo "CERNIX production requires APP_JWT_SECRET or JWT_SECRET."
        exit 1
    fi
fi

# Runtime records must never be deleted or recreated during startup.
php artisan migrate --force
php artisan cernix:repair-baseline --force
php artisan cernix:registration-status

if [ "${APP_ENV:-production}" != "production" ] \
    && [ "${RENDER_SKIP_SEED:-true}" != "true" ] \
    && [ "${CERNIX_SEED_ON_BOOT:-false}" = "true" ]; then
    echo "Running explicitly enabled insert-only seeders."
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="$APP_PORT"
