# Verification

## Traceability

| Requirement | Automated test/check | Expected | Actual |
|---|---|---|---|

## Commands

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
composer audit --locked
npm audit
```

## Manual checks

1. <Проверка и ожидаемый результат.>

## Security regression

- Authentication negative path.
- Authorization negative path.
- CSRF/XSS/SSRF where applicable.
- Secret redaction.
- Concurrency/replay.

## Final result

PASS | FAIL
