# Design: Мультивыбор зданий и специалистов в Task Planner

## Архитектура UI и Состояния (`Tasks.vue`)

Все клиентские изменения сосредоточены во фронтенд-компоненте `resources/js/views/Tasks.vue`.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Tasks.vue Form State                            │
├────────────────────────────────────────────────────────────────────────┤
│ - buildingTargetScope: 'self' | 'friend'                               │
│ - selectedFriend: FriendObject | null                                  │
│ - selectedBuildings: Array<{ id, scope, buildingGrid, building, friend }>│
│ - selectedSpecialists: Array<SpecialistObject>                         │
│ - selectedBuff: BuffObject | null                                      │
│ - stepDelay: Ref<number> (Preserves custom values across additions)    │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                    click "Add Step to Sequence"
                                   │
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│               Action Expansion Engine (addStepToSequence)               │
│                                                                        │
│ For each target in selectedBuildings (or selectedSpecialists):         │
│   Create individual action entry:                                       │
│   {                                                                    │
│     task_type: stepActionType,                                         │
│     payload: { grid, unique_id1, unique_id2, target_scope, ... },      │
│     delay_seconds: stepDelay,                                          │
│     meta: { building, specialist, buff, friend, subTaskLabel }         │
│   }                                                                    │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
                                   ▼
┌────────────────────────────────────────────────────────────────────────┐
│                  sequenceActions (Array of Actions)                    │
│      Posted directly to POST/PUT /api/tasks as standard sequence       │
└────────────────────────────────────────────────────────────────────────┘
```

---

## Структуры данных состояния Vue 3

### 1. `selectedBuildings`
Массив выбранных целевых зданий (со своего острова или острова выбранного друга):

```typescript
interface SelectedBuildingTarget {
    id: string; // e.g. "self:1234" or "friend:42:5678"
    scope: 'self' | 'friend';
    buildingGrid: number;
    building: object;
    friend?: {
        id: number;
        nickname?: string;
        username?: string;
    } | null;
}
```

### 2. `selectedSpecialists`
Массив выбранных объектов специалистов:

```typescript
type SelectedSpecialist = {
    uniqueId1: number;
    uniqueID2?: number;
    uniqueId2?: number;
    name?: string;
    type: number;
};
```

---

## Проектирование Компонентов и Модальных Окон

### 1. Переключатель зон в форме
- Кнопки **«Моя зона»** (`self`) и **«Зона друга»** (`friend`).
- При переключении на `friend` выводится кастомный дропдаун выбора друга.
- При клике на «Выбрать здания» открывается модальное окно с загруженным островом (своим или друга в зависимости от выбранного режима).

### 2. Модальное окно выбора зданий (`showBuildingModal`)
- Внутренние табы выбора зон удалены; модальное окно фокусируется на отображении списка зданий целевого острова.
- Чекбокс / индикатор выделения на карточке каждого здания.
- Быстрые кнопки «Выбрать все visible» и «Очистить выбор».
- Поддержка закрытия:
  - Кнопка «❌» в header окна.
  - Клик по оверлею (пустому месту вокруг окна `@click.self="showBuildingModal = false"`).
  - Клавиша `Esc`.

### 3. Задержка действия (`stepDelay`)
- Поле задержки шага сохраняет свое состояние после клика «Добавить шаг» (вызов `stepDelay.value = 5` удален).

### 4. Порядок задач в списке Scheduled Tasks
- Контроллер `ScheduledTaskController@index` использует `.orderBy('id', 'desc')`.
- На фронтенде `groupedTasks` выполняет отсортированную итерацию `[...tasks.value].sort((a, b) => b.id - a.id)`.

### 5. Двухъязычная локализация ошибок
- Файлы `lang/en/ui.php` и `lang/ru/ui.php` хранят переводы справочника TSO `game_error.*`.
- Сервис `GameErrorResolver` и фронтенд-функция `getActionStepError` автоматически отображают текст ошибки на активном языке интерфейса.

### 6. Асинхронный запуск задач и polling статуса
- **Backend (`ScheduledTaskController@execute`)**:
  - Атомарно обновляет статус задачи на `queued` с уникальным `execution_token`.
  - Отправляет фоновую джобу `ExecuteScheduledTaskJob::dispatch($task->id, $token)`.
  - Мгновенно возвращает ответ `{ success: true, queued: true, task: ... }` без ожидания выполнения серии действий, избавляя HTTP-запрос от 60-секундного таймаута.
- **Frontend (`runTaskNow` и `pollTaskExecution`)**:
  - При клике `executingTasks.value[task.id] = true` включается спиннер на кнопке запуска.
  - Если задача вернулась в статусе `queued` / `running`, запускается таймер опроса `pollTaskExecution(taskId)` каждые 2000 мс.
  - При получении статуса `completed` или `failed`:
    - Опрос останавливается (`clearInterval`).
    - Спиннер выключается (`executingTasks.value[taskId] = false`).
    - Пользователю выводится уведомление (успех или локализованная ошибка).

---

## Алгоритм генерации действий (`addStepToSequence`)

```javascript
for (const bTarget of selectedBuildings.value) {
    sequenceActions.value.push({
        task_type: stepActionType.value,
        payload: {
            grid: bTarget.buildingGrid,
            unique_id1: selectedBuff.value?.uniqueId1,
            unique_id2: selectedBuff.value?.uniqueID2 || selectedBuff.value?.uniqueId2 || 0,
            target_scope: bTarget.scope,
            target_player_id: bTarget.scope === 'friend' ? bTarget.friend?.id : null,
            target_player_name: bTarget.scope === 'friend' ? (bTarget.friend?.nickname || bTarget.friend?.username) : null,
            amount: stepActionType.value === 'apply_buff' ? stepAmount.value : 1
        },
        delay_seconds: Number(stepDelay.value || 0),
        meta: {
            building: { ...bTarget.building },
            buff: selectedBuff.value ? { ...selectedBuff.value } : null,
            friend: bTarget.friend ? { ...bTarget.friend } : null
        }
    });
}
selectedBuildings.value = [];
// stepDelay.value остается неизменным!
```
