# SOLID & Clean Layered Architecture Requirements

## 1. Executive Summary

This specification defines the mandatory architectural constraints, design principles, RESTful API standards, current implementation status, and remaining roadmap for transforming the Laravel administration application into a clean, layered, SOLID-compliant codebase.

---

## 2. Core Architectural Principles (Non-Negotiable)

### 2.1 Dependency Flow Direction & Strict Dependency Injection
Strict single-direction dependency flow:

```text
HTTP (Controllers / Form Requests / Resources) / Console Commands / Queue Jobs
  ↳ Services (Domain Logic & Orchestration)
      ↳ Models (Eloquent / Persistence Invariants) & External Adapters
```

Rules:
- **Mandatory Constructor Injection**: Every Controller, Service, Handler, Command, and Job MUST inject its dependencies via PHP 8.1 Constructor Property Promotion (`public function __construct(private readonly ServiceClass $service)`).
- **No Static Facades or Helper Locators in Domain Services**: The use of static facades (`DB::`, `Cache::`, `Log::`, `Auth::`, `Storage::`) or `app()` / `resolve()` helpers inside domain services is strictly forbidden. Inject contracts, models, loggers, or `CacheManager` / `Repository` dependencies instead.

---

### 2.2 RESTful API Design Principles
Every API endpoint exposed by the application MUST strictly adhere to REST standards:

1. **Resource-Oriented URIs**:
   - Use plural nouns for resource collections (e.g. `/api/accounts`, `/api/tasks`, `/api/market/servers`).
   - Avoid verb-based URL paths (prefer `POST /api/accounts/{id}/actions` over `/api/execute-account-action`).
2. **Correct Semantic HTTP Verbs**:
   - `GET`: Safe & idempotent retrieval of resources/collections.
   - `POST`: Creation of a new resource or execution of non-idempotent domain actions.
   - `PUT` / `PATCH`: Idempotent or partial update of an existing resource.
   - `DELETE`: Idempotent removal of a resource.
3. **Standard HTTP Response Status Codes**:
   - `200 OK`: Successful read, update, or action response.
   - `201 Created`: Successful resource creation.
   - `204 No Content`: Successful deletion without response body.
   - `400 Bad Request`: Malformed request syntax or invalid operation state.
   - `401 Unauthorized`: Missing or invalid authentication token.
   - `403 Forbidden`: Authenticated user lacks permission for the resource.
   - `404 Not Found`: Target resource identifier does not exist.
   - `422 Unprocessable Entity`: Form Request validation failure.
   - `500 Internal Server Error`: Unhandled server exception.
4. **Stateless Request Processing**:
   - Each HTTP request must contain all authorization tokens and contextual parameters necessary for processing without relying on server-side session state.

---

### 2.3 Presentation Separation & Mandatory JsonResources
- **JsonResource Standard**: Every HTTP API endpoint returning domain entities, model collections, or complex structures MUST serialize data through a dedicated Laravel `JsonResource` or `ResourceCollection` (`App\Http\Resources\*`).
- **No Manual Response Mapping in Controllers**: Controllers must not construct ad-hoc array structures for API entities when a domain `JsonResource` exists.

---

### 2.4 SOLID Enforcement

#### Single Responsibility Principle (SRP)
- Class size target: under 200 lines where practical. No "God Classes".
- Controller methods must be thin (up to 10 lines), delegating validation to Form Requests and response mapping to JsonResources or dedicated Services.
- Services must own a single domain boundary (e.g. `AccountService`, `ScheduledTaskService`, `MarketServerService`, `PopularItemService`).

#### Open/Closed Principle (OCP)
- `switch` or `if/else` branching on entity/action type must be replaced with Strategy Pattern (`TaskActionHandlerInterface`) and dynamic container-bound Registries (`TaskHandlerRegistry`).
- Adding a new task action or market strategy requires adding a new class without modifying existing execution engines.

#### Liskov Substitution Principle (LSP)
- Implementations must honor interface contracts without throwing unexpected non-domain exceptions or changing parameter semantics.

#### Interface Segregation Principle (ISP)
- Domain contracts must be narrow and single-purpose (e.g. `ResourceNameResolver`, `ArbitrageFinder`, `TaskActionHandlerInterface`).

#### Dependency Inversion Principle (DIP)
- High-level domain services depend on abstractions (`Contracts`), bound in dedicated `ServiceProviders` (`MarketServiceProvider`, `TaskServiceProvider`, `TsoServiceProvider`).
- Dependencies injected via constructor property promotion.

---

## 3. Technology & Syntax Constraints

- **PHP Constraint**: `^8.1` (PHP 8.1 syntax only; no `readonly class` or PHP 8.2+ features; constructor property promotion and `readonly` properties are enforced).
- **Laravel Framework**: `^10.10`.
- **Strict Types**: `declare(strict_types=1);` in every PHP file.
- **Form Requests**: All incoming HTTP input validated via Form Requests (`App\Http\Requests\...`).
- **JsonResources**: All HTTP API entity responses serialized via JsonResources (`App\Http\Resources\...`).
- **No Repository Wrappers**: Eloquent models serve directly as data layer without redundant repository wrappers.
- **Environment Isolation**: `env()` function forbidden outside `config/` directory.

---

## 4. Quality & Verification Gates

The following 4 automated quality gates are mandatory before any stage is considered complete:

```bash
php artisan test             # 1. Full test suite execution
./vendor/bin/pint --test     # 2. Strict PHP code style verification
./vendor/bin/phpstan analyse # 3. Static analysis checks
npm run build                # 4. Vite production asset compilation
```
