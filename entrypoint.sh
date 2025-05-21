#!/bin/bash
if grep -q "^APP_KEY=" /var/www/html/.env && [ -z "$(grep '^APP_KEY=' /var/www/html/.env | cut -d'=' -f2)" ]; then
    php artisan key:generate
fi

mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
sudo chown -R $USER:www-data /path/to/your/project/storage
find /var/www/html/storage -type d -exec chmod 775 {} \;
find /var/www/html/storage -type f -exec chmod 664 {} \;
exec apache2-foreground