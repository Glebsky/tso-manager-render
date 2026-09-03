# TSO Manager Admin

<p align="center">
<a href="https://www.foglamp.dev/scan/tso-manager-admin-fs2rdm"><img src="https://img.shields.io/badge/Foglamp-Codebase%20Scan-brightgreen?style=flat-square" alt="Foglamp Codebase Scan"></a>
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
</p>

TSO Manager Admin — это панель управления и планировщик автоматизации для игры **The Settlers Online**. Проект позволяет подключать игровые аккаунты, синхронизировать состояние игровых зон (здания, специалисты, баффы, ресурсы, коллекционные предметы), планировать повторяющиеся задачи (наложение баффов, отправка геологов/исследователей, сбор коллекций, управление производством) и вести мониторинг/аналитику рынка.

---

## Технологический стек

* **Backend**: PHP 8.5 / Laravel 12 (Sanctum 4.0 для API-аутентификации)
* **Frontend**: Vue 3.5 (`<script setup>`) + Vite 6 + Tailwind CSS 3.4 (SPA, встроенная в Laravel)
* **Локализация**: Мультиязычный интерфейс (RU, EN, UK) с чанкированием и динамической подгрузкой словарей
* **Парсинг игровых данных**: Python 3 + Py3AMF (декодирование и кодирование пакетов AMF0/AMF3)
* **База данных**: PostgreSQL 17 / MySQL / SQLite (для тестов в памяти)
* **Кэширование и очереди**: Redis 7 / Database
* **Наблюдаемость и мониторинг**: Sentry (APM и сбор ошибок), Laravel Pulse (системные метрики и очереди), Laravel Telescope (локальная отладка), Request ID Tracing (сквозная трассировка в логах и очередях)

---

## Начало работы

### 1. Системные требования
* **PHP >= 8.5** (расширения: `pdo`, `pdo_pgsql`, `pdo_sqlite`, `redis`, `zip`, `curl`, `mbstring`, `xml`)
* **Composer >= 2.0**
* **Node.js >= 20.0 & npm**
* **Python 3** с библиотекой `Py3AMF` (`pip install Py3AMF` или `pip3 install Py3AMF --break-system-packages`)
* **PostgreSQL 17** и **Redis 7** (или запуск через Docker)

### 2. Установка зависимостей
Клонируйте репозиторий и установите пакеты PHP и Node.js:

```bash
composer install
npm install
```

### 3. Настройка окружения (`.env`)
Скопируйте конфигурационный файл из шаблона:

```bash
copy .env.example .env
php artisan key:generate
```

Настройте параметры подключения к БД, Redis и специфичные настройки TSO в `.env`.

### 4. Миграции и подготовка базы данных
Выполните миграции:

```bash
php artisan migrate
```

---

## Конфигурация окружения (.env)

Ниже приведено подробное описание всех ключей конфигурации из `.env.example`, сгруппированных по функциональным модулям:

### 1. Основные параметры приложения (Application)
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `APP_NAME` | `"TSO Manager"` | Название приложения, используется в заголовках, письмах и UI. |
| `APP_ENV` | `local` | Окружение приложения (`local`, `production`, `testing`). |
| `APP_KEY` | *(генерируется)* | Ключ шифрования сессий и токенов (генерируется через `php artisan key:generate`). |
| `APP_DEBUG` | `true` | Режим отладки. В `production` обязательно `false` для безопасности. |
| `APP_TIMEZONE` | `UTC` | Базовый часовой пояс приложения. |
| `APP_URL` | `http://localhost` | Базовый URL приложения для генерации ссылок и работы роутера. |
| `APP_LOCALE` | `en` | Язык приложения по умолчанию (`ru`, `en`, `uk`). |
| `APP_FALLBACK_LOCALE` | `en` | Резервный язык, если перевод ключа отсутствует в текущей локали. |

### 2. Логирование (Logging)
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `LOG_CHANNEL` | `stack` / `daily` | Основной канал логирования (`stack`, `daily`, `single`, `sentry`, `sentry_logs`). |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Канал для предупреждений об устаревших функциях PHP/библиотек. |
| `LOG_LEVEL` | `debug` | Минимальный уровень логирования (`debug`, `info`, `warning`, `error`). |
| `LOG_STACK` | `single,sentry_logs` | Список каналов через запятую, объединяемых в составной канал `stack`. |
| `LOG_STDERR_FORMATTER` | `Monolog\Formatter\JsonFormatter` | Форматтер для потока `stderr` (полезно для контейнеров Docker/K8s). |

### 3. База данных (PostgreSQL)
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `DB_CONNECTION` | `pgsql` | Драйвер БД (`pgsql`, `mysql`, `sqlite`). Основной стек — PostgreSQL. |
| `DB_HOST` | `127.0.0.1` | Хост сервера базы данных. |
| `DB_PORT` | `5432` | Порт PostgreSQL (по умолчанию `5432`). |
| `DB_DATABASE` | `tso_manager` | Имя рабочей базы данных. |
| `DB_USERNAME` | `tso_admin` | Имя пользователя базы данных. |
| `DB_PASSWORD` | `secret` | Пароль пользователя базы данных. |

### 4. Кэш, Сессии, Очереди и Файловое хранилище
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `BROADCAST_DRIVER` | `log` | Драйвер широковещания событий (`log`, `pusher`, `null`). |
| `CACHE_DRIVER` | `file` / `redis` | Драйвер кэша (`file`, `redis`, `database`). В продакшене рекомендуется `redis`. |
| `FILESYSTEM_DISK` | `local` | Диск хранения файлов (`local`, `public`, `s3`). |
| `QUEUE_CONNECTION` | `sync` / `database` | Драйвер очереди задач (`database`, `redis`, `sync`). |
| `SESSION_DRIVER` | `file` / `redis` | Хранилище сессий пользователей (`redis`, `file`, `database`). |
| `SESSION_LIFETIME` | `120` | Время жизни сессии пользователя в минутах. |

### 5. Redis и Memcached
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `REDIS_HOST` | `127.0.0.1` | IP-адрес или хост сервера Redis. |
| `REDIS_PASSWORD` | `null` | Пароль для доступа к Redis (если настроен). |
| `REDIS_PORT` | `6379` | Порт подключения к Redis. |
| `MEMCACHED_HOST` | `127.0.0.1` | Хост Memcached (если используется в качестве кэша). |

### 6. Почтовый сервис (Mail)
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `MAIL_MAILER` | `smtp` | Драйвер почты (`smtp`, `sendmail`, `log`). |
| `MAIL_HOST` | `mailpit` | Хост SMTP сервера (локально `mailpit` или `mailhog`). |
| `MAIL_PORT` | `1025` | Порт SMTP сервера. |
| `MAIL_USERNAME` | `null` | Логин пользователя SMTP. |
| `MAIL_PASSWORD` | `null` | Пароль пользователя SMTP. |
| `MAIL_ENCRYPTION` | `null` | Тип шифрования (`tls`, `ssl`, `null`). |
| `MAIL_FROM_ADDRESS`| `"hello@example.com"` | Email отправителя системных уведомлений. |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | Имя отправителя в письмах. |

### 7. AWS / S3 Хранилище (Опционально)
| Ключ | Описание |
| :--- | :--- |
| `AWS_ACCESS_KEY_ID` | Идентификатор ключа доступа к AWS / S3-совместимому хранилищу. |
| `AWS_SECRET_ACCESS_KEY` | Секретный ключ доступа AWS / S3. |
| `AWS_DEFAULT_REGION` | Регион S3 (например, `us-east-1`). |
| `AWS_BUCKET` | Имя корзины (bucket) для хранения файлов. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Использование path-style адресации (актуально для MinIO / локальных S3). |

### 8. Pusher / WebSockets и Vite
| Ключ | Описание |
| :--- | :--- |
| `PUSHER_APP_ID` / `KEY` / `SECRET` | Учетные данные для Pusher / Soketi при работе с WebSocket в реальном времени. |
| `PUSHER_HOST` / `PORT` / `SCHEME` | Параметры кастомного WebSocket-сервера. |
| `VITE_*` | Проброс соответствующих переменных в сборку фронтенда через Vite. |

### 9. Игровой клиент TSO и Планировщик (`config/game.php`)
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `TSO_SCHEDULER_MODE` | `queue` | Режим работы планировщика: `queue` (отправка в очереди `tso-tasks`/`tso-market`), `cron` (выполнение через cron-воркер) или `sync` (синхронно). |
| `TSO_STALE_TASK_TIMEOUT` | `10` | Таймаут зависших задач в минутах (автоматический сброс зависших `running` задач). |
| `TSO_SSL_VERIFY` | `true` | Проверка валидности SSL-сертификатов при обращении к игровым серверам Ubisoft/TSO. |
| `TSO_HTTP_TIMEOUT` | `30` | Таймаут HTTP-запросов к игровым шлюзам TSO (в секундах). |
| `TSO_SESSION_TTL` | `300` | Время жизни активной AMF-сессии игрового сервера в секундах до повторной инициализации. |
| `TSO_SESSION_LOCK_WAIT`| `20` | Максимальное время ожидания блокировки сессии (секунды) для предотвращения параллельных конфликтов. |

### 10. Аналитика и синхронизация рынка (`config/market.php`)
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `MARKET_DEFAULT_SERVER_ID` | `ru` | Сервер по умолчанию для отображения публичной аналитики рынка. |
| `MARKET_OFFER_LIFETIME_HOURS` | `6` | Время жизни рыночного лота в часах (для фильтрации активных предложений). |
| `MARKET_CACHE_STRATEGY` | `individual` | Стратегия кэширования и выборки предложений рынка: `individual` или `bulk`. |

### 11. Наблюдаемость и мониторинг (Sentry, Pulse, Telescope)
| Ключ | Значение по умолчанию | Описание |
| :--- | :--- | :--- |
| `SENTRY_LARAVEL_DSN` | *(DSN)* | DSN адрес проекта в Sentry для отправки ошибок и трейсов. |
| `SENTRY_ENABLE_LOGS` | `true` | Включение отправки структурированных логов в Sentry Logs. |
| `SENTRY_SEND_DEFAULT_PII` | `false` | Разрешение на передачу персональных данных (IP, email) в Sentry. |
| `SENTRY_ENVIRONMENT` | `local` | Имя окружения в панели Sentry (`local`, `staging`, `production`). |
| `SENTRY_TRACES_SAMPLE_RATE` | `0.0` | Доля трейсинга производительности транзакций (от `0.0` до `1.0`). |
| `SENTRY_PROFILES_SAMPLE_RATE` | `0.0` | Доля профилирования выполнения кода в Sentry (от `0.0` до `1.0`). |
| `PULSE_ENABLED` | `true` | Включение встроенного дашборда метрик Laravel Pulse (`/pulse`). |
| `TELESCOPE_ENABLED` | `true` | Включение инструмента локальной отладки Laravel Telescope (`/telescope`). |

---

## Наблюдаемость и отладка (Telemetry & Observability)

В проект интегрирован комплекс инструментов для мониторинга, отладки и аудита производительности:

1. **Sentry (`sentry/sentry-laravel`)**:
   - Автоматический перехват необработанных исключений и интеграция с контуром ошибок Laravel (`Integration::handles($exceptions)`).
   - Трейсинг производительности запросов, очередей и транзакций базы данных.

2. **Laravel Pulse (`laravel/pulse`)**:
   - Дашборд метрик в реальном времени: нагрузка на сервер, время отклика эндпоинтов, медленные SQL-запросы, загрузка очередей и воркеров (`/pulse`).

3. **Laravel Telescope (`laravel/telescope`)**:
   - Инструмент глубокой инспекции для локальной разработки (`/telescope`).
   - Изолирован от продакшена: вынесен в `require-dev`, отключен от автодискавери (`dont-discover`), загружается исключительно в окружении `local`.

4. **Сквозная трассировка (Distributed Request ID Tracing)**:
   - Middleware `RequestId` валидирует или генерирует уникальный заголовок `X-Request-Id`.
   - Контекст `request_id` автоматически внедряется в логгер (`Log::shareContext`) и пробрасывается через полезную нагрузку очередей (`Queue::createPayloadUsing`) и консольные команды (`CommandStarting`), связывая веб-запросы, фоновые джобы и логи воркеров в единую цепочку.

5. **Логирование медленных запросов**:
   - Любой SQL-запрос, выполняющийся дольше 200 мс, автоматически логируется с уровнем `warning` (`[Slow query]`).

---

## Запуск в режиме разработки

### Запуск бэкенда
Локальный сервер разработки Laravel:
```bash
php artisan serve
```

### Сборка и запуск фронтенда (Vite)
Для разработки с поддержкой Hot Module Replacement (HMR):
```bash
npm run dev
```

Для сборки продакшн-бандла:
```bash
npm run build
```

---

## Специальные команды Artisan (`tso:*`)

Проект расширяет стандартный CLI Laravel специализированными командами:

* **`php artisan tso:run-scheduler [--mode=queue|cron|sync] [--work]`**
  Главный диспетчер шедулера TSO:
  - Автоматически восстанавливает зависшие задачи (`recoverStaleTasks`).
  - Резервирует и отправляет в очередь созревшие задачи планировщика (`tso-tasks`).
  - Запускает синхронизацию подключенных игровых серверов рынка (`tso-market`).
  - Триггерит периодическую синхронизацию зон и данных аккаунтов (`tso-accounts`).
  - Запускает очистку логов согласно политике хранения (Log Retention Policy).
  - С флагом `--work` запускает встроенный воркер для изоляции cron-вызовов.

* **`php artisan tso:sync-market [--sync] [--server=SERVER_ID]`**
  Запускает синхронизацию лотов рынка для настроенных подключений с проверкой интервалов и защитой от параллельных запусков (атомарные Redis/Cache блокировки).

* **`php artisan tso:execute-tasks [--task=ID] [--async]`**
  Выполняет задачи планировщика (наложение баффов, отправка специалистов, сбор коллекций). Позволяет запустить конкретную задачу по ID.

* **`php artisan tso:lang:export-frontend`**
  Экспортирует языковые строки приложения и игровые переводы в `resources/js/lang/generated/<locale>.json` для SPA-бандла.

* **`php artisan tso:lang:import`**
  Импортирует игровые переводы из XML-экспорта игры в `lang/<locale>/game.php`.

---

## Фоновые воркеры и Планировщик (Production)

Для корректной и бесперебойной работы автоматизации в продакшн-окружении:

### 1. Выделенные очереди (Queue Worker)
Обработка фоновых задач выполняется через выделенные очереди `tso-tasks`, `tso-accounts`, `tso-market` и системную очередь `default`:

```bash
php artisan queue:work --queue=tso-tasks,tso-accounts,tso-market,default --sleep=3 --tries=3
```

### 2. Планировщик Laravel (Cron)
Добавьте вызов планировщика в системный crontab:
```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

> Расписание в [`routes/console.php`](file:///c:/OSPanel/home/admin/routes/console.php) каждую минуту запускает `tso:run-scheduler --work` с защитой `withoutOverlapping(10)` и `onOneServer()`.

---

## Контроль качества (Quality Gates)

Перед завершением задач и деплоем проект проверяется следующими шлюзами качества:

```bash
# 1. Запуск полного набора Feature и Unit тестов
php artisan test

# 2. Проверка соответствия стандартам кодовой базы (Laravel Pint)
./vendor/bin/pint --test

# 3. Статический анализ типов и контрактов (PHPStan)
./vendor/bin/phpstan analyse

# 4. Проверка чистоты сборки фронтенда
npm run build
```

---

## Docker Development & Deployment

### Быстрый старт через Docker

```bash
# Первоначальная установка (сборка, запуск, ключи, миграции)
make install

# Либо вручную:
make build
make up
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Панель доступна по адресу `http://localhost`.

### Сервисы контейнеров

| Сервис | Порт / URL | Описание |
|---|---|---|
| **App** (PHP-FPM) | Внутренний `:9000` | Laravel 12 приложение (PHP 8.5) |
| **Nginx** | `http://localhost:80` | Web-сервер |
| **Node** (Vite) | `http://localhost:5173` | Dev-сервер с поддержкой HMR |
| **PostgreSQL** | `localhost:5432` | База данных PostgreSQL 17 |
| **Redis** | `localhost:6379` | Кэш / Очереди / Сессии |
| **Worker** | — | Воркер очередей |
| **Scheduler** | — | Планировщик задач |

### Доступные команды Makefile (`make help`):

| Команда | Назначение |
|---|---|
| `make install` | Первоначальная установка и настройка |
| `make up` | Запуск всех контейнеров в фоне |
| `make down` | Остановка всех контейнеров |
| `make build` | Пересборка Docker-образов |
| `make fresh` | Полный пересбор с удалением volumes, миграциями и сидерами |
| `make shell` | Интерактивный терминал в контейнере приложения |
| `make test` | Запуск тестового набора Laravel |
| `make lint` | Проверка кодстайла Pint и отсутствия TLS-секретов в git |
| `make analyse` | Запуск статического анализатора PHPStan |
| `make logs` | Просмотр логов контейнеров в реальном времени |
| `make prod-up` | Запуск оптимизированного Production-окружения |

---

## Архитектурные особенности

### Модель Single-Operator (ADR-001)
Приложение спроектировано по модели **одного оператора (Single-Operator)**:
- Первая регистрация создаёт учётную запись администратора.
- Все последующие попытки регистрации блокируются на уровне транзакций базы данных.
- Все административные эндпоинты выполняются в контексте единого оператора.

### Схема архитектуры
Визуализированная схема зависимостей фронтенда, сервисов, фоновых парсеров и внешних API доступна на Foglamp:
👉 **[Посмотреть Codebase Scan на Foglamp](https://www.foglamp.dev/scan/tso-manager-admin-fs2rdm)**
