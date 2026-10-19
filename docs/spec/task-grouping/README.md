# Task Grouping — группировка запланированных задач

- **Статус:** Implemented (as-built спецификация, составлена по коду после реализации)
- **Формат:** однофайловая спецификация
- **Проект:** Laravel 12 / Vue 3 (TSO Manager), PHP ^8.5
- **Коммиты:** `9550ed6` (поле `group_name`), `fa4fe1f` (сущность `TaskGroup`, CRUD, drag-and-drop), `79e47aa` (фикс стилей)

## 1. Цель

Оператор объединяет запланированные задачи аккаунта в именованные группы, управляет
группами (создать, переименовать, удалить) и меняет их порядок перетаскиванием на
странице Tasks. Группировка влияет только на отображение и не меняет порядок или
расписание выполнения задач планировщиком.

## 2. Модель данных

| Таблица | Поля | Ограничения |
| --- | --- | --- |
| `scheduled_tasks` | `group_name string(120) nullable` | индекс `idx_scheduled_tasks_account_group (account_id, group_name)` |
| `task_groups` | `id`, `account_id`, `name string(120)`, `sort_order uint default 0`, timestamps | FK `accounts` c `cascadeOnDelete`; unique `(account_id, name)`; индекс `(account_id, sort_order)` |

- Связь задачи с группой — по паре `(account_id, group_name)` = `(task_groups.account_id, task_groups.name)`,
  без внешнего ключа. Целостность обеспечивает `TaskGroupService`.
- Миграция `2026_09_26_000000_create_task_groups_table` бэкфиллит группы из уже
  использованных `scheduled_tasks.group_name` (`insertOrIgnore`).

## 3. Требования

- **FR-1.** Задача создаётся/редактируется с необязательным `group_name` (`nullable|string|max:120`), поле возвращается в `ScheduledTaskResource`; дубликат задачи сохраняет группу.
- **FR-2.** Имя группы обязательно, обрезается (`trim`), до 120 символов и уникально в пределах аккаунта.
- **FR-3.** Новая группа получает `sort_order = max(sort_order аккаунта) + 1`.
- **FR-4.** Переименование в одной транзакции обновляет `task_groups.name` и `group_name` всех задач этой группы аккаунта.
- **FR-5.** Удаление группы в одной транзакции обнуляет `group_name` её задач (задачи не удаляются) и удаляет группу.
- **FR-6.** Переупорядочивание принимает упорядоченный список `group_ids` и записывает `sort_order = позиция + 1` одним `UPDATE … CASE`.
- **FR-7.** Список групп сортируется по `sort_order`, затем по `id`; ответ `GET /api/tasks` содержит `groups`.
- **FR-8.** UI группирует задачи по аккаунту и группе; задачи без группы показываются в псевдогруппе `__ungrouped__`, которая всегда последняя, не перетаскивается и не редактируется. Задачу можно перетащить в другую группу.
- **FR-9.** Тексты UI локализованы в `lang/{en,ru,uk}/ui.php` и экспортированы в `resources/js/lang/generated/*.json`.

## 4. API

Все маршруты — под `auth:sanctum`.

| Метод | Путь | Вход | Ответ |
| --- | --- | --- | --- |
| GET | `/api/task-groups` | `?account_id` (опционально) | `TaskGroupResource[]` |
| POST | `/api/task-groups` | `account_id`, `name` | `201`, группа |
| PUT | `/api/task-groups/{taskGroup}` | `name` | группа |
| DELETE | `/api/task-groups/{taskGroup}` | — | `204` |
| POST | `/api/task-groups/reorder` | `group_ids: int[]` | `{ success: true }` |

`TaskGroupResource`: `id`, `account_id`, `name`, `sort_order`, `created_at`, `updated_at`.

## 5. Код

| Слой | Файлы |
| --- | --- |
| Модели | `app/Models/TaskGroup.php`, `app/Models/ScheduledTask.php`, `app/Models/Account.php` |
| Сервис | `app/Services/Tasks/TaskGroupService.php` |
| HTTP | `app/Http/Controllers/TaskGroupController.php`, `app/Http/Requests/Tasks/{Store,Update,Reorder}TaskGroup(s)Request.php`, `app/Http/Resources/TaskGroupResource.php` |
| Фронтенд | `resources/js/views/Tasks.vue`, `resources/js/services/api/tasks.js` |

## 6. Проверка

- `tests/Feature/TaskGroupingTest.php` — FR-1.
- `tests/Feature/TaskGroupTest.php` — FR-2…FR-7 (список, создание, дубликаты, каскадное переименование, удаление с отвязкой, reorder, `groups` в `/api/tasks`).
- FR-8 проверяется вручную на странице Tasks.
- Гейты: `php artisan test`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `npm run build`.

## 7. Известные ограничения / открытые вопросы

- **OQ-1.** `reorder` не проверяет, что все `group_ids` принадлежат одному аккаунту; `sort_order` задаётся по позиции в переданном списке.
- **OQ-2.** Связь по имени без FK: задача может содержать `group_name`, для которого нет строки в `task_groups` (если имя задано напрямую через API задач).
- **OQ-3.** `GET /api/task-groups` без `account_id` возвращает группы всех аккаунтов (допустимо для однопользовательской админки).
