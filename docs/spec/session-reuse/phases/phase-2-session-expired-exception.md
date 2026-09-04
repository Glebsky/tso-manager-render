# Фаза Ф2. Типизированное исключение

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)

### Шаги

- [ ] **Ф2.1.** Создать файл `app/Exceptions/SessionExpiredException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the game server explicitly rejects the stored web session
 * (dsoAuthUser + dsoAuthToken + cookie jar) and a full re-login is required.
 *
 * Deliberately extends RuntimeException and NOT TaskExecutionException: this is
 * a transport-level signal, every existing call site already catches Exception,
 * and it must not be rendered as a user-facing task payload.
 */
final class SessionExpiredException extends RuntimeException {}
```

  Подводные камни:
  - Наследовать от `TaskExecutionException`/`GameServerErrorException` **нельзя**: у них другой конструктор (ключ перевода + payload + HTTP-код) и другая семантика.
  - Класс `final`, без дополнительных полей — KISS.

- [ ] **Ф2.2.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `feat(exceptions): add SessionExpiredException`.

---

