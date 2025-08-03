#!/usr/bin/env bash

echo "Running composer install..."
composer install --no-dev --working-dir=/var/www/html

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching views..."
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Seeding database (if needed)..."
php artisan db:seed --force || echo "No seeders or seeding failed, continuing..."

echo "Clearing and caching everything..."
php artisan optimize

echo "Starting PHP-FPM and NGINX..."
