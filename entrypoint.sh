#!/bin/bash

# Копируем .env.example в .env, если .env отсутствует
if [ ! -f "/var/www/html/.env" ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Генерируем APP_KEY, если он отсутствует или пустой
if grep -q "^APP_KEY=" /var/www/html/.env && [ -z "$(grep '^APP_KEY=' /var/www/html/.env | cut -d'=' -f2)" ]; then
    php artisan key:generate --force
fi

# Создание необходимых директорий
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views

# Настройка прав доступа
chown -R www-data:www-data /var/www/html/storage
find /var/www/html/storage -type d -exec chmod 775 {} \;
find /var/www/html/storage -type f -exec chmod 664 {} \;

# Очищаем кэш конфигурации
php artisan config:clear
php artisan cache:clear

# Выполняем миграции (если база данных готова)
php artisan winter:up || true

# Запускаем Apache в foreground
exec apache2-foreground
