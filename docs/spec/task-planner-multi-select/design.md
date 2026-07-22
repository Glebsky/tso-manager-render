# Design: Мультивыбор зданий и специалистов в Task Planner

## Архитектура UI и Состояния (`Tasks.vue`)

изменения сосредоточены во фронтенд-компоненте `resources/js/views/Tasks.vue`.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Tasks.vue Form State                            │
├────────────────────────────────────────────────────────────────────────┤
│ - selectedBuildings: Array<{ id, scope, buildingGrid, building, friend }>│
│ - selectedSpecialists: Array<SpecialistObject>                         │
│ - selectedBuff: BuffObject | null                                      │
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
Массив выбранных целевых зданий (может включать здания со своего острова и острова друга):

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

### 1. Модальное окно выбора зданий (`showBuildingModal`)
- Табы выбора источника:
  - **Мой город**: отображение зданий текущего аккаунта.
  - **Остров друга**: выбор друга из списка и отображение зданий друга.
- Карточка здания содержит чекбокс / индикатор выделения.
- Клик по карточке переключает статус присутствия в `selectedBuildings`.
- Кнопка "Выбрать все visible" и "Очистить выбор".
- Футер модального окна: Отображение кол-ва выбранных зданий и кнопка "Готово".

### 2. Панель отображения выбранных целей в форме шага
- Вместо одиночной кнопки выбора показывается компактный блок чипов/карточек:
  - Каждая карточка содержит: иконку здания/специалиста, имя, грид/тип, бейдж владельца и кнопку `✕` для быстрого удаления.
  - Кнопка "+ Добавить здания" / "+ Добавить специалистов".

### 3. Алгоритм генерации действий (`addStepToSequence`)
```javascript
if (isBuildingAction(stepActionType)) {
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
}
```
