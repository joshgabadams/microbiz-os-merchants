#!/bin/sh
set -e

php artisan config:clear
php artisan route:clear

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
