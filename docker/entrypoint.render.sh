#!/bin/sh
# =====================================================================
# Entrypoint для Render.
#
# Отличия от версии в старом архиве:
#   * route:cache больше не может уронить контейнер. В routes/web.php есть
#     closure-роуты ('/', '/healthz', /admin/{any}). Если сериализация
#     замыкания когда-нибудь отвалится, при `set -e` контейнер просто
#     не стартовал бы. Теперь при ошибке кэш роутов сбрасывается,
#     и приложение работает без него (чуть медленнее, но живое).
#   * migrate --force по-прежнему под флагом RUN_MIGRATIONS: на Free-плане
#     контейнер перезапускается часто, и каждый раз ждать коннекта
#     к Supabase = +5-15 сек к холодному старту.
#   * Быстрая проверка доступности БД с понятной ошибкой вместо
#     молчаливого зависания.
# =====================================================================
set -e

: "${PORT:=10000}"
: "${RUN_MIGRATIONS:=true}"
export PORT

echo ">> render entrypoint: generating nginx config for port ${PORT}"
mkdir -p /etc/nginx/http.d
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/http.d/default.conf
nginx -t

cd /var/www/html

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
fi

if [ -z "$APP_KEY" ]; then
    echo "!! APP_KEY is empty. Set it in Render environment variables."
    exit 1
fi

# ---------------------------------------------------------------------
# Кэш конфигурации. Делаем ДО миграций, чтобы artisan работал быстрее.
# ---------------------------------------------------------------------
echo ">> laravel: caching config, views, events"
php artisan config:cache
php artisan view:cache
php artisan event:cache

# route:cache отдельно и НЕ фатально (см. комментарий в шапке).
if php artisan route:cache; then
    echo ">> laravel: route cache built"
else
    echo "!! route:cache failed - continuing without route cache"
    php artisan route:clear || true
fi

php artisan storage:link || true

# ---------------------------------------------------------------------
# Миграции.
#
# Поставьте RUN_MIGRATIONS=false в Render после первого успешного деплоя,
# и включайте обратно только когда добавили новые миграции.
#
# ВНИМАНИЕ ПРИ ПЕРВОМ ДЕПЛОЕ ЭТОЙ ВЕРСИИ: в наборе появилась миграция
# 2026_08_09_120000_encrypt_account_credentials - она меняет типы колонок
# accounts.password / dso_auth_user / dso_auth_token на text и шифрует
# существующие значения текущим APP_KEY. Поэтому APP_KEY менять нельзя:
# при другом ключе сохранённые пароли аккаунтов расшифровать не получится.
# ---------------------------------------------------------------------
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo ">> laravel: checking database connection"
    if ! php -r '
        $h = getenv("DB_HOST"); $p = getenv("DB_PORT") ?: 5432;
        if (!$h) { fwrite(STDERR, "DB_HOST is empty\n"); exit(1); }
        $s = @fsockopen($h, (int) $p, $e, $m, 8);
        if (!$s) { fwrite(STDERR, "cannot reach {$h}:{$p} -> {$m}\n"); exit(1); }
        fclose($s); echo "db reachable: {$h}:{$p}\n";
    '; then
        echo "!! Database is unreachable."
        echo "!! Если DB_HOST выглядит как db.<ref>.supabase.co - это Direct Connection,"
        echo "!! он доступен только по IPv6, а Render ходит по IPv4."
        echo "!! Переключитесь на pooler: aws-0-<region>.pooler.supabase.com:6543"
        echo "!! и DB_USERNAME=postgres.<project-ref>"
        exit 1
    fi

    echo ">> laravel: migrate"
    php -d memory_limit=256M artisan migrate --force --no-interaction
else
    echo ">> laravel: migrations skipped (RUN_MIGRATIONS=false)"
fi

echo ">> starting supervisord (nginx + php-fpm + scheduler)"
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
