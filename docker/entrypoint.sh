#!/bin/sh
set -e

echo "Running entrypoint initialization..."

# Ensure Laravel storage and bootstrap folders exist
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Fix permissions on runtime folders
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear runtime config caches so .env changes are always active
php artisan config:clear || true
php artisan cache:clear || true

# Execute the main container process
exec "$@"
