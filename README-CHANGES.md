# TSO Manager — пакет исправлений для Render Free

Распакуйте архив в корень проекта с заменой файлов. Пути внутри архива
уже совпадают с вашей структурой.

```bash
cd /path/к/проекту
unzip -o ~/Downloads/tso-render-fixes.zip
git diff            # посмотрите, что изменилось
```

---

## Содержимое архива

| Файл | Статус | Суть изменения |
|---|---|---|
| `.dockerignore` | **новый** | `.env`, `node_modules`, `.git` больше не попадают в образ |
| `render.yaml` | замена | `CACHE_DRIVER`/`SESSION_DRIVER` -> `file` |
| `Dockerfile.render` | замена | PHP 8.3, `opcache` + `pcntl`, кэш-дружелюбные слои |
| `docker/php/php.ini` | замена | `memory_limit` 128M, OPcache 128M/20k, realpath cache |
| `docker/php/www.conf` | замена | `pm = static`, 3 воркера, slowlog |
| `docker/nginx/render.conf.template` | замена | `fastcgi_buffering on`, `/healthz` без PHP |
| `docker/entrypoint.render.sh` | замена | миграции под флагом, проверка БД, `event:cache` |
| `docker/crontab.render` | замена | `* * * * *` -> `*/5 * * * *` |
| `app/Console/Kernel.php` | замена | убран `--work`, воркер вынесен отдельно |
| `config/database.php` | замена | `DB_SSLMODE` из env, таймаут коннекта |
| `database/migrations/2026_07_27_000000_create_sessions_table.php` | замена | защита `hasTable()` |
| `database/migrations/2026_07_27_000001_create_cache_table.php` | замена | защита `hasTable()` |
| `database/migrations/2026_07_28_085400_create_sessions_table.php` | замена | дубликат сделан идемпотентным |
| `database/migrations/2026_07_28_085401_create_cache_table.php` | замена | дубликат сделан идемпотентным |
| `database/migrations/2026_07_29_000000_add_indexes_to_bot_logs_table.php` | **новый** | индексы на `bot_logs` |

В каждом файле сверху есть комментарий с объяснением, что именно и зачем изменено.

---

## Что АРХИВ НЕ СДЕЛАЕТ ЗА ВАС

Это три шага в дашборде Render, без них большая часть эффекта потеряется.

### 1. Переключить Supabase на pooler (самое важное)

Сейчас у вас `DB_HOST=db.sibqukjvhwlzxgzhfxil.supabase.co` — это Direct Connection,
он доступен только по IPv6, а Render ходит по IPv4.

В Supabase: **Project Settings -> Database -> Connection string -> Transaction pooler**.
Оттуда возьмите значения и впишите в Render -> Environment:

```
DB_HOST     = aws-0-<ваш-регион>.pooler.supabase.com
DB_PORT     = 6543
DB_USERNAME = postgres.<project-ref>
DB_PASSWORD = <тот же пароль>
```

### 2. Поменять драйверы

`render.yaml` применится автоматически только если сервис создан через Blueprint.
Если вы создавали сервис руками — поменяйте в Environment вручную:

```
CACHE_DRIVER   = file      # было database
SESSION_DRIVER = file      # было database
RUN_MIGRATIONS = true      # новая переменная
```

### 3. Ротировать секреты

В присланном архиве лежал `.env` с боевым паролем Supabase, `APP_KEY`
и Pusher-секретами в открытом виде. Смените их.

> `APP_KEY` менять осторожно: он шифрует куки и всё, что прошло через
> `Crypt::encrypt()`. После смены все сессии сбросятся. Если в базе есть
> зашифрованные поля (например, пароли игровых аккаунтов в `accounts`)
> — сначала перешифруйте их, иначе потеряете данные.

---

## Порядок деплоя

```bash
# 1. Распаковать архив
unzip -o tso-render-fixes.zip

# 2. Проверить локально (гейты из вашего AGENTS.md)
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build

# 3. Закоммитить
git add -A && git commit -m "perf: оптимизация под Render Free"

# 4. СНАЧАЛА поменять переменные в дашборде Render (шаги 1-2 выше)
# 5. Потом push -> autoDeploy подхватит
git push
```

После первого успешного деплоя поставьте `RUN_MIGRATIONS=false` —
тогда рестарты контейнера перестанут ждать базу.

---

## Как замерить результат

До и после:

```bash
curl -o /dev/null -s -w "connect:%{time_connect} ttfb:%{time_starttransfer} total:%{time_total}\n" \
  https://tso-manager.onrender.com/
```

Ожидаемый `ttfb`: было 2-3 с, должно стать 0.4-0.8 с.

Если всё ещё медленно — включённый теперь slowlog покажет точку зависания
со стектрейсом прямо в логах Render — ищите строки `[pool www] ... script_filename`.

---

## Чего этот архив НЕ чинит

1. **Засыпание Free-сервиса.** После 15 минут без трафика контейнер
   останавливается, первый запрос после паузы ждёт 50+ секунд.
   Лечится только платным планом.
2. **0.1 CPU.** Это жёсткий потолок. Мы сняли с него лишнюю нагрузку,
   но больше процессора не появится.
3. **Задержка планировщика.** Задачи теперь запускаются раз в 5 минут,
   а не каждую минуту. Это осознанный размен скорости сайта на
   точность расписания. Как вернуть — написано в `docker/crontab.render`.
4. **Сессии на эфемерном диске.** При каждом рестарте пользователи
   разлогиниваются. Если неприемлемо — верните `SESSION_DRIVER=database`,
   но вернётся и часть задержки.

---

## Что стоит сделать дальше (не вошло в архив — требует решений по коду)

1. **`HttpCacheHeaders` ходит в БД на каждом запросе.**
   `resolveServerId()` делает `MarketServerConnection::value('server_id')` без кэша.
   Стоит обернуть в `Cache::remember(..., 300, ...)`.

2. **Миддлваре висит на всём.** Проверьте `routes/`: если `HttpCacheHeaders`
   применяется глобально, ограничьте его только market-роутами.

3. **`DashboardController` делает 6+ запросов.** Четыре `count()` можно
   свести в один запрос с `selectRaw` и `FILTER (WHERE ...)`, а весь блок `stats`
   закэшировать на 30-60 секунд.

4. **`RunSchedulerCommand::processAccountSync()` делает `Account::all()`**
   и бегает по всем аккаунтам в цикле. Стоит фильтровать по
   `last_sync_at` прямо в SQL, а не в PHP.

5. **Redis.** Если когда-нибудь перейдёте на платный план — Render Key Value
   закроет и кэш, и сессии, и очередь сразу, без компромиссов с эфемерным диском.

6. **Background Worker отдельным сервисом.** На платном плане вынесите
   `queue:work` из веб-контейнера — тогда фон вообще перестанет конкурировать
   с HTTP за CPU, и можно будет вернуть поминутное расписание.
