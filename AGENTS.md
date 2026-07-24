# AGENTS.md

## CONSTITUTION POLICY
- **НЕ читай** `docs/spec/constitution.md` и `docs/spec/constitution.md` автоматически.
- Читай `docs/spec/constitution.md` и и `docs/spec/constitution.md` **ТОЛЬКО** по явному указанию пользователя или при выполнении сложных архитектурных задач.

## QUICK RULES
1. **Stack**: PHP 8.1 (без PHP 8.2+ синтаксиса), Laravel 10, Vue 3 (`<script setup>`), Vite, Tailwind, Postgres/Redis.
2. **Workflow**: Проверяй реальный код перед изменениями. Делай минимально необходимые изменения. Не изменяй `.env` и секреты.
3. **Gates**: Перед завершением задачи запускай: `php artisan test`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `npm run build`.
4. **Safety**: Запрещены деструктивные команды (`rm -rf`, `git reset --hard`, `DROP/TRUNCATE`) без явного подтверждения.
5. **Exclusions**: Не сканируй и не анализируй служебные каталоги (`vendor/`, `node_modules/`, `bootstrap/`, `storage/`, `public/build/`).

