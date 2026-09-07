# Design

## Current behavior

<Факты со ссылками на файлы и методы.>

## Target flow

```text
Request → FormRequest → Action → Policy/Service → Repository/Model → Resource
```

## Components

| Component | Responsibility | Must not do |
|---|---|---|
| Controller | HTTP boundary | Business logic |
| Action | Use case coordination | Render HTTP |
| Policy | Pure decision | I/O |

## Dependency direction

<Разрешённые зависимости.>

## Data and transaction boundary

<Где начинается/заканчивается транзакция; какие rows/locks.>

## Error model

| Condition | Domain/Application error | HTTP mapping | Retryable |
|---|---|---:|---:|

## Idempotency and concurrency

<Keys, locks, tokens, replay behavior.>

## Compatibility

<Что сохраняется для API/DB/jobs/cache.>

## Alternatives rejected

### ALT-001

- Option: ...
- Rejected because: ...
