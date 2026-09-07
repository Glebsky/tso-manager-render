# Security Hardening Workstreams

## WS-01 Quality gate recovery

Результат: исходная ветка имеет green PHPUnit/Pint/PHPStan/build. Исправить ожидания mock для `forgetSessionVerified()`, контракт форматирования task error и тип `Exception|false` в `TsoAuthService` без suppressions.

## WS-02 Single operator

Результат: два конкурентных PostgreSQL запроса создают не более одного пользователя. Выбрать advisory transaction lock или singleton lock row; последовательный SQLite-тест не считается доказательством.

## WS-03 Host/proxy boundary

Результат: unknown Host отклоняется; URL не строится по непроверенному Host; приложение доверяет только фактическому reverse proxy; rate limit IP нельзя подменить пользовательским forwarded header.

## WS-04 Logout

Результат: GET `/logout` отсутствует; POST требует authenticated session и CSRF; legacy client получает безопасный redirect либо документированную несовместимость.

## WS-05 APP_KEY

Результат: production startup завершается ошибкой при отсутствии ключа; app/worker/scheduler используют один persistent secret; restart не ломает encrypted account credentials.

## WS-06 Docker public topology

Результат: nginx и PHP-FPM видят согласованный `public/`; `index.php` присутствует; manifest/assets доступны; worker/scheduler не требуют TLS cert files.

## WS-07 Dependencies

Результат: lock-файлы обновлены минимально; audits не содержат нерешённых High/Critical либо есть утверждённое исключение с reachability-анализом и сроком.

## WS-08 Headers

Результат: HSTS на HTTPS; CSP сначала report-only, затем enforce; inline bootstrap serialization безопасна; headers проверены web/API tests.
