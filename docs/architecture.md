# Architecture

How MealHubApi is put together. This is the high-level map; per-feature behavior lives in [features/](features/), the request surface in [routes.md](routes.md), and the data layer in [models.md](models.md).

## What this app is

A stateless JSON REST API (Laravel 12) consumed by a separate React SPA. It renders no Blade views of its own and owns no frontend assets beyond Laravel defaults. Auth is **Sanctum personal access tokens** (not JWT/cookies — a deliberate divergence from the sibling MealHub app). It runs against its **own dedicated MySQL database** (`mealhubapi`), independent of MealHub's schema.

## Request lifecycle

```
HTTP request
  → routes/api.php            (role-scoped route groups, throttle + auth:sanctum middleware)
  → Form Request              (App\Http\Requests\** — validation + authorize)
  → Controller (thin)         (App\Http\Controllers\Api\V1\** — delegates only)
  → Service (fat)             (App\Services\** — all business logic + orchestration)
  → Model / Eloquent          (App\Models\** — persistence, relationships, scopes)
  → API Resource              (App\Http\Resources\** — shapes the model for output)
  → ApiResponse envelope      (App\Http\Traits\ApiResponse — {success, data, message})
```

Errors thrown anywhere in that chain (validation, auth, not-found, 500) are caught by the handler in `bootstrap/app.php` and reshaped into the **same** envelope for any `api/*` route, so controllers never hand-format framework errors.

## Layers and their rules

- **Thin controllers, fat services.** Controllers validate (via a Form Request), delegate to a service, and shape the response — no business logic, no multi-step Eloquent orchestration. See [controllers.md](controllers.md).
- **Form Requests** own all validation and authorization. Controllers never call `$request->validate()` inline.
- **Services** (`app/Services/`) hold business logic, one per domain concern (e.g. `AuthService`). Injected via constructor property promotion.
- **API Resources** shape every model/collection returned. Controllers never return raw models or ad-hoc arrays.
- **Models** (`app/Models/`) use the `casts()` method (not the `$casts` property) and hold relationships and query scopes.

## Cross-cutting conventions

- **Consistent envelope.** Success: `{ "success": true, "data": …, "message"? }`. Error: `{ "success": false, "message": …, "errors"? }`. Implemented once in `App\Http\Traits\ApiResponse` and the `bootstrap/app.php` exception handler; reused everywhere.
- **API versioning.** All endpoints live under `/api/v1/...`. New versions get a new prefix + controller namespace (`Api\V1\`, `Api\V2\`, …).
- **Role scoping.** The four roles (customer, admin, restaurant, rider) share one `users` table with a `role` column. Auth is role-scoped so credentials for one role are never valid at another's endpoints. See [features/authentication.md](features/authentication.md).
- **Don't duplicate.** Shared logic used by 2+ controllers/services belongs in a service, trait, or query scope. Prefer extending an existing Form Request/Resource over copying validation arrays.

## Directory map

| Path | Holds |
| --- | --- |
| `app/Http/Controllers/Api/V1/` | Versioned, thin API controllers |
| `app/Http/Requests/` | Form Requests (validation + authorize) |
| `app/Http/Resources/` | Eloquent API Resources (output shaping) |
| `app/Http/Traits/` | Shared HTTP concerns (`ApiResponse`) |
| `app/Services/` | Business logic, one class per domain |
| `app/Models/` | Eloquent models |
| `app/Notifications/` | Mail/database notifications (`OtpNotification`) |
| `routes/api.php` | API route definitions |
| `bootstrap/app.php` | Middleware, exception handler, routing wiring |
| `tests/Feature/` | Endpoint-level feature tests |
| `docs/` | This documentation |

## Testing & tooling

- **PHPUnit** feature tests per endpoint (happy path + each meaningful failure). Model factories for all test data.
- **Pint** enforces PSR-12; runs automatically on every file edit via a `PostToolUse` hook in `.claude/settings.json`.
- `.env` and `config/**` are edit-protected by project settings and are changed by the developer, not tooling.
