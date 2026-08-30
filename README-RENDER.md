# TSO Manager — деплой на Render

Это код из `original.zip` (Laravel 12 / PHP 8.5, зарефакторенная версия) плюс все
адаптации под Render из `render.zip`. Ниже — что именно отличается от базового
проекта и почему. Если будете снова обновлять код — читайте сначала этот файл.

---

## 1. Файлы, которые есть только ради Render

| Файл | Зачем |
|------|-------|
| `render.yaml` | Blueprint: план, health check, все env-переменные |
| `Dockerfile.render` | Один контейнер: nginx + php-fpm + supercronic |
| `docker/entrypoint.render.sh` | Генерация nginx-конфига под `$PORT`, кэши, миграции |
| `docker/nginx/render.conf.template` | nginx с `listen ${PORT}` и `/healthz` |
| `docker/supervisord.conf` | Три процесса в одном контейнере |
| `docker/crontab.render` | `schedule:run` для supercronic |
| `docker/php/php.ini`, `docker/php/www.conf` | Настройки под 512 MB / 0.1 CPU |
| `.dockerignore` | Без него `.env` и `vercel.json` с паролями попадут в образ |

В Render Dashboard должно быть указано: **Dockerfile Path = `./Dockerfile.render`**.

---

## 2. Самое важное: что было восстановлено при обновлении

В `original.zip` **отсутствовали** файлы, без которых Laravel не стартует вообще:

- `public/index.php` — точка входа (nginx без неё отдаёт 403/404 на всё)
- `bootstrap/app.php` — создание контейнера приложения
- `app/Http/Kernel.php` и `app/Console/Kernel.php`

Их взяли из `render.zip` и адаптировали под новый код.

### Почему оставлен СТАРЫЙ стиль скелета, а не новый `bootstrap/app.php` Laravel 12

Новый стиль (`Application::configure()->withRouting()->withMiddleware()`) здесь не подходит,
потому что в обновлённом коде всё ещё живут:

- `app/Providers/RouteServiceProvider.php` — сам регистрирует `routes/api.php` и `routes/web.php`
  плюс `RateLimiter::for('api')`
- `app/Exceptions/Handler.php`
- `app/Providers/{Auth,Event,Broadcast}ServiceProvider.php`
- массив `providers` в `config/app.php`

Если добавить `withRouting()`, роуты зарегистрируются дважды, а `Handler.php`
перестанет вызываться. Laravel 11/12 официально поддерживает старую структуру,
поэтому это рабочий и самый безопасный вариант.

### Где теперь расписание

В `routes/console.php` (фасад `Schedule`) — так сделано в обновлённом коде.
`Kernel::schedule()` намеренно пустой: если объявить задачу в двух местах,
`tso:run-scheduler --work` будет запускаться дважды в минуту (`withoutOverlapping()`
от этого не спасает — у каждого события свой mutex), а на 0.1 CPU это больно.

---

## 3. Что изменилось в самой Render-обвязке при обновлении

1. **PHP 8.3 → 8.5**, **Node 20 → 22** в `Dockerfile.render`.
   `composer.json` теперь требует `"php": "^8.5"`.
   **Откат**, если базовый образ 8.5 недоступен: в `Dockerfile.render` строка
   `ARG PHP_VERSION=8.5` → `8.4`. Код проверен: 8.5-only синтаксиса нет,
   `Pdo\Mysql` защищён `class_exists()`, `platform-check` в composer выключен.
2. **`route:cache` больше не валит деплой.** В `routes/web.php` есть closure-роуты;
   теперь при ошибке делается `route:clear` и старт продолжается.
3. **nginx: добавлен `location ^~ /build/`.** Новый `vite.config.js` режет бандл на
   чанки (`lang-ru`, `lang-en`, `lang-uk`, `vendor-vue`, `vendor`), а в конфиге есть
   запрет на отдачу `*.json`. Префикс `^~` имеет приоритет над regex,
   так что ассеты сборки теперы гарантированно отдаются.
4. **OPcache: 128 MB / 20000 файлов** (было 128/20000 только частично).
   Laravel 12 + vendor больше Laravel 10, в дефолтные 10k файлов не влезал.
5. **Миграции sessions/cache сделаны идемпотентными** (см. раздел 5).
6. **`config/database.php`:** `sslmode` теперь из `DB_SSLMODE`, добавлен
   `PDO::ATTR_TIMEOUT` из `DB_CONNECT_TIMEOUT`.

---

## 4. Env-переменные, которые нужно задать руками

В `render.yaml` они помечены `sync: false` — значений в репо нет:

```
APP_KEY      = base64:...        # php artisan key:generate --show
APP_URL      = https://<service>.onrender.com
DB_HOST      = aws-0-<region>.pooler.supabase.com
DB_PORT      = 6543
DB_USERNAME  = postgres.<project-ref>
DB_PASSWORD  = <пароль>
```

**Про Supabase.** Прямой хост `db.<ref>.supabase.co` резолвится только в IPv6,
а Render ходит по IPv4 — будет таймаут. Нужен именно **pooler** на порту 6543
и логин вида `postgres.<project-ref>`. Entrypoint проверяет соединение до миграций
и печатает эту подсказку в лог.

---

## 5. Первый деплой этой версии — порядок действий

1. Проверьте, что `RUN_MIGRATIONS=true`.
2. Задеплойте и смотрите лог. Должны примениться две новые миграции:
   - `2026_08_09_120000_encrypt_account_credentials` — меняет типы колонок
     `accounts.password`, `dso_auth_user`, `dso_auth_token` на `text` и шифрует
     существующие значения текущим `APP_KEY`;
   - `2026_07_28_0854*` sessions/cache — пройдут вхолостую (таблицы уже есть).
3. После успешного деплоя поставьте **`RUN_MIGRATIONS=false`** — иначе каждый
   рестарт контейнера на Free-плане будет ждать базу.

> **После этой миграции `APP_KEY` МЕНЯТЬ НЕЛЬЗЯ.** При другом ключе пароли
> аккаунтов в базе не расшифруются, и бот не сможет логиниться в игре.
> Сделайте бэкап таблицы `accounts` в Supabase перед первым деплоем.

---

## 6. Безопасность — требует вашего внимания

В архиве сохранены два файла из `render.zip`, в которых лежат реальные секреты:

- `.env` — боевые данные Supabase;
- `vercel.json` — пароль БД **открытым текстом** в блоке `env`.

В Docker-образ они больше не попадают (добавлены в `.dockerignore`, вместе
с каталогом `api/`). Но если эти файлы когда-либо были в git или в публичном
репозитории — **смените пароль базы в Supabase**. Если Vercel больше не нужен,
`vercel.json` и `api/server.php` можно удалить совсем.

---

## 7. Диагностика

```bash
# сервис жив (отвечает nginx, PHP не трогается)
curl -i https://<service>.onrender.com/healthz

# из Render Shell:
curl localhost:$PORT/fpm-status      # состояние воркеров php-fpm
ps -o rss,cmd -C php-fpm             # реальное потребление памяти
php artisan schedule:list            # что видит планировщик
php artisan queue:monitor tso-tasks  # размер очереди
```

В `schedule:list` должна быть ровно **одна** запись `tso:run-scheduler --work`.
Если две — значит задачу случайно объявили и в `app/Console/Kernel.php`,
и в `routes/console.php`.

---

## 8. Два падения сборки с "exit code: 2" и их разбор

### 8.1. Почему первое сообщение было бесполезным

`apk add`, `docker-php-ext-install` и `pip3 install` стояли в одном `RUN` через
`&&`. Docker печатает только итоговый код всей цепочки, поэтому было неясно,
что именно упало. Сейчас это восемь отдельных шагов с `set -eux`, в логе
видна конкретная команда и номер шага.

### 8.2. Что показало разбиение

Второй деплой упал ровно на шаге 3/8 - `docker-php-ext-install`. Код 2 -
это код возврата `make`, то есть падала компиляция расширения. Ни `apk`,
ни `pip` виноваты не были. Сравнение с рабочим `Dockerfile.render` из
`render.zip`: там был **тот же список расширений**, но PHP 8.3. Значит,
дело не в списке, а в базовом образе.

### 8.3. Как переписан шаг 3/8

Каждое расширение теперь проходит трёхступенчатую стратегию `add_ext`:

1. уже вкомпилировано в базовый образ -> ничего не делаем;
2. готовый `.so` лежит в образе -> `docker-php-ext-enable`, без компиляции;
3. иначе -> `docker-php-ext-install -j1`.

Почему это чинит падение:

* **`pdo` и `pdo_sqlite` в официальном образе php уже есть.** Их повторная
  сборка была чисто лишним риском.
* **`opcache` больше не пересобирается.** В PHP 8.4+ JIT переписан на
  IR-фреймворк, и сборка opcache из исходников - самый хрупкий пункт
  списка. Готовый `.so` просто включается.
* **Убран `-j"$(nproc)"`.** Параллельный `make` на сборщике Render может
  получить OOM-kill, а выглядит это ровно как загадочный `exit code 2`.
* **`opcache` и `zip` теперь необязательные.** Ни одна зависимость из
  `composer.lock` не требует `ext-zip`, а `ZipArchive` в коде проекта не
  встречается ни разу (composer распакует пакеты бинарём `unzip`).
  Обязательны только `pdo_pgsql` и `pcntl` - по ним сборка падает намеренно.
* У `pdo_pgsql` есть вторая попытка через
  `docker-php-ext-configure pdo_pgsql --with-pdo-pgsql=/usr` на случай, если
  `configure` не найдёт `pg_config`.

### 8.4. ПОПРАВКА: откат на PHP 8.3 невозможен

Ранее здесь был совет поставить `ARG PHP_VERSION=8.3`. **Это ошибка, так
делать нельзя.** В новом `composer.lock` из `original.zip` лежат компоненты
 Symfony 8 с требованием `"php": ">=8.4"`:

| Пакет | Требование |
|---|---|
| symfony/clock | >=8.4 |
| symfony/css-selector | >=8.4 |
| symfony/event-dispatcher | >=8.4 |
| symfony/string | >=8.4 |
| symfony/translation | >=8.4 |

Сборка на 8.3 бы успешно прошла: `config.platform.php = 8.5.0` и
`platform-check: false` подавляют обе проверки. Но приложение упало бы уже
в рантайме, на синтаксисе 8.4 внутри `vendor/symfony` - тихая поломка
вместо честного падения сборки.

Допустимые версии - только **8.4 и 8.5**. Ни один пакет из lock не требует
8.5, поэтому по умолчанию теперь стоит `ARG PHP_VERSION=8.4`: образ старше
и обкатаннее, Alpine и Python в нём ближе к тому, на чём у вас всё
собиралось. Попробовать 8.5 можно без правки файла:
Render -> Settings -> Docker Build Arguments -> `PHP_VERSION=8.5`.

Если когда-нибудь понадобится настоящий откат на 8.3, требуется не правка
`ARG`, а пересборка зависимостей:

```bash
composer config platform.php 8.3.999
composer update --with-all-dependencies
```

### 8.5. Если сборка всё равно падает

Шаг 3/8 теперь печатает `php -v` и полный `php -m` ДО работы, а перед
каждым расширением - строку вида `--- pcntl: компилируем из исходников`.
В логе будет точно видно, на каком расширении и с какой ошибкой
компилятора всё остановилось.

Проверить гипотезу локально, не трогая Render:

```bash
docker run --rm -it mirror.gcr.io/library/php:8.4-fpm-alpine sh -lc \
  'php -m; apk add --no-cache libpq-dev $PHPIZE_DEPS \
   && docker-php-ext-install -j1 pdo_pgsql pcntl && php -m'
```

### 8.6. Третье падение: composer install, exit code 2

Шаги 1-6 прошли (расширения собрались на 8.4), упало на 18/22:

```
Installing dependencies from lock file
Verifying lock file contents can be installed on current platform.
Warning: The lock file is not up to date with the latest changes in composer.json.
Your lock file does not contain a compatible set of packages. Please run composer update.
  Problem 1
    - Root composer.json requires php ^8.5 but your php version
      (8.4.0; overridden via config.platform, actual: 8.4.24)
      does not satisfy that requirement.
```

Код 2 у Composer - это SolverProblemsException, то есть не сеть и не права,
а неразрешимые зависимости.

**Причина.** В корневом `composer.json` стоит `"php": "^8.5"`, а образ
собирается на 8.4. Судя по логу, `config.platform.php` в репозитории
равен `8.4.0` (в архиве было `8.5.0`) - если правили вручную, это
ничего не ломает, но и не помогает: ограничение `^8.5` проверяется
в любом случае.

**Главное:** ни один из 77 prod-пакетов в `composer.lock` не требует 8.5.
Самый высокий потолок - Symfony 8 с `">=8.4"`. Значит `^8.5` в корневом
файле - декларация автора, а не реальное требование кода.

**Решение** (шаг 7/8 Dockerfile.render): флаг `--ignore-platform-req=php`.
Он снимает проверку только для самого php; проверки `ext-*` остаются,
так что реально недостающее расширение всё равно уронит сборку.

Почему в Dockerfile, а не правкой `composer.json`: `composer.json` приезжает
из `original.zip` и будет перезаписан при следующем обновлении,
а `Dockerfile.render` - файл Render-обвязки, он переживёт мерж.

**Альтернатива без флага:** собирать на 8.5 (Render -> Settings ->
Docker Build Arguments -> `PHP_VERSION=8.5`). Тогда `^8.5` выполняется
честно, а флаг просто ничего не делает. По умолчанию оставлена 8.4:
именно на ней шаг со сборкой расширений уже прошёл на Render.

**Отдельная проблема в исходном проекте.** `composer.json` и `composer.lock`
в `original.zip` рассинхронизированы - content-hash не сходится
(`b98955aa...` против `fbca0b3f...` в lock). Сборке это не мешает (Composer
ставит ровно то, что в lock), но локально стоит один раз выполнить:

```bash
composer update --lock    # пересчёт только hash, версии не трогает
composer update           # полное обновление зависимостей
```

Шаг 7/8 теперь печатает `composer --version`, блок `config.platform` и
`composer check-platform-reqs --lock` ДО установки - если что-то ещё не
сойдётся, это будет видно в логе списком.
