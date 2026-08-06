# TSO Manager Admin

<p align="center">
<a href="https://www.foglamp.dev/scan/tso-manager-admin-fs2rdm"><img src="https://img.shields.io/badge/Foglamp-Codebase%20Scan-brightgreen?style=flat-square" alt="Foglamp Codebase Scan"></a>
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
</p>

TSO Manager Admin — это панель управления и планировщик автоматизации для игры **The Settlers Online**. Проект позволяет подключать игровые аккаунты, синхронизировать состояние игровых зон (зданий, специалистов, ресурсов), планировать повторяющиеся задачи (наложение баффов, отправка геологов/исследователей, управление производством) и вести мониторинг/аналитику рынка.

## Технологический стек

* **Backend**: PHP 8.1 / Laravel 10 (Sanctum для авторизации)
* **Frontend**: Vue 3 (`<script setup>`) + Vite + Tailwind CSS (SPA интегрировано в Laravel)
* **Data parsing**: Python 3 + PyAMF (используется для декодирования игровых AMF3/AMF0 пакетов)
* **Database**: PostgreSQL / MySQL / SQLite (для тестов)
* **Queue / Cache**: Redis / Database

---

## Начало работы

### 1. Требования
* PHP 8.1
* Composer
* Node.js & npm
* Python 3 с установленными зависимостями (`pip install PyAMF` или аналогичный парсер AMF в зависимости от используемой среды).

### 2. Установка
Клонируйте репозиторий и установите PHP и JS зависимости:

```bash
composer install
npm install
```

### 3. Настройка окружения
Создайте файл `.env` из примера:

```bash
copy .env.example .env
# Сгенерируйте ключ приложения
php artisan key:generate
```

Настройте параметры подключения к базе данных (`DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) в `.env`.

### 4. Миграции базы данных
Создайте структуру таблиц:

```bash
php artisan migrate
```

---

## Запуск в режиме разработки

### Запуск бэкенда
Вы можете использовать встроенный сервер Laravel:
```bash
php artisan serve
```

### Сборка и запуск фронтенда (Vite)
Для разработки с HMR (Hot Module Replacement):
```bash
npm run dev
```

Для продакшн сборки фронтенда:
```bash
npm run build
```

---

## Специальные команды Artisan (`tso:*`)

Проект расширяет стандартный CLI Laravel следующими командами для управления автоматизацией:

* **`php artisan tso:run-scheduler`**
  Запускает главный шедулер автоматизации TSO. Обрабатывает активные задачи планировщика (Task Planner) и запускает аналитику/синхронизацию рынка.
  
* **`php artisan tso:sync-market`**
  Запускает синхронизацию рынка для настроенных подключений к игровым серверам. Загружает лоты, парсит их с помощью Python и сохраняет историю цен.

* **`php artisan tso:execute-tasks`**
  Выполняет зависшие/очередные задачи на аккаунтах (баффы, специалисты, шахты).

* **`php artisan tso:lang:export-frontend`**
  Экспортирует языковые строки приложения и игровые переводы в `resources/js/lang/generated/<locale>.json` для SPA-бандла.

* **`php artisan tso:lang:import`**
  Импортирует игровые переводы из XML-экспорта игры в `lang/<locale>/game.php`.

---

## Фоновые воркеры и Деплой

В продакшн-окружении для автоматической работы бота необходимо настроить следующие процессы в фоновом режиме (например, через **Supervisor** или **systemd**):

1. **Очереди Laravel (Queue Worker)**:
   ```bash
   php artisan queue:work --queue=default
   ```
2. **Планировщик задач Laravel (Cron)**:
   Добавьте запись в crontab сервера:
   ```cron
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```
3. **Пингование шедулера TSO**:
   Команда `tso:run-scheduler` может быть запущена как демон или настроена в Laravel Scheduler (`app/Console/Kernel.php`).

## Docker Development

### Quick Start

```bash
# First-time setup
make install

# Or manually:
cp .env.docker .env
make build
make up
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Visit `http://localhost` to access the application.

### Services

| Service | URL / Port | Description |
|---|---|---|
| **App** (PHP-FPM) | Internal :9000 | Laravel application |
| **Nginx** | http://localhost:80 | Web server |
| **Node** (Vite) | http://localhost:5173 | HMR dev server |
| **PostgreSQL** | localhost:5432 | Database |
| **Redis** | localhost:6379 | Cache / Queue / Sessions |
| **Worker** | — | Queue worker |
| **Scheduler** | — | Task scheduler |

### Available Commands

Run `make help` to see all commands:

| Command | Description |
|---|---|
| `make install` | First-time setup |
| `make up` | Start containers |
| `make down` | Stop containers |
| `make build` | Rebuild images |
| `make fresh` | Full rebuild with migrate + seed |
| `make shell` | Open app container shell |
| `make test` | Run test suite |
| `make lint` | Check code style |
| `make analyse` | Run PHPStan |
| `make logs` | Follow container logs |
| `make prod-up` | Start production build |

### Production

```bash
make prod-up
```

This uses `docker-compose.prod.yml` override which:
- Builds optimized production images (no dev dependencies)
- Bakes assets into the image (no Vite dev server)
- Mounts TLS certificates at runtime from `./docker/nginx/certs` (`fullchain.pem` and `privkey.pem`)
- Enables restart policies
- Adds healthchecks

> **Note on TLS Certificates:** Production certificates must be provided at runtime under `docker/nginx/certs/` (`fullchain.pem` and `privkey.pem`). Private keys and certificate files are excluded from git. In development mode, `docker/entrypoint.sh` automatically generates self-signed certificates if none are present.

### Troubleshooting

**Port conflicts with OSPanel**: Stop OSPanel services before starting Docker, or change ports in `docker-compose.yml`.

**Permission issues**: Run `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`.

**Fresh database**: Run `make fresh` to destroy volumes and rebuild everything.

### Single-Operator Model (ADR-001)

The application is **single-operator by design**. First-time registration creates the initial administrator user; subsequent registration requests are blocked server-side at the database transaction layer. All administration endpoints run under this single operator principal.

> **Reversal / Migration Path:** If multi-operator support is required in the future, the migration path is documented in `docs/spec/architecture-hardening/decisions.md` (ADR-001): one additive database migration adding `user_id` columns, defining `scopeOwnedBy()`, and registering 3 Eloquent policies.

## Архитектура системы


Полная визуализированная схема зависимостей фронтенда, контроллеров, сервисов, фоновых Python-парсеров и внешних API Ubisoft/TSO доступна на Foglamp:
👉 **[Посмотреть Codebase Scan на Foglamp](https://www.foglamp.dev/scan/tso-manager-admin-fs2rdm)**

