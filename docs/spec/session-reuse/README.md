# SPEC-001-session-reuse — точка входа

Стабильная переиспользуемая игровая сессия для **TSO Manager Admin**. Эталон поведения — клиент `tso_client`.

## С чего начать агенту-исполнителю

Читай строго в этом порядке. Не приступай к коду, пока не прочитаны первые четыре документа.

| № | Документ | Зачем |
| --- | --- | --- |
| 1 | [constitution.md](constitution.md) | Нерушимые правила, анти-галлюцинационные запреты, инварианты, SOLID/DRY/KISS |
| 2 | [spec.md](spec.md) | Что делаем и зачем, границы области работ |
| 3 | [plan.md](plan.md) | Как делаем: порядок фаз, карта изменяемых файлов |
| 4 | [data-model.md](data-model.md) | Ключи кэша, TTL, переменные окружения — единственный источник правды |
| 5 | [contracts/auth-session.md](contracts/auth-session.md) | Сигнатуры и поведенческие обязательства |
| 6 | [tasks.md](tasks.md) | Рабочая доска: все чекбоксы в одном месте |
| 7 | [phases/](phases/) | Полный текст шагов с дословными якорями кода |
| 8 | [quickstart.md](quickstart.md) | Гейты приёмки и ручная проверка на живом аккаунте |

Справочное: [research.md](research.md) (разбор эталона и что осознанно НЕ делаем), [rollback.md](rollback.md), [deviations.md](deviations.md), [acceptance.md](acceptance.md).

## Структура

```
docs/spec/001-session-reuse/
├── README.md                 ← вы здесь
├── constitution.md           правила высшего приоритета
├── spec.md                   WHAT / WHY
├── plan.md                   HOW
├── research.md               контекст и отклонённые варианты
├── data-model.md             ключи, TTL, конфиг
├── contracts/
│   └── auth-session.md       сигнатуры и обязательства
├── tasks.md                  чек-лист исполнения
├── phases/
│   ├── phase-0-cache-infrastructure.md
│   ├── phase-1-remove-debug-dumps.md
│   ├── phase-2-session-expired-exception.md
│   ├── phase-3-verify-session.md
│   ├── phase-4-load-server-check.md
│   ├── phase-5-sliding-ttl.md
│   ├── phase-6-remember-flow.md
│   ├── phase-7-dry-callers.md
│   └── phase-8-tests.md
├── quickstart.md             гейты и ручная проверка
├── rollback.md               план отката
├── deviations.md             журнал расхождений (заполняет исполнитель)
└── acceptance.md             Definition of Done
```

## Цикл работы над фазой

1. Открой документ фазы из `phases/`.
2. Найди в репозитории дословный якорь кода. Не нашёлся байт в байт — **СТОП**, запись в [deviations.md](deviations.md), никаких догадок.
3. Примени блок замены ровно в том виде, как он дан.
4. Прогони гейты из [quickstart.md](quickstart.md).
5. Зелёные гейты → отметь чекбоксы в [tasks.md](tasks.md) и статус фазы в [constitution.md](constitution.md).
6. Одна фаза = один коммит. Не смешивать фазы в одном коммите.

## Границы области работ (кратко)

- Нет новых зависимостей, миграций и полей в БД.
- Нет 2FA/TOTP и нет работы с капчей — текущее поведение сохраняется дословно.
- Отладочные дампы `storage/app/debug/play_page.html` и `storage/app/debug/flash_vars.json` удаляются безвозвратно.
- Механизм подмены/шаринга сессии (`readSharedSession` / `writeSharedSession`, `setDsId`, поколения) сохраняется и не переписывается.
