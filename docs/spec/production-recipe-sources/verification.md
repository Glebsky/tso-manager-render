# Verification

Как доказать, что работа сделана. Главный урок прошлой итерации: 294 зелёных теста не помешали трём зданиям не работать (P-41).

---

## 1. Главный тест — `CatalogCoverageTest`

Этот тест важнее всех остальных вместе взятых.

Логика:

```
ожидаемые_типы = все productionType из ICONS   → должно быть 69

для каждого типа:
    источник = metadata[тип].recipe_source

    assert источник не null
        → "Тип {N} не имеет источника. См. recipe-source-matrix.md"

    если источник начинается с "unsupported:":
        assert count(recipes[тип]) == 0
        continue

    assert count(recipes[тип]) > 0
        → "Тип {N} источник '{src}' дал 0 рецептов"
```

**Ключевой момент:** список типов берётся из ICONS, а НЕ из `config/game_production.php`. Если взять из конфига — тест будет всегда зелёным и бесполезным. Это методологическая суть всей задачи.

---

## 2. Тесты точных чисел

Все числа взяты из `fixtures/summary.json` и проверены на реальных файлах.

| Тест | Ожидание |
| --- | --- |
| `test_type_0_has_nine_regular_units` | 9 |
| `test_type_8_has_seven_elite_units` | 7 |
| `test_elite_soldier_is_in_type_zero` | `EliteSoldier` ∈ тип 0 |
| `test_type_2_has_three_skillpoints` | `[Manuscript, Tome, Codex]` |
| `test_type_4_has_sixteen_collections` | 16 |
| `test_country_saying_is_not_a_recipe` | `CountrySaying` ∉ тип 4 |
| `test_type_6_has_six_buffs` | 6 |
| `test_type_11_has_six_buffs` | 6 |
| `test_type_1_has_full_buff_pool` | 299 |
| `test_type_5_unchanged` | 269 |
| `test_producer_count` | 69 |
| `test_production_lists_count` | 71 |

Если число не совпало — **не правь тест под реальность**. Найди причину расхождения. Если расхождение объясняется новой версией игровых файлов — задай вопрос и обнови и тест, и `fixtures/summary.json`, и `recipe-source-matrix.md` вместе.

---

## 3. Тесты нормализаторов

### 3.1 Длительность

| Вход | Ожидание |
| --- | --- |
| `<TimedProduction duration="255600">` | 255600 |
| `<Buff productionTime="1800">` | 1800 |
| `<MilitaryUnit productionTimeSeconds="180">` | 180 |
| `<skillPoint><productionLevel productionTime="216000">` | 216000 |
| `<collection productionTime="20">` | 20 |
| узел без любого из трёх атрибутов | исключение |

Отдельный тест: ни один рецепт во всём каталоге не имеет `duration_seconds === 0`.

### 3.2 Стоимость мгновенного завершения

Отдельный тест на `InstantBuildCosts` с заглавной I (P-35): все 16 коллекций имеют не-null значение.

### 3.3 Стоимость ресурсов

| Форма | Пример из реальных данных |
| --- | --- |
| `<Costs><Cost name count>` | `Recruit` → Population 1, Beer 5, BronzeSword 10 |
| `<cost name count>` | `Manuscript` тир 0 → SimplePaper 250, Nib 200, Coin 10 |
| `<resource name amount>` | `RedNoseCollection` → CollectibleHerbs 8 |

---

## 4. Тесты политики

По одному тесту на каждый новый шаг из `design.md` §8:

| Сценарий | Ожидаемая причина отказа |
| --- | --- |
| Заказ в здание типа 7 | `production_type_unsupported` |
| `amount = 2` для `Tome` | `amount_exceeds_recipe_limit` |
| `stacks = 2` для `Tome` | `stacks_exceeds_recipe_limit` |
| Коллекция с неактивным событием | `recipe_requires_inactive_event` |
| `amount = 26` для обычного бафа | `amount_exceeds_recipe_limit` |
| `amount = 25` для обычного бафа | проходит |

Плюс тест порядка: заказ в здание типа 7 с несуществующим рецептом возвращает `production_type_unsupported`, а не `recipe_unknown`.

---

## 5. Тесты калькулятора

| Сценарий | Ожидание |
| --- | --- |
| `Recruit × 5` | `resources.Beer = 25`, `resources.BronzeSword = 50`, `population = 5`, `Population` НЕ в `resources` |
| `Tome × 1` | `is_lower_bound = true` |
| Обычный баф | `is_lower_bound = false` |
| Баф без `<Costs>` | `complete = false`, `resources = []` |
| `amount=3, stacks=2, duration=100` | `duration_seconds = 600` |

---

## 6. Тесты локатора

- Находит GLOBALS по маркеру `<TimedProductionList`.
- Находит ICONS по маркеру `productionType="`.
- Находит SKILLPOINTS по `<skillPoint `.
- Находит COLLECTIONS по `<collections>`.
- При переименовании файлов в фикстуре тест всё равно зелёный (P-43).
- Греп-тест: в `app/` и `config/` нет ни одного вхождения строки `gfx_settings_`.

---

## 7. Фронтенд

| Сценарий | Ожидание |
| --- | --- |
| Здание типа 7 | текст «Поддержка ещё не реализована» |
| Каталог пуст у поддерживаемого типа | «Каталог не загружен» |
| Всё отфильтровано по уровню | «Нет доступных рецептов» |
| Переплётчик | 3 рецепта, слайдеры скрыты, цена с «≥» |
| Казармы | 9 рецептов, население отдельной строкой |
| Ратуша | 16 рецептов |

---

## 8. Ручная проверка в игре

Автотесты не доказывают, что сервер примет заказ. Перед сдачей прогони вручную по одному заказу:

1. Переплётчик (grid 8998) → `Tome` → `errorCode = 0`, заказ виден в игре.
2. Казармы (grid 9180) → `Recruit` × 5.
3. Ратуша (grid 8825) → любая коллекция с активным событием.
4. Снова проверить работавшее раньше: Прилавок с закусками (9808), Каменоломня (9361) — регрессий быть не должно.

**Пункт 4 обязателен.** Половина риска этой задачи — сломать то, что уже работает.

При ошибке сохрани сырой ответ AMF в `docs/references/amf/` и заведи запись в `docs/foundbugs.md`.

---

## 9. Финальный чек-лист

- [ ] `vendor/bin/pint --test` — чисто
- [ ] `vendor/bin/phpstan analyse` — ноль ошибок
- [ ] `php artisan test` — всё зелёное, число тестов выросло относительно 294
- [ ] `npm run build` — без ошибок
- [ ] `CatalogCoverageTest` существует и берёт список типов из ICONS
- [ ] Ни одного хардкода имён файлов с хэшами
- [ ] Все AC из `requirements.md` отмечены
- [ ] Три ручных заказа в игре прошли, регрессий нет
- [ ] `walkthrough.md` обновлён
- [ ] Открытые вопросы из `open-questions.md` либо закрыты, либо явно отложены
