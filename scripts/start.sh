#!/bin/bash

echo "Starting Laravel application..."

# Verificar que el directorio de trabajo es correcto
cd /var/www/html

# Crear enlace simbólico para storage si no existe
if [ ! -L "public/storage" ]; then
    php artisan storage:link
fi

# Ejecutar setup adicional
if [ -f "/var/www/html/scripts/01-setup.sh" ]; then
    bash /var/www/html/scripts/01-setup.sh
fi

# Verificar configuración de nginx
nginx -t

# Crear directorio de logs si no existe
mkdir -p /var/log/nginx

# Iniciar nginx en segundo plano
echo "Starting nginx..."
nginx -g "daemon off;" &

# Esperar un momento para que nginx inicie
sleep 2

# Verificar que nginx está corriendo
if ! pgrep nginx > /dev/null; then
    echo "Error: nginx failed to start"
    exit 1
fi

echo "nginx started successfully"

# Iniciar PHP-FPM en primer plano
echo "Starting PHP-FPM..."
exec php-fpm
