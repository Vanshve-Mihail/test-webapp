#!/bin/bash
PROJECT_NAME="winter_project"
DOCKER_COMPOSE_FILE="docker-compose.yml"
ENV_FILE=".env"

echo "Остановка и удаление старых контейнеров"
docker-compose -f $DOCKER_COMPOSE_FILE down
echo "Удаление старых томов..."
docker volume rm ${PROJECT_NAME}_db_data || true
echo "Сборка"
docker-compose -f $DOCKER_COMPOSE_FILE up --build -d
echo "Проверка статуса контейнеров"
docker ps
echo "Настройка .env файла"
if [ ! -f "$ENV_FILE" ]; then
    docker exec winter_app php artisan winter:env
fi
echo "Создание папок и настройка прав доступа"
docker exec winter_app bash -c '
mkdir -p /var/www/html/storage/logs \
&& mkdir -p /var/www/html/storage/framework/cache/data \
&& mkdir -p /var/www/html/storage/framework/sessions \
&& mkdir -p /var/www/html/storage/framework/views \
&& chown -R www-data:www-data /var/www/html/storage \
&& find /var/www/html/storage -type d -exec chmod 775 {} \; \
&& find /var/www/html/storage -type f -exec chmod 664 {} \;
'
echo "Очистка кэша"
docker exec winter_app php artisan config:clear
docker exec winter_app php artisan cache:clear
docker exec winter_app php artisan winter:up || true
echo "Откройте http://localhost:8080"
