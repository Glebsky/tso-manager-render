# Архитектурные правила PHP/Laravel

## Слои и направление зависимостей

```text
HTTP/Console → Application → Domain
Infrastructure → Application/Domain contracts
```

Domain не зависит от Laravel HTTP, Eloquent query builder, Redis, cURL, файловой системы или Python process.

## Controllers

Контроллер:

- принимает типизированный FormRequest;
- вызывает один use case/action;
- возвращает Resource/Response;
- не содержит бизнес-правила, длинные запросы, retry или distributed lock.

## FormRequest

Отвечает за синтаксическую валидацию, простую нормализацию и request authorization. Бизнес-инварианты размещаются в policy/domain/application layer.

## Actions и services

Action используется для одного use case. Service — для небольшого связанного набора операций. Application layer задаёт транзакцию и координирует зависимости, но не знает детали HTTP.

## DTO и enums

DTO обязателен, если данные пересекают слои, имеют известную структуру или больше трёх связанных аргументов. Enum используется вместо magic strings для закрытого набора значений.

## Interfaces

Интерфейс создаётся только на реальной границе: внешний transport, parser, clock, lock provider, storage или несколько реализаций. Интерфейс для каждого класса запрещён.

## SOLID

- **SRP:** один класс — одна причина изменения.
- **OCP:** расширяемость только для подтверждённой вариативности.
- **LSP:** реализации сохраняют предусловия, постусловия и семантику ошибок.
- **ISP:** маленькие use-case oriented interfaces.
- **DIP:** бизнес-слой зависит от контрактов, не от cURL/Redis/Python.

## DRY

Устраняется дублирование знания, а не просто похожие строки. Перед extraction указать минимум два места, общее правило и причину совместного изменения.

## KISS

Предпочитать Laravel facilities, constructor injection, final-классы, явные условия, небольшие методы и простые DTO. Не вводить generic repository, event bus, plugin architecture или фабрики без текущего требования.

## Ошибки

- Domain exceptions не содержат HTTP status.
- HTTP mapping выполняется на границе приложения.
- Нельзя делать пустой `catch` или скрывать ошибку через `@`, кроме документированного best-effort cleanup.
- Секреты не входят в exception message.

## Транзакции и concurrency

- Транзакция располагается вокруг полного бизнес-инварианта.
- Row lock работает только для существующей строки.
- SQLite не используется для доказательства PostgreSQL locking semantics.
- Retry имеет ограничение, backoff и классификацию retryable ошибок.
- Queue job проектируется идемпотентным или защищается execution token/lock.
