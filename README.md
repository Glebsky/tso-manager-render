# TSO Manager Admin

<p align="center">
<a href="https://www.foglamp.dev/scan/tso-manager-admin-fs2rdm"><img src="https://img.shields.io/badge/Foglamp-Codebase%20Scan-brightgreen?style=flat-square" alt="Foglamp Codebase Scan"></a>
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
</p>

TSO Manager Admin is an admin control panel and automation scheduler for **The Settlers Online**. The project allows connecting game accounts, synchronizing game zone states (buildings, specialists, buffs, resources, collectible items), scheduling recurring tasks (applying buffs, sending geologists/explorers, collecting pickups, managing production queues), and monitoring/analyzing the in-game market.

---

## Technology Stack

* **Backend**: PHP 8.5 / Laravel 12 (Sanctum 4.0 for API authentication)
* **Frontend**: Vue 3.5 (`<script setup>`) + Vite 6 + Tailwind CSS 3.4 (SPA embedded in Laravel)
* **Localization**: Multi-language interface (RU, EN, UK) with chunking and dynamic vocabulary loading
* **Game Data Parsing**: Python 3 + Py3AMF (AMF0/AMF3 packet decoding and encoding)
* **Database**: PostgreSQL 17 / MySQL / SQLite (for in-memory tests)
* **Caching & Queues**: Redis 7 / Database
* **Observability & Monitoring**: Sentry (APM and error tracking), Laravel Pulse (system metrics and queues), Laravel Telescope (local debugging), Request ID Tracing (end-to-end tracing in logs and queues)

---

## Getting Started

### 1. System Requirements
* **PHP >= 8.5** (extensions: `pdo`, `pdo_pgsql`, `pdo_sqlite`, `redis`, `zip`, `curl`, `mbstring`, `xml`)
* **Composer >= 2.0**
* **Node.js >= 20.0 & npm**
* **Python 3** with `Py3AMF` library (`pip install Py3AMF` or `pip3 install Py3AMF --break-system-packages`)
* **PostgreSQL 17** and **Redis 7** (or run via Docker)

### 2. Installing Dependencies
Clone the repository and install the PHP and Node.js packages:

```bash
composer install
npm install
```

### 3. Environment Configuration (`.env`)
Copy the configuration template:

```bash
copy .env.example .env
php artisan key:generate
```

Configure database connection settings, Redis, and TSO-specific options in `.env`.

### 4. Database Migrations & Preparation
Run the migrations:

```bash
php artisan migrate
```

---

## Environment Configuration (.env)

Below is a detailed description of all configuration keys from `.env.example`, grouped by functional module:

### 1. Application Settings
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `APP_NAME` | `"TSO Manager"` | Application name, used in page titles, emails, and UI. |
| `APP_ENV` | `local` | Application environment (`local`, `production`, `testing`). |
| `APP_KEY` | *(generated)* | Encryption key for sessions and tokens (generated via `php artisan key:generate`). |
| `APP_DEBUG` | `true` | Debug mode. Must be set to `false` in `production` for security. |
| `APP_TIMEZONE` | `UTC` | Base application timezone. |
| `APP_URL` | `http://localhost` | Base application URL used for link generation and routing. |
| `APP_LOCALE` | `en` | Default application locale (`ru`, `en`, `uk`). |
| `APP_FALLBACK_LOCALE` | `en` | Fallback locale when a translation key is missing in the current locale. |

### 2. Logging
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `LOG_CHANNEL` | `stack` / `daily` | Primary logging channel (`stack`, `daily`, `single`, `sentry`, `sentry_logs`). |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Channel for PHP/library deprecation warnings. |
| `LOG_LEVEL` | `debug` | Minimum logging level (`debug`, `info`, `warning`, `error`). |
| `LOG_STACK` | `single,sentry_logs` | Comma-separated list of channels combined into the `stack` composite channel. |
| `LOG_STDERR_FORMATTER` | `Monolog\Formatter\JsonFormatter` | Formatter for the `stderr` stream (useful for Docker/K8s containers). |

### 3. Database (PostgreSQL)
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `DB_CONNECTION` | `pgsql` | Database driver (`pgsql`, `mysql`, `sqlite`). Primary stack is PostgreSQL. |
| `DB_HOST` | `127.0.0.1` | Database server host. |
| `DB_PORT` | `5432` | PostgreSQL port (default `5432`). |
| `DB_DATABASE` | `tso_manager` | Database name. |
| `DB_USERNAME` | `tso_admin` | Database username. |
| `DB_PASSWORD` | `secret` | Database user password. |

### 4. Cache, Sessions, Queues, and File Storage
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `BROADCAST_DRIVER` | `log` | Event broadcasting driver (`log`, `pusher`, `null`). |
| `CACHE_DRIVER` | `file` / `redis` | Cache driver (`file`, `redis`, `database`). `redis` is recommended for production. |
| `FILESYSTEM_DISK` | `local` | File storage disk (`local`, `public`, `s3`). |
| `QUEUE_CONNECTION` | `sync` / `database` | Queue worker driver (`database`, `redis`, `sync`). |
| `SESSION_DRIVER` | `file` / `redis` | User session storage (`redis`, `file`, `database`). |
| `SESSION_LIFETIME` | `120` | User session lifetime in minutes. |

### 5. Redis and Memcached
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `REDIS_HOST` | `127.0.0.1` | Redis server IP address or hostname. |
| `REDIS_PASSWORD` | `null` | Redis password (if configured). |
| `REDIS_PORT` | `6379` | Redis connection port. |
| `MEMCACHED_HOST` | `127.0.0.1` | Memcached host (if used as cache). |

### 6. Mail Service
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `MAIL_MAILER` | `smtp` | Mail driver (`smtp`, `sendmail`, `log`). |
| `MAIL_HOST` | `mailpit` | SMTP server host (locally `mailpit` or `mailhog`). |
| `MAIL_PORT` | `1025` | SMTP server port. |
| `MAIL_USERNAME` | `null` | SMTP username. |
| `MAIL_PASSWORD` | `null` | SMTP password. |
| `MAIL_ENCRYPTION` | `null` | Encryption type (`tls`, `ssl`, `null`). |
| `MAIL_FROM_ADDRESS`| `"hello@example.com"` | System notification sender email address. |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | Sender name displayed in emails. |

### 7. AWS / S3 Storage (Optional)
| Key | Description |
| :--- | :--- |
| `AWS_ACCESS_KEY_ID` | Access key ID for AWS / S3-compatible storage. |
| `AWS_SECRET_ACCESS_KEY` | Secret access key for AWS / S3. |
| `AWS_DEFAULT_REGION` | S3 region (e.g., `us-east-1`). |
| `AWS_BUCKET` | S3 bucket name for file storage. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Use path-style addressing (recommended for MinIO / local S3 instances). |

### 8. Pusher / WebSockets and Vite
| Key | Description |
| :--- | :--- |
| `PUSHER_APP_ID` / `KEY` / `SECRET` | Credentials for Pusher / Soketi for real-time WebSockets. |
| `PUSHER_HOST` / `PORT` / `SCHEME` | Custom WebSocket server parameters. |
| `VITE_*` | Passes corresponding variables to the frontend build via Vite. |

### 9. TSO Game Client & Scheduler (`config/game.php`)
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `TSO_SCHEDULER_MODE` | `queue` | Scheduler execution mode: `queue` (dispatch to `tso-tasks`/`tso-market` queues), `cron` (run via cron worker), or `sync` (synchronous execution). |
| `TSO_STALE_TASK_TIMEOUT` | `10` | Stale task timeout in minutes (automatically resets stuck `running` tasks). |
| `TSO_SSL_VERIFY` | `true` | Verify SSL certificates when communicating with Ubisoft/TSO game servers. |
| `TSO_HTTP_TIMEOUT` | `30` | HTTP request timeout for TSO game gateways (in seconds). |
| `TSO_SESSION_TTL` | `300` | Active game server AMF session lifetime in seconds before re-initialization. |
| `TSO_SESSION_LOCK_WAIT`| `20` | Maximum session lock wait time (seconds) to prevent concurrent conflicts. |

### 10. Market Analytics & Synchronization (`config/market.php`)
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `MARKET_DEFAULT_SERVER_ID` | `ru` | Default server ID for public market analytics display. |
| `MARKET_OFFER_LIFETIME_HOURS` | `6` | Market offer lifetime in hours (used to filter active listings). |
| `MARKET_CACHE_STRATEGY` | `individual` | Caching and retrieval strategy for market offers: `individual` or `bulk`. |

### 11. Observability & Monitoring (Sentry, Pulse, Telescope)
| Key | Default Value | Description |
| :--- | :--- | :--- |
| `SENTRY_LARAVEL_DSN` | *(DSN)* | Project DSN in Sentry for sending errors and traces. |
| `SENTRY_ENABLE_LOGS` | `true` | Enable sending structured logs to Sentry Logs. |
| `SENTRY_SEND_DEFAULT_PII` | `false` | Allow sending personally identifiable information (IP, email) to Sentry. |
| `SENTRY_ENVIRONMENT` | `local` | Environment name in the Sentry dashboard (`local`, `staging`, `production`). |
| `SENTRY_TRACES_SAMPLE_RATE` | `0.0` | Transaction performance tracing sample rate (`0.0` to `1.0`). |
| `SENTRY_PROFILES_SAMPLE_RATE` | `0.0` | Code execution profiling sample rate in Sentry (`0.0` to `1.0`). |
| `PULSE_ENABLED` | `true` | Enable the built-in Laravel Pulse metrics dashboard (`/pulse`). |
| `TELESCOPE_ENABLED` | `true` | Enable the Laravel Telescope local debugging tool (`/telescope`). |

---

## Telemetry & Observability

The project integrates a comprehensive suite of tools for monitoring, debugging, and performance auditing:

1. **Sentry (`sentry/sentry-laravel`)**:
   - Automatic capture of unhandled exceptions and integration with Laravel's error pipeline (`Integration::handles($exceptions)`).
   - Performance tracing for HTTP requests, queue jobs, and database transactions.

2. **Laravel Pulse (`laravel/pulse`)**:
   - Real-time metrics dashboard: server load, endpoint response times, slow SQL queries, and queue/worker throughput (`/pulse`).

3. **Laravel Telescope (`laravel/telescope`)**:
   - Deep inspection tool for local development (`/telescope`).
   - Isolated from production: declared in `require-dev`, disabled from auto-discovery (`dont-discover`), and loaded strictly in the `local` environment.

4. **Distributed Request ID Tracing**:
   - The `RequestId` middleware validates or generates a unique `X-Request-Id` header.
   - The `request_id` context is automatically shared with the logger (`Log::shareContext`) and passed through queue job payloads (`Queue::createPayloadUsing`) and console commands (`CommandStarting`), linking web requests, background jobs, and worker logs into a unified trace.

5. **Slow Query Logging**:
   - Any SQL query taking longer than 200 ms is automatically logged at the `warning` level (`[Slow query]`).

---

## Running in Development Mode

### Starting the Backend
Local Laravel development server:
```bash
php artisan serve
```

### Building & Running the Frontend (Vite)
For development with Hot Module Replacement (HMR):
```bash
npm run dev
```

To compile the production bundle:
```bash
npm run build
```

---

## Custom Artisan Commands (`tso:*`)

The project extends the standard Laravel CLI with domain-specific commands:

* **`php artisan tso:run-scheduler [--mode=queue|cron|sync] [--work]`**
  Main TSO scheduler dispatcher:
  - Automatically recovers stuck tasks (`recoverStaleTasks`).
  - Reserves and dispatches due scheduler tasks to the queue (`tso-tasks`).
  - Triggers synchronization for connected game market servers (`tso-market`).
  - Triggers periodic synchronization of zones and account data (`tso-accounts`).
  - Runs log cleanup according to the Log Retention Policy.
  - With the `--work` flag, runs the built-in worker to isolate cron invocations.

* **`php artisan tso:sync-market [--sync] [--server=SERVER_ID]`**
  Executes market lot synchronization for configured connections with interval checks and concurrent execution prevention (atomic Redis/Cache locks).

* **`php artisan tso:execute-tasks [--task=ID] [--async]`**
  Executes scheduler tasks (buff application, specialist dispatch, collectible pickups). Allows executing a specific task by ID.

* **`php artisan tso:lang:export-frontend`**
  Exports application language strings and game translations to `resources/js/lang/generated/<locale>.json` for the SPA bundle.

* **`php artisan tso:lang:import`**
  Imports in-game translations from game XML exports into `lang/<locale>/game.php`.

---

## Background Workers & Scheduler (Production)

For reliable and uninterrupted automation in a production environment:

### 1. Dedicated Queues (Queue Worker)
Background processing runs through dedicated queues `tso-tasks`, `tso-accounts`, `tso-market`, and the system `default` queue:

```bash
php artisan queue:work --queue=tso-tasks,tso-accounts,tso-market,default --sleep=3 --tries=3
```

### 2. Laravel Scheduler (Cron)
Add the scheduler invocation to the system crontab:
```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

> The schedule in [`routes/console.php`](routes/console.php) runs `tso:run-scheduler --work` every minute with `withoutOverlapping(10)` and `onOneServer()` safeguards.

---

## Quality Gates

Before finishing tasks or deploying, the project is verified against the following quality gates:

```bash
# 1. Run the full Feature and Unit test suite
php artisan test

# 2. Check code style and standards compliance (Laravel Pint)
./vendor/bin/pint --test

# 3. Static type and contract analysis (PHPStan)
./vendor/bin/phpstan analyse

# 4. Verify clean frontend compilation
npm run build
```

---

## Docker Development & Deployment

### Quick Start with Docker

```bash
# Initial installation (build, launch, keys, migrations)
make install

# Or manually:
make build
make up
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The control panel is available at `http://localhost`.

### Container Services

| Service | Port / URL | Description |
|---|---|---|
| **App** (PHP-FPM) | Internal `:9000` | Laravel 12 application (PHP 8.5) |
| **Nginx** | `http://localhost:80` | Web server |
| **Node** (Vite) | `http://localhost:5173` | Dev server with HMR support |
| **PostgreSQL** | `localhost:5432` | PostgreSQL 17 database |
| **Redis** | `localhost:6379` | Cache / Queues / Sessions |
| **Worker** | — | Queue worker |
| **Scheduler** | — | Task scheduler |

### Available Makefile Commands (`make help`):

| Command | Purpose |
|---|---|
| `make install` | Initial installation and setup |
| `make up` | Start all containers in the background |
| `make down` | Stop all containers |
| `make build` | Rebuild Docker images |
| `make fresh` | Complete rebuild with volume removal, migrations, and seeders |
| `make shell` | Interactive terminal in the application container |
| `make test` | Run the Laravel test suite |
| `make lint` | Check Pint code style and verify no TLS secrets in git |
| `make analyse` | Run the PHPStan static analysis tool |
| `make logs` | Stream container logs in real time |
| `make prod-up` | Start the optimized Production environment |

---

## Architecture Highlights

### Single-Operator Model (ADR-001)
The application is designed around a **Single-Operator** model:
- The first user registration creates the administrator account.
- All subsequent registration attempts are blocked at the database transaction level.
- All administrative endpoints are executed in the context of the single operator.

### Architecture Diagram
A visualized dependency diagram covering the frontend, services, background parsers, and external APIs is available on Foglamp:
👉 **[View Codebase Scan on Foglamp](https://www.foglamp.dev/scan/tso-manager-admin-fs2rdm)**
