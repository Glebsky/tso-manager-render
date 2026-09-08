# Security-by-Design Checklist

## Обязательные разделы threat model

1. Assets.
2. Entry points.
3. Trust boundaries.
4. Threat actors.
5. Authentication и session lifecycle.
6. Authorization для каждой операции и сущности.
7. Validation и canonicalization.
8. Secret storage, encryption и rotation.
9. Logging/redaction.
10. Dependency и supply-chain risks.
11. Deployment assumptions.

## Проверки

### Authentication и sessions

- Session ID регенерируется после login.
- Logout только POST/DELETE и защищён CSRF.
- `APP_KEY` постоянен и одинаков для app/worker/scheduler.
- Cookie имеет HttpOnly, SameSite и Secure в production.

### Authorization

- Наличие `auth:sanctum` не заменяет object-level authorization.
- Route model binding не даёт доступ к чужому объекту.
- Console/jobs проверяют тот же business invariant, что HTTP.

### Input/output

- FormRequest ограничивает тип, размер и допустимые значения.
- URL проверяется по scheme, host и назначению; SSRF запрещён.
- Blade/JS использует безопасную сериализацию.
- Raw SQL принимает bindings; identifiers берутся из allowlist.

### Infrastructure

- TrustHosts включён.
- Trusted proxies перечислены явно.
- Unknown Host отклоняется nginx.
- TLS verification включена.
- HSTS/CSP вводятся с тестами.
- Debug/Telescope/Ignition недоступны в production.

### Concurrency

- Первый пользователь защищён реальной PostgreSQL блокировкой.
- Jobs идемпотентны.
- Locks имеют TTL и безопасное освобождение.
- Retry не создаёт duplicate side effects.

### Dependencies

- `composer audit --locked` и `npm audit` выполняются в CI.
- High/Critical vulnerability блокирует merge или требует документированного risk acceptance.
