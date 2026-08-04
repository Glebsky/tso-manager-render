# collect_pickups — сбор коллекций на острове

Новый тип действия Task Planner: «клик» по всем доступным коллекциям (pickups)
собственной зоны. Графика не нужна — это обычный серверный вызов.

## Клиентская матчасть (client_scripts)

```as3
// com.bluebyte.tso.service.services.PickupService
public function executePickup(_arg_1:dUniqueID):Boolean {
    if (_arg_1) { sendToCurrentZone(COMMAND.EXECUTE_PICKUP, _arg_1); return true; }
    return false;
}
```

* `COMMAND.EXECUTE_PICKUP = 13002` (рядом: `ADD_PICKUP = 13001`, `UPDATE_PICKUP_DATA = 1189`,
  `SET_BUILDING_PICKUP = 52`, `SPAWN_PICKUPS_CHEAT = 1188`).
* Payload — голый `dUniqueID` (`uniqueID1` + `uniqueID2`), **без** обёртки `dServerAction`,
  в отличие от баффов и производства.
* Неудача на стороне клиента логируется как
  `"uid:... unable to find resource pickup to execute!"` — трактуем как skip.
* Список коллекций лежит в `dZoneVO.pickups:ArrayCollection`, типы —
  `COLLECTIBLE_BUILDING_NORMAL = 0`, `COLLECTIBLE_BUILDING_EVENT = 1`.
* Спавн управляется `CollectionsManager` (`homelandSpawnTimeMin/Max`,
  `minLevelForHomelandSpawn`) — разумный интервал задачи 20–30 минут.

## Что изменено

| Файл | Изменение |
| --- | --- |
| `app/Services/TsoAmfService.php` | `CMD_EXECUTE_PICKUP = 13002` + `executePickup()` |
| `app/Services/Tasks/Handlers/CollectPickupsHandler.php` | новый хендлер `supports('collect_pickups')` |
| `app/Exceptions/PickupsUnavailableException.php` | новое исключение |
| `app/Providers/TaskServiceProvider.php` | регистрация хендлера |
| `app/Http/Requests/Tasks/ScheduledTaskRequest.php` | `TASK_TYPES`, `STEP_TASK_TYPES`, `pickupRules()` |
| `storage/app/parse_zone.py` | `extract_pickups()` + ключ `pickups` в JSON |
| `lang/{en,ru,uk}/ui.php` | `tasks.action.collect_pickups`, `tasks.type_label.collect_pickups` |
| `lang/{en,ru,uk}/tasks.php` | `error.pickups_unavailable`, блок `pickups.*` |
| `resources/js/lang/generated/{en,ru,uk}.json` | те же ключи для фронта |
| `resources/js/views/Tasks.vue` | пункт 🧺 в дропдауне, иконка, лейбл, payload, добавление шага |
| `tests/Feature/ScheduledTaskCollectPickupsTest.php` | 7 тестов |

## Payload задачи

```json
{
  "pickup_type": "all",   // all | normal (0) | event (1)
  "resources": ["Wood"],  // опциональный белый список ресурсов
  "limit": 20,             // максимум кликов за запуск
  "delay_ms": 250          // пауза между кликами, 0–5000
}
```

Результат выполнения: `Collectibles: 7/9 collected, 2 skipped (2x code 2001)`.

## Поведение при ошибках

* Коды `1005` / `1012` пробрасываются наверх — `TaskExecutionService`
  делает релогин и повторяет шаг.
* Любой другой код (протухший uid, переполненный склад коллекций
  `pickupManagerLimitsPerType`) — warning в лог и счётчик skipped, задача не падает.
* Зона всегда читается заново внутри самой задачи, кеш `zone_data` не используется:
  uid пикапа живёт только до сбора.

## Патч parse_zone.py

Уже применён. В скрипт добавлены хелперы `_attr()` / `_as_items()` и функция
`extract_pickups(zone_obj)`, которая вызывается в ветке `dZoneVO` и кладёт результат
в `result["pickups"]`:

```python
pickups.append({
    "unique_id1": uid1,
    "unique_id2": uid2,
    "type": ptype,          # 0 = normal, 1 = event
    "resource": "...",      # resourceName_string / item_string / name
    "grid": grid,
})
```

Если в реальном дампе поля `dZoneVO.pickups` называются иначе — распечатай
один раз `vars(zone.pickups[0])` и допиши имя в список алиасов в `extract_pickups()`.
Хендлер понимает оба написания uid (`unique_id1` и `uniqueID1`), а если ключа
`pickups` в ответе нет вообще — бросает `PickupsUnavailableException`.

## После развёртывания

1. `php artisan config:clear && php artisan cache:clear`
2. Пересобрать фронт: `npm run build` (ключи в `resources/js/lang/generated/*.json`
   уже добавлены; при желании можно перегенерировать их штатной командой
   экспорта локализаций).
3. `php artisan test --filter=ScheduledTaskCollectPickupsTest`
4. Проверить на одном аккаунте задачей `collect_pickups` с `schedule_type=interval`,
   `interval_minutes=30`.
