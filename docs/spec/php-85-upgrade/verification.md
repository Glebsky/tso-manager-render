# Verification: Upgrade to PHP 8.5

- **Пакет:** php-85-upgrade v1.0
- **Статус:** Draft / Planning

## 1. Матрица Проверок (Verification Matrix)

| Область проверки | Метод проверки | Ожидаемый результат | Команда / Процедура |
| :--- | :--- | :--- | :--- |
| **Сборка Docker** | Automated Build | Успешная сборка стадий `base`, `development`, `production` без ошибок | `docker compose build` |
| **PHP Runtime Version** | CLI Check | Отображение версии `PHP 8.5.x` | `docker compose exec app php -v` |
| **Тестовая Suite** | PHPUnit / Artisan | 100% зелёные тесты, отсутствие падений и deprecation warnings | `php artisan test` |
| **Стандарты кода** | Laravel Pint | Отсутствие нарушений форматирования | `./vendor/bin/pint --test` |
| **Статический анализ** | PHPStan | Уровень 6 пройден без ошибок | `./vendor/bin/phpstan analyse` |
| **Сборка Фронтенда** | Vite / NPM | Продакшн-бандл скомпилирован успешно | `npm run build` |

## 2. Обязательные Гейты Качества (Mandatory Quality Gates)

Перед принятием работы и завершением задачи **ОБЯЗАТЕЛЬНО** выполнение всех 4 гейтов:

```bash
# 1. Запуск всех unit & feature тестов
php artisan test

# 2. Проверка стиля кода
./vendor/bin/pint --test

# 3. Запуск статического анализа (Level 6)
./vendor/bin/phpstan analyse

# 4. Проверка сборки ресурсов
npm run build
```

## 3. Валидация Docker-Контейнера в Среде Исполнения

1. Запуск контейнеров в фоновом режиме:
   ```bash
   docker compose up -d
   ```
2. Проверка статуса процессов PHP-FPM:
   ```bash
   docker compose exec app php-fpm -t
   ```
3. Проверка логов контейнера на отсутствие ошибок старта расширений (Redis, Postgres, SQLite):
   ```bash
   docker compose logs app
   ```

## 4. Итоговое Подтверждение (Sign-off Criteria)

Задача считается выполненной только при условии, что все проверки из Матрицы и Гейты качества пройдены с 0 ошибок, а документация обновлена в соответствии со спецификацией.
