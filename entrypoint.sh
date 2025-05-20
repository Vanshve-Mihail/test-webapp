#!/bin/bash
if [ ! -f "/var/www/html/.env" ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

if grep -q "^APP_KEY=" /var/www/html/.env && [ -z "$(grep '^APP_KEY=' /var/www/html/.env | cut -d'=' -f2)" ]; then
    php artisan key:generate --force
fi

mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
chown -R www-data:www-data /var/www/html/storage
find /var/www/html/storage -type d -exec chmod 775 {} \;
find /var/www/html/storage -type f -exec chmod 664 {} \;
php artisan config:clear
php artisan cache:clear
php artisan winter:up || true
exec apache2-foreground
