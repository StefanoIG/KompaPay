#!/bin/bash

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

# Limpiar cachés de Laravel
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones si es necesario
if [ "$APP_ENV" = "production" ]; then
    php artisan migrate --force
fi

echo "Setup completed successfully!"
