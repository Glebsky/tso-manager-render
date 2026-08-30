<!-- SDD package: log-retention-cleanup v1.0 -->
# SDD: Автоматическая очистка системных логов по Log Retention Policy в Cron

- **Статус:** Approved / Implementation
- **Версия пакета:** 1.0
- **Проект:** Laravel 10 / Vue 3 (TSO Manager)
- **Компонент:** System Log Cleanup (`app/Services/SystemLogCleanupService.php`, `RunSchedulerCommand.php`, `SettingsController.php`)

## Назначение

Настоящая спецификация описывает интеграцию **Log Retention Policy** в автоматический cron-процесс (`tso:run-scheduler`) для очистки устаревших системных логов (`BotLog`) с повторным использованием логики ручной очистки логов (**Clear System Logs**).

## Документы

1. [Requirements](requirements.md) — требования, бизнес-правила, ограничения и критерии приемки.
2. [Design](design.md) — архитектура компонента, диаграмма последовательности, интерфейсы сервисов.
3. [Implementation Plan](implementation-plan.md) — план реализации, затрагиваемые файлы и Definition of Done.
