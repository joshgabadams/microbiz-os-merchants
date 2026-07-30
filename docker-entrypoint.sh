#!/bin/sh
set -e

# If a command was passed to `docker run` (e.g. `php artisan key:generate --show`),
# run exactly that instead of the default startup sequence below.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

php artisan config:clear
php artisan route:clear

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
