#!/usr/bin/env bash

echo "🚀 Iniciando despliegue de Laravel..."

# Instalar dependencias
echo "📦 Instalando dependencias..."
composer install --no-dev --working-dir=/var/www/html

# Crear directorio de logs
mkdir -p /var/log/nginx
mkdir -p /var/cache/nginx

# Establecer permisos correctos
echo "📁 Configurando permisos..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Limpiar cachés existentes
echo "🧹 Limpiando cachés..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Verificar conexión a la base de datos
echo "🗄️ Verificando conexión a la base de datos..."
if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful!';"; then
    echo "✅ Conexión a base de datos exitosa"
else
    echo "❌ Error: No se puede conectar a la base de datos"
    echo "🔧 Intentando continuar con el despliegue..."
fi

# Ejecutar migraciones
echo "📊 Ejecutando migraciones..."
php artisan migrate --force

# Verificar que las migraciones se ejecutaron correctamente
if php artisan migrate:status | grep -q "Ran"; then
    echo "✅ Migraciones ejecutadas correctamente"
else
    echo "❌ Error en migraciones"
    exit 1
fi

# FORZAR EJECUCIÓN DE SEEDERS
echo "🌱 EJECUTANDO SEEDERS OBLIGATORIAMENTE..."
echo "⚠️  Esto creará datos de prueba en la base de datos"

# Intentar ejecutar seeders y capturar el resultado
if php artisan db:seed --force --class=DatabaseSeeder; then
    echo "✅ Seeders ejecutados exitosamente"
    echo "👥 Datos de prueba creados en la base de datos"
else
    echo "⚠️  Primera ejecución de seeders falló, intentando de nuevo..."
    # Segundo intento
    if php artisan db:seed --force; then
        echo "✅ Seeders ejecutados en el segundo intento"
    else
        echo "❌ Error: No se pudieron ejecutar los seeders"
        echo "🔍 Revisando estado de la base de datos..."
        php artisan tinker --execute="echo 'Usuarios: ' . App\Models\User::count(); echo 'Grupos: ' . App\Models\Grupo::count();"
        echo "⚠️  Continuando sin seeders..."
    fi
fi

# Optimizar para producción
echo "⚡ Optimizando para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

echo "🎉 Despliegue completado exitosamente!"
