# Спецификация и План реализации: Многоуровневое кеширование (Multi-Level Cache)

**Проект:** TSO Admin / Market Analytics (Laravel 10 + Vue 3 SPA)  
**Дата:** 2026-07-25  
**Статус:** Реализовано  
**Размещение:** `docs/spec/multi-level-cache/plan.md`

---

## 1. Обзор архитектуры кеширования

Реализована 4-уровневая система кеширования:

```
[Браузер (Client)]
  L1  localStorage (SWR-кеш через apiCacheService.js, TTL + версия схемы)
  L2  HTTP-кеш браузера (Middleware HttpCacheHeaders: ETag, Cache-Control, 304 Not Modified, X-Data-Version)
──────────── сеть ────────────
[Laravel Backend]
  L3  Application Cache (MarketCacheService: Redis/Array): кеширование JSON-ответов аналитики по версиям данных серверов
  L4  Данные БД + Redis: кеш настроек (Setting::get), локи синка (market_sync_lock)
```

---

## 2. Ключевые компоненты

1. **`App\Services\MarketCacheService`**:
   - Отвечает за генерацию версий данных серверов (`market:data_version:{server_id}`).
   - Выполняет канонизацию query-параметров и учет текущей локали (`app()->getLocale()`) в ключах L3.
   - Метод `bumpDataVersion($serverId)` вызывается при успешном завершении `MarketSyncService::sync()` и при CRUD-операциях с подключениями серверов.

2. **`App\Http\Middleware\HttpCacheHeaders`**:
   - Подключен к группе маршрутов `public/market`.
   - Добавляет заголовки `ETag`, `Cache-Control: public, max-age=60, stale-while-revalidate=300`, `X-Data-Version`.
   - Поддерживает условный запрос `If-None-Match`, отдавая статус `304 Not Modified`.

3. **`App\Models\Setting`**:
   - `Setting::get($key)` кеширует значения с TTL 300 с.
   - `Setting::set($key, $value)` мгновенно сбрасывает кеш соответствующего ключа.

4. **`resources/js/services/apiCacheService.js`**:
   - Клиентская служба Stale-While-Revalidate (SWR) поверх `localStorage`.
   - Безопасно очищает устаревшие записи при исчерпании квоты памяти (LRU).

---

## 3. Критерии приёмки и верификация

- [x] Повторные запросы аналитики рынка отдаются из L3/L2 без выполнения тяжелых SQL.
- [x] При успешном синке рынка версия сервера инкрементируется (`bumpDataVersion`), инвалидируя устаревшие ключи L3/L2/L1.
- [x] Время жизни офферов (`time_left`) рассчитывается динамически от `expires_at`, предотвращая застывание таймеров.
- [x] Админские логи и статусы серверов не кешируются (всегда live).
- [x] Пройдены все обязательные гейты (`php artisan test`, `pint`, `phpstan`, `npm run build`).
