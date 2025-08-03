# Usar imagen base con nginx y PHP-FPM
FROM richarvey/nginx-php-fpm:3.1.6

# Instalar dependencias del sistema necesarias
RUN apk add --no-cache \
    postgresql-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd mbstring

# Copiar la aplicación completa
COPY . /var/www/html/

# Copiar configuración personalizada de nginx
COPY conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf

# Configuración de imagen
ENV SKIP_COMPOSER 0
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

# Configuración de Laravel
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

# Permitir que composer funcione como root
ENV COMPOSER_ALLOW_SUPERUSER 1

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Instalar dependencias de PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar scripts y darles permisos de ejecución
RUN chmod +x ./scripts/*.sh

# Establecer permisos correctos
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Exponer puerto
EXPOSE 80

# Usar script de inicio personalizado
CMD ["./scripts/start.sh"]
