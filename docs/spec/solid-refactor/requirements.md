# SOLID & Clean Layered Architecture Refactoring Requirements

## 1. Executive Summary

This specification defines the mandatory architectural constraints, design principles, current implementation status, and remaining roadmap for transforming the Laravel administration application into a clean, layered, SOLID-compliant codebase.

---

## 2. Core Architectural Principles (Non-Negotiable)

### 2.1 Dependency Flow Direction
Strict single-direction dependency flow:

```text
HTTP (Controllers / Form Requests / Resources) / Console Commands / Queue Jobs
  ↳ Services (Domain Logic & Orchestration)
      ↳ Models (Eloquent / Persistence Invariants) & External Adapters
```

Rules:
- **HTTP / Console / Jobs** layer depends on **Services**.
- **Services** layer depends on **Models** and explicit interfaces in `App\Services\*\Contracts\`.
- **Models** do not contain business orchestration or HTTP logic.

---

### 2.2 SOLID Enforcement

#### Single Responsibility Principle (SRP)
- Class size target: under 200 lines where practical. No "God Classes".
- Controller methods must be thin (up to 10 lines), delegating validation to Form Requests and response mapping to JsonResources or dedicated Services.
- Services must own a single domain boundary (e.g. `AccountService`, `ScheduledTaskService`, `MarketServerService`).

#### Open/Closed Principle (OCP)
- `switch` or `if/else` branching on entity/action type must be replaced with Strategy Pattern (`TaskActionHandlerInterface`) and dynamic container-bound Registries (`TaskHandlerRegistry`).
- Adding a new task action or market strategy requires adding a new class without modifying existing execution engines.

#### Liskov Substitution Principle (LSP)
- Implementations must honor interface contracts without throwing unexpected non-domain exceptions or changing parameter semantics.

#### Interface Segregation Principle (ISP)
- Domain contracts must be narrow and single-purpose (e.g. `ResourceNameResolver`, `ArbitrageFinder`, `TaskActionHandlerInterface`).

#### Dependency Inversion Principle (DIP)
- High-level domain services depend on abstractions (`Contracts`), bound in dedicated `ServiceProviders` (`MarketServiceProvider`, `TaskServiceProvider`, `TsoServiceProvider`).
- No facades or static service locators inside domain services; all dependencies injected via constructor property promotion.

---

## 3. Technology & Syntax Constraints

- **PHP Constraint**: `^8.1` (PHP 8.1 syntax only; no `readonly class` or PHP 8.2+ features; constructor property promotion and `readonly` properties are enforced).
- **Laravel Framework**: `^10.10`.
- **Strict Types**: `declare(strict_types=1);` in every PHP file.
- **Form Requests**: All incoming HTTP input validated via Form Requests (`App\Http\Requests\...`).
- **No Repository Wrappers**: Eloquent models serve directly as data layer without redundant repository wrappers.
- **Environment Isolation**: `env()` function forbidden outside `config/` directory.

---

## 4. Contract Safeguards & Non-Breaking Behavioral Guarantees

1. **HTTP Endpoints & Verbs**: Every URI, HTTP method, and middleware group in `routes/api.php` remains identical.
2. **Response Payloads**: JSON keys, nesting order, data types, status codes, and string literals must match existing contracts byte-for-byte.
3. **Queue Jobs & Commands**: Payload signatures, queue names (`tso-tasks`), execution tokens, and lock keys remain unchanged.

---

## 5. Quality & Verification Gates

The following 4 automated quality gates are mandatory before any stage is considered complete:

```bash
php artisan test             # 1. Full test suite execution
./vendor/bin/pint --test     # 2. Strict PHP code style verification
./vendor/bin/phpstan analyse # 3. Static analysis checks
npm run build                # 4. Vite production asset compilation
```
