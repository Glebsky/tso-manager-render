# Гейты приёмки и ручная проверка

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](README.md) · [spec](spec.md) · [plan](plan.md) · [tasks](tasks.md) · [constitution](constitution.md)

## 10. Гейты приёмки (после каждой фазы)

- [ ] `php artisan test` — зелёный, число тестов не уменьшилось.
- [ ] `vendor/bin/pint --test` — без нарушений.
- [ ] `vendor/bin/phpstan analyse` (или `composer phpstan`) — без новых ошибок. Новую ошибку **нельзя** прятать в `phpstan-baseline.neon`; её нужно исправить.
- [ ] `npm run build` — только если менялся фронтенд. В этой спеке фронтенд не меняется, поэтому шаг пропускается осознанно.
- [ ] `git diff --stat` сверен с [plan.md](plan.md): изменённых файлов не больше, чем в карте.

### 10.1. Ручная проверка на живом аккаунте (после всех фаз)

- [ ] Выполнить две задачи по одному аккаунту подряд с интервалом ~1 минута.
- [ ] В логах второй задачи присутствует `[TsoAmf] Reusing shared game session for account #...`.
- [ ] В логах второй задачи **отсутствуют** `[TsoAuth] legacy login failed`, `[TsoAuth] oauth login failed` и повторный полный логин.
- [ ] `[TsoAuth] Session probe for account #...: HTTP 200, alive=yes` встречается вместо полного логина.
- [ ] Ключи в Redis существуют и переиспользуются:

```bash
docker compose exec redis redis-cli --scan --pattern '*tso:*'
```

  Ожидаются `tso:session_ok:{id}`, `tso:amf_session:{id}:{gen}:{zone}`, `tso:client_id:{id}`, `tso:auth_flow:{id}`.
- [ ] Каталог `storage/app/debug/` не создаётся заново после логина.
- [ ] Файл `storage/app/cookies/account_{id}.txt` существует, права каталога `0700`, `mtime` обновляется пробой.
- [ ] Количество ошибок `1005`/`1012` в `bot_logs` за сутки заметно меньше, чем до изменений.

---

