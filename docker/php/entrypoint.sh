#!/bin/sh
# AT.urtg.uz — backend container entrypoint (backend, queue-worker, scheduler).
set -e
cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    if command -v composer >/dev/null 2>&1; then
        echo "[entrypoint] vendor/ missing — running composer install"
        composer install --no-interaction --prefer-dist
    else
        echo "[entrypoint] vendor/ missing and composer is not available" >&2
        exit 1
    fi
fi

if [ "${APP_OPTIMIZE:-false}" = "true" ]; then
    php artisan optimize
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
