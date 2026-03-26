#!/usr/bin/env sh
set -eu

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache || true
chown -R www-data:www-data storage bootstrap/cache || true

php artisan storage:link --ansi || true

if [ "${APP_ENV:-}" = "production" ]; then
  php artisan package:discover --ansi || true
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

exec "$@"
