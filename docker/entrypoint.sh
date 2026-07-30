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

# Ensure production assets in app_public volume are synchronized
if [ -d "/tmp/build_assets" ] && [ "$1" = "php-fpm" ]; then
    mkdir -p /var/www/html/public/build
    cp -r /tmp/build_assets/* /var/www/html/public/build/ 2>/dev/null || true
fi

# Clear runtime config caches first
php artisan config:clear || true
php artisan cache:clear || true

# Auto-generate APP_KEY if not specified in environment
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not specified. Generating a temporary key for this container session..."
    GENERATED_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    export APP_KEY="$GENERATED_KEY"
    # Update Laravel config directly so it takes effect even if config is cached later
    php artisan config:clear || true
fi

# Production-only optimizations and migrations
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing Laravel for Production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    echo "Waiting for PostgreSQL to become ready..."
    until nc -z -v -w30 "$DB_HOST" "$DB_PORT"; do
        echo "Waiting for database connection on $DB_HOST:$DB_PORT..."
        sleep 1
    done

    echo "Database port is open. Checking if database $DB_DATABASE exists..."
    php -r "
        try {
            \$dbHost = getenv('DB_HOST') ?: 'postgres';
            \$dbPort = getenv('DB_PORT') ?: '5432';
            \$dbUser = getenv('DB_USERNAME') ?: 'tso_admin';
            \$dbPass = getenv('DB_PASSWORD') ?: 'secret';
            \$dbName = getenv('DB_DATABASE') ?: 'tso_admin';

            \$pdo = new PDO('pgsql:host=' . \$dbHost . ';port=' . \$dbPort . ';dbname=postgres', \$dbUser, \$dbPass);
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            \$stmt = \$pdo->query(\"SELECT 1 FROM pg_database WHERE datname = '\" . \$dbName . \"'\");
            if (!\$stmt->fetch()) {
                echo 'Database ' . \$dbName . ' does not exist. Creating it...\n';
                \$pdo->exec('CREATE DATABASE \"' . \$dbName . '\"');
                echo 'Database created successfully.\n';
            } else {
                echo 'Database ' . \$dbName . ' already exists.\n';
            }
        } catch (Exception \$e) {
            fwrite(STDERR, 'DB initialization Fatal Error: ' . \$e->getMessage() . '\n');
            exit(1);
        }
    "

    # Run migrations ONLY in the main app container
    if [ "$1" = "php-fpm" ]; then
        echo "Running database migrations..."
        php artisan migrate --force
    fi
fi

# Execute the main container process
exec "$@"
