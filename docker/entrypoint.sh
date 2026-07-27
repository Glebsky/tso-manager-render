#!/bin/sh
set -e

echo "Running entrypoint initialization..."

# Ensure Laravel storage and bootstrap folders exist
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Fix permissions on runtime folders (only if running as root)
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
fi

# Clear runtime config caches first
php artisan config:clear || true
php artisan cache:clear || true

# Production-only optimizations and migrations
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing Laravel for Production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Run migrations ONLY in the main app container to avoid race conditions with workers
    if [ "$1" = "php-fpm" ]; then
        echo "Running database migrations..."
        php artisan migrate --force
    fi
fi

# Execute the main container process
exec "$@"
