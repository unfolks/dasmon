#!/bin/sh
set -eu

: "${APP_KEY:?APP_KEY belum diisi di .env.production. Jalankan artisan key:generate --show terlebih dahulu.}"

mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec "$@"
