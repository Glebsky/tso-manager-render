# Quality Gates и Definition of Done

## Узкие проверки

После каждой задачи запускать тест конкретного класса или группы:

```bash
php artisan test --filter=<TestName>
./vendor/bin/phpstan analyse <changed-path>
./vendor/bin/pint --test <changed-path>
```

Для frontend:

```bash
npm run build
```

## Полные обязательные проверки

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
composer audit --locked
npm audit
```

## Запрещённые способы получить green build

- удалить или пропустить тест;
- ослабить assertion без изменения контракта;
- добавить `@phpstan-ignore`;
- добавить исключение в baseline;
- отключить middleware/security check;
- заменить production semantics SQLite-поведением;
- скрыть ошибку пустым catch;
- увеличить timeout без анализа причины.

## Requirement Traceability Matrix

В `verification.md` должна быть таблица:

| Requirement | Test/Check | Result | Evidence |
|---|---|---|---|
| FR-001 | ConcurrentRegistrationTest | PASS | command output |
| SEC-001 | HostHeaderTest | PASS | command output |

## Definition of Done

- Все MUST-требования реализованы.
- Все requirement IDs связаны с тестом или ручной проверкой.
- Нет новых PHPStan ошибок.
- Нет новых Pint нарушений.
- Нет новых High/Critical dependency findings без утверждённого exception.
- Миграции протестированы forward и rollback либо rollback явно признан невозможным.
- Секреты не попали в логи, ответы и fixtures.
- Documentation соответствует фактическому коду.
- `deviations.md` содержит все отклонения.
- `implementation-report.md` завершён.
