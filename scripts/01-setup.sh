#!/bin/bash

echo "Ejecutando setup adicional..."

# Actualizar el sistema
apt-get update && apt-get install -y \
    nginx \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Crear directorios necesarios
mkdir -p /var/log/nginx
mkdir -p /var/cache/nginx

# Configurar permisos para Laravel
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Limpiar caches de Laravel
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimizar para produccion
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones si es necesario
if [ "$APP_ENV" = "production" ]; then
    echo "Ejecutando migraciones en produccion..."
    php artisan migrate --force --verbose
    
    echo "EJECUTANDO SEEDERS EN SETUP..."
    php artisan db:seed --force --verbose
fi

echo "Setup completado exitosamente!"
