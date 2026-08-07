FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    postgresql-dev \
    libxml2-dev \
    sqlite-dev \
    libzip-dev \
    oniguruma-dev \
    curl-dev \
    python3 \
    py3-pip \
    linux-headers \
    unzip \
    zip \
    pkgconfig \
    $PHPIZE_DEPS

RUN docker-php-ext-install pdo pdo_pgsql pdo_sqlite zip \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN pip3 install Py3AMF --break-system-packages || pip3 install Py3AMF

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN mkdir -p /var/log/php \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data /var/log/php storage bootstrap/cache

# =========================================================================
# Stage: vendor-builder (Builds all dependencies including dev)
# =========================================================================
FROM base AS vendor-builder

COPY composer.json composer.lock ./
RUN composer install --no-scripts --prefer-dist

# =========================================================================
# Stage: development
# =========================================================================
FROM base AS development

RUN apk add --no-cache git

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy vendor folders built inside Linux container
COPY --from=vendor-builder /var/www/html/vendor /var/www/html/vendor

# Setup entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# =========================================================================
# Stage: node-build (for Production assets)
# =========================================================================
FROM node:20-alpine AS node-build

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

# =========================================================================
# Stage: production
# =========================================================================
FROM base AS production

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Install only production dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

COPY --from=node-build /app/public/build /var/www/html/public/build
COPY --from=node-build /app/public/build /tmp/build_assets

RUN chown -R www-data:www-data storage bootstrap/cache

# Setup entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
