# Executor Rules для AI-моделей

1. Выполняй задачи строго по порядку.
2. За один цикл выполняй одну незавершённую задачу.
3. Перед изменением файла прочитай его полностью.
4. Не угадывай API класса, schema или route — найди определение.
5. Не создавай класс, interface, migration или config key без прямого указания задачи.
6. Не переименовывай существующие сущности.
7. Не форматируй и не рефактори несвязанные файлы.
8. Не изменяй public API без требования и compatibility plan.
9. Не используй `array<string,mixed>`, если спецификация определяет DTO.
10. Не используй `env()` вне `config/`.
11. Не логируй passwords, tokens, cookies, authorization headers или raw external payload с секретами.
12. Не ослабляй TLS, CSRF, authentication, authorization или validation ради прохождения теста.
13. Не удаляй failing tests и не обновляй snapshots без доказательства нового контракта.
14. Не добавляй PHPStan ignore/baseline без отдельного решения.
15. Не выполняй destructive DB/git/filesystem команды.
16. После каждого изменения запускай указанную узкую проверку.
17. Если код и спецификация расходятся — остановись и запиши deviation.
18. Если требуется архитектурное решение, которого нет в design/decisions — остановись.
19. Если обнаружены чужие незакоммиченные изменения — не перезаписывай их.
20. Не заявляй о завершении, пока все acceptance criteria и gates не подтверждены.

## Формат отчёта после каждой задачи

```text
Task: TASK-XXX
Status: completed | blocked | failed
Files changed: ...
Tests added/changed: ...
Commands run: ...
Results: ...
Deviations: none | DEV-XXX
Risks remaining: ...
Next allowed task: TASK-YYY
```

## Stop conditions

Немедленно остановиться, если:

- нужен секрет или изменение `.env`;
- требуется destructive operation;
- acceptance criteria противоречат друг другу;
- production schema неизвестна;
- external protocol подтверждён только догадкой;
- невозможно сохранить backward compatibility;
- baseline уже красный и невозможно отделить старую ошибку от новой;
- задача требует изменения файлов вне указанного allowlist.
