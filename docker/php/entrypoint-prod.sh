#!/usr/bin/env sh
set -eu

if [ "${APP_ENV:-}" = "production" ]; then
  php artisan package:discover --ansi || true
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

exec "$@"

