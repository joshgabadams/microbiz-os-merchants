#!/bin/sh
set -e

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

php artisan config:clear
php artisan route:clear

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

if [ "$RUN_SEEDERS" = "true" ]; then
    php artisan db:seed --class="Database\Seeders\RbacSeeder" --force
    php artisan db:seed --class="Database\Seeders\PaymentsRbacSeeder" --force
    php artisan db:seed --class="Database\Seeders\GlAccountSeeder" --force
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
