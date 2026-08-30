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

В проекте используются как стандартные параметры Laravel, так и специализированные настройки автоматизации TSO и Маркета:

### Настройки автоматизации TSO (`config/game.php`)
```env
# Режим планировщика: 'queue' (по умолчанию), 'cron' или 'sync'
TSO_SCHEDULER_MODE=queue

# Таймаут зависших задач в минутах (автоматический сброс статуса 'running' в 'pending')
TSO_STALE_TASK_TIMEOUT=10

# Проверка SSL-сертификатов при обращении к серверам Ubisoft/TSO
TSO_SSL_VERIFY=true

# Таймаут HTTP-запросов к серверам TSO (в секундах)
TSO_HTTP_TIMEOUT=30
```

### Настройки рынка (`config/market.php`)
```env
# Сервер по умолчанию для публичной аналитики рынка
MARKET_DEFAULT_SERVER_ID=ru

# Время жизни активного рыночного предложения в часах
MARKET_OFFER_LIFETIME_HOURS=6

# Стратегия кэширования рынка: 'bulk' или 'individual'
MARKET_CACHE_STRATEGY=bulk
```

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
