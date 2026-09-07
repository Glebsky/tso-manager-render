# Security and Production Hardening — Workstream Plan

Рекомендуемая первая спецификация для текущего проекта. Работы выполняются независимо, отдельными ветками/PR.

## Workstreams

1. **Dependency remediation** — CommonMark, Browserslist, Nanoid, PostCSS.
2. **Single-operator concurrency** — реальная PostgreSQL защита первой регистрации.
3. **Trusted hosts and proxies** — allowlist Host и конкретные proxy ranges.
4. **Logout CSRF** — удалить GET `/logout`, оставить POST + auth + CSRF.
5. **Persistent APP_KEY** — production fail-fast; единый ключ для всех процессов.
6. **Docker public volume** — единый источник `public/index.php` и build assets.
7. **Security headers** — CSP/HSTS и regression tests.
8. **Quality gate recovery** — 3 failing tests и 2 PHPStan errors.

## Порядок

```text
Baseline → Quality gate recovery → Authentication/concurrency → Host/proxy
→ APP_KEY/Docker → Dependencies → Headers → Full verification
```

## Разделение изменений

- Dependency update не совмещать с refactoring.
- Auth fixes не совмещать с Docker.
- Docker topology проверять отдельным smoke test.
- CSP вводить report-only этапом, затем enforcing mode.
