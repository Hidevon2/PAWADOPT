#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be configured in the Render environment." >&2
    exit 1
fi

php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=RenderStaffSeeder --force
php artisan optimize

exec apache2-foreground
