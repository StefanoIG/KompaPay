#!/usr/bin/env bash

echo "Iniciando despliegue de Laravel..."

# Instalar dependencias
echo "Instalando dependencias..."
composer install --no-dev --working-dir=/var/www/html

# Crear directorio de logs
mkdir -p /var/log/nginx
mkdir -p /var/cache/nginx

# Establecer permisos correctos
echo "Configurando permisos..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Limpiar cachés existentes
echo "Limpiando caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Verificar conexión a la base de datos
echo "Verificando conexion a la base de datos..."
if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful!';"; then
    echo "Conexion a base de datos exitosa"
else
    echo "Error: No se puede conectar a la base de datos"
    echo "Intentando continuar con el despliegue..."
fi

# Ejecutar migraciones FORZADAMENTE
echo "EJECUTANDO MIGRACIONES FORZADAMENTE..."
php artisan migrate --force --verbose

# Verificar que las migraciones se ejecutaron correctamente
echo "Verificando estado de migraciones..."
php artisan migrate:status

# FORZAR EJECUCION DE SEEDERS
echo "EJECUTANDO SEEDERS OBLIGATORIAMENTE..."
echo "Esto creara datos de prueba en la base de datos"

# Intentar ejecutar seeders y capturar el resultado
echo "Primer intento de seeders..."
if php artisan db:seed --force --class=DatabaseSeeder --verbose; then
    echo "Seeders ejecutados exitosamente"
    echo "Datos de prueba creados en la base de datos"
else
    echo "Primera ejecucion de seeders fallo, intentando de nuevo..."
    # Segundo intento
    echo "Segundo intento de seeders..."
    if php artisan db:seed --force --verbose; then
        echo "Seeders ejecutados en el segundo intento"
    else
        echo "Error: No se pudieron ejecutar los seeders"
        echo "Revisando estado de la base de datos..."
        php artisan tinker --execute="echo 'Usuarios: ' . App\Models\User::count(); echo 'Grupos: ' . App\Models\Grupo::count();"
        echo "Continuando sin seeders..."
    fi
fi

# Optimizar para producción
echo "Optimizando para produccion..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

echo "Despliegue completado exitosamente!"
