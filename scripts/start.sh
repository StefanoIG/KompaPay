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

# Iniciar nginx en segundo plano
nginx -g "daemon off;" &

# Iniciar PHP-FPM en primer plano
exec php-fpm
