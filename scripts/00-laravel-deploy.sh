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

# Ejecutar seeders si existen
echo "🌱 Ejecutando seeders..."
php artisan db:seed --force || echo "No seeders o seeding falló, continuando..."

# Optimizar para producción
echo "⚡ Optimizando para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

echo "🎉 Despliegue completado exitosamente!"
