# План отката

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](README.md) · [spec](spec.md) · [plan](plan.md) · [tasks](tasks.md) · [constitution](constitution.md)

## 12. План откатки

1. Каждая фаза — отдельный коммит, откат делается `git revert <sha>` в обратном порядке.
2. Быстрый откат поведения TTL без правки кода: `TSO_SESSION_TTL=300` в `.env` + `php artisan config:clear`.
3. Быстрый сброс состояния всех сессий (например, после инцидента):

```bash
docker compose exec redis redis-cli --scan --pattern '*tso:session_ok:*' | xargs -r docker compose exec -T redis redis-cli del
docker compose exec redis redis-cli --scan --pattern '*tso:amf_session:*' | xargs -r docker compose exec -T redis redis-cli del
```

   Это безопасно: аккаунты просто выполнят полный логин при следующем обращении.
4. Файлы cookie при необходимости: `rm -f storage/app/cookies/account_*.txt`.

---

