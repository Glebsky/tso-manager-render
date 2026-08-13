# Design: Upgrade to PHP 8.5

- **Пакет:** php-85-upgrade v1.0
- **Статус:** Draft / Planning

## 1. Архитектура Среды Исполнения

Приложение TSO Manager исполняется в изолированных Docker-контейнерах на базе Alpine Linux, а также может запускаться локально через OpenServer / CLI.

### 1.1. Базовый Docker-образ
Существующий `Dockerfile` использует мультистадийную сборку на основе `php:8.4-fpm-alpine`.

**Изменения в `Dockerfile`:**
```dockerfile
- FROM php:8.4-fpm-alpine AS base
+ FROM php:8.5-fpm-alpine AS base
```

Необходимые системные пакеты Alpine и PECL-расширения остаются неизменными:
- `pdo_pgsql`
- `pdo_sqlite`
- `zip`
- `redis` (через `pecl install redis && docker-php-ext-enable redis`)
- `Py3AMF` (для Python integration, если используется)

### 1.2. Конфигурация Composer (`composer.json`)

В `composer.json` вносятся изменения в секции `require` и `config.platform`:

```json
{
    "require": {
-       "php": "^8.4",
+       "php": "^8.5",
        "guzzlehttp/guzzle": "^7.2",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.0",
        "laravel/tinker": "^2.9"
    },
    "config": {
        "platform": {
-           "php": "8.4.0"
+           "php": "8.5.0"
        }
    }
}
```

## 2. Матрица Зависимостей и Анализ Совместимости

Ниже представлена матрица основных сторонних пакетов и их статус совместимости с PHP 8.5:

| Пакет | Заявленная версия в composer.json | Статус совместимости с PHP 8.5 | Действие |
| :--- | :--- | :--- | :--- |
| `laravel/framework` | `^12.0` | Совместим (Laravel 12 поддерживает PHP 8.4+) | Сохранить `^12.0` |
| `laravel/sanctum` | `^4.0` | Совместим | Сохранить |
| `guzzlehttp/guzzle` | `^7.2` | Совместим | Сохранить |
| `phpstan/phpstan` | `^2.2` | Совместим (PHPStan 2.x поддерживает PHP 8.5 AST) | Сохранить |
| `larastan/larastan` | `^3.10` | Совместим | Сохранить |
| `phpunit/phpunit` | `^10.5 \|\| ^11.0` | Совместим | Сохранить |
| `laravel/pint` | `^1.13` | Совместим | Сохранить |
| `spatie/laravel-ignition` | `^2.4` | Совместим | Сохранить |

## 3. Стратегия Адаптации Кода и Синтаксиса

### 3.1. Исправление Deprecations PHP 8.5
Во время миграции производится проверка логов и результативности PHPStan на предмет предупреждений о deprecated-функциях. При обнаружении устаревших вызовов стандартной библиотеки происходит их замена на рекомендованные альтернативы без изменения бизнес-логики.

### 3.2. Эргономика синтаксиса PHP 8.5
В согласии с `constitution.md`:
- Использование возможностей PHP 8.5 приветствуется при создании новых классов или точечном упрощении DTO/Value-объектов (например, property hooks, asymmetric visibility, explicit return types).
- Отсутствуют массовые автоматические переименования или рефакторинги стабильных сервисов.

## 4. Обновление Документации Управления Проектом

### 4.1. `constitution.md`
В разделе **3. Runtime Contract**:
- Обновляется строка требования к версии: `PHP ^8.5; Laravel ^12.0.`
- Разрешается использование современных возможностей PHP 8.5.

### 4.2. `AGENTS.md`
В разделе **QUICK RULES**:
- Правило 1 обновляется на: `Stack: PHP 8.5, Laravel 12, Vue 3 (<script setup>), Vite, Tailwind, Postgres/Redis.`
