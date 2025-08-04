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

# Copiar configuración personalizada de nginx
if [ -f "/var/www/html/conf/nginx/nginx-site.conf" ]; then
    echo "Copying custom nginx configuration..."
    cp /var/www/html/conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf || echo "Failed to copy to sites-available"
    # Try copying to conf.d directory (create if needed)
    mkdir -p /etc/nginx/conf.d
    cp /var/www/html/conf/nginx/nginx-site.conf /etc/nginx/conf.d/default.conf || echo "Failed to copy to conf.d"
fi

# Verificar configuración de nginx
echo "Testing nginx configuration..."
nginx -t
if [ $? -ne 0 ]; then
    echo "Nginx configuration test failed!"
    exit 1
fi

# Crear directorio de logs si no existe
mkdir -p /var/log/nginx

# Verificar que PHP-FPM está configurado para puerto 9000
echo "Checking PHP-FPM configuration..."
php-fpm -t

# Iniciar nginx en segundo plano
echo "Starting nginx..."
nginx -g "daemon off;" &

# Esperar un momento para que nginx inicie
sleep 3

# Verificar que nginx está corriendo
if ! pgrep nginx > /dev/null; then
    echo "Error: nginx failed to start"
    cat /var/log/nginx/error.log 2>/dev/null || echo "No nginx error log found"
    exit 1
fi

echo "nginx started successfully on port 80"

# Iniciar PHP-FPM en primer plano
echo "Starting PHP-FPM on port 9000..."
exec php-fpm
