<!-- SDD package: buff-production-queue v1.0 -->
# SDD: Очередь производства бафов (`produce_buff`)

- **Статус:** Draft / Awaiting approval
- **Версия пакета:** 1.0
- **Проект:** Laravel 12 / Vue 3 (TSO Manager Admin)
- **Компонент:** Task Planner, TSO AMF protocol layer, Zone snapshot
- **Новый тип задачи:** `produce_buff`

## Назначение

Пакет специфицирует новую возможность: оператор выбирает здание-производитель
(Дом снабжения, Переплётчик, Лаборатория и др.), выбирает рецепты бафов, указывает
количество, видит суммарную стоимость в товарах и время, добавляет шаги в `sequence`
и выполняет их как обычную задачу планировщика по расписанию.

## Документы

Читать в этом порядке. Не приступать к коду, не прочитав `data-sources.md` и
`decisions.md` — там зафиксированы протокольные факты и запреты.

1. [requirements.md](requirements.md) — требования (FR), инварианты (INV), acceptance criteria (AC).
2. [data-sources.md](data-sources.md) — **протокольные факты с точными ссылками на строки** `client_scripts.txt`, `icons.xml`, `globals.xml`.
3. [decisions.md](decisions.md) — ADR. Отклонение от ADR без правки этого файла запрещено.
4. [design.md](design.md) — архитектура backend и frontend, классы, сигнатуры, поток данных.
5. [implementation-plan.md](implementation-plan.md) — пошаговый план с файлами и Definition of Done.
6. [pitfalls.md](pitfalls.md) — известные ловушки. Прочитать до написания кода.
7. [verification.md](verification.md) — тест-план и гейты.

## Краткая суть для нетерпеливых

| Что | Значение |
| --- | --- |
| Команда игры | `COMMAND.START_TIMED_PRODUCTION = 91` |
| Payload | VO `Communication.VO.dTimedProductionVO` (5 полей) |
| **НЕ** использовать | `CMD_STOP_PRODUCTION = 107` — это тумблер вкл/выкл, не очередь |
| Каталог зданий | `docs/references/icons.xml`, атрибут `productionType` у `<Building>` |
| Каталог рецептов | `docs/references/globals.xml`, `<Buff produceable="true">` + `<TimedProductionList>` |
| Стоимость | `<Costs><Cost name count/></Costs>` × `amount` × `stacks` |
| Новый тип задачи | `TaskType::ProduceBuff = 'produce_buff'` |

## Ключевые решения (кратко)

- **Очередь производства ≠ очередь строительства.** Это разные подсистемы игры
  (ADR-1). Очередь производства ключуется по `productionType` в пределах зоны,
  а не по конкретному зданию — см. ADR-2, это контринтуитивно.
- **Одна команда несёт `amount`**, поэтому «10 бафов» — это ОДНО действие sequence
  с `amount: 10`, а не 10 шагов (ADR-3). Это отличие от `collect_building` ADR-9
  и `build-mine` ADR-6, и оно обосновано.
- **Каталог — в конфиге, генерируется artisan-командой** из игровых XML (ADR-5).
- **Стоимость — снапшот в `meta`**, не пересчитывается при исполнении (ADR-6).
- **Отказ из-за занятой очереди — успешный исход задачи**, а не исключение (ADR-7),
  по образцу `build-mine` ADR-12.
