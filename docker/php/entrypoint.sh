#!/bin/sh
set -e
cd /var/www/html

until pg_isready -h "${DB_HOST:-pgsql}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-matocenu}" >/dev/null 2>&1; do
  echo "Čekám na PostgreSQL…"; sleep 2
done

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public/avatars

php artisan config:clear >/dev/null

# Only the main app container migrates (horizon & scheduler set RUN_MIGRATIONS=0).
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  php artisan migrate --force --no-interaction
  php artisan matocenu:sync-reference-data --no-interaction
fi

php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null
php artisan event:cache >/dev/null

exec "$@"
