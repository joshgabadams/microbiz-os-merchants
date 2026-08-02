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

# Only seeders confirmed safe to re-run on every restart are included here
# (both use firstOrCreate/syncWithoutDetaching, never plain create() or
# truncate()). Deliberately NOT included:
#   - the default `db:seed` / DatabaseSeeder, since it calls
#     User::factory()->create() with a fixed unique email and will crash
#     with a duplicate-key error on any restart after the first.
#   - GlAccountSeeder, since it truncates gl_accounts and reinserts with
#     fresh auto-increment IDs -- safe once, but running it again would
#     silently break the gl_account_id foreign keys on existing GlJournal
#     rows. Run that one manually, deliberately, only when you actually
#     mean to reset the chart of accounts.
if [ "$RUN_SEEDERS" = "true" ]; then
    php artisan db:seed --class="Database\Seeders\RbacSeeder" --force
    php artisan db:seed --class="Database\Seeders\PaymentsRbacSeeder" --force
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
