# Architecture & Conventions

Hand-written. Describes how code in this project is **meant** to be structured and why.
For what the code currently *is*, see the auto-generated
[architecture.md](architecture.md), [controllers.md](controllers.md),
[models.md](models.md) and [routes.md](routes.md).

---

## Request lifecycle

```
HTTP Request
  → Route (routes/api.php)
  → Middleware (auth:sanctum, throttle, role: — planned)
  → Form Request        validation + authorization
  → Controller          delegate only
  → Service             business rules, transactions
  → Repository          Eloquent queries
  → Model               schema, relationships, casts
  → API Resource        response shape
  → ApiResponse trait   envelope
HTTP Response
```

## Tier responsibilities

| Tier | Responsible for | Must never contain |
| --- | --- | --- |
| **Form Request** | Validation rules, `authorize()` | Business logic, DB writes |
| **Controller** | Delegate to a service, shape the response | Business logic, Eloquent, conditionals beyond simple guards |
| **Service** | Business rules, transactions, orchestrating repositories | HTTP concerns, `Request`/`Response` objects, direct `Model::query()` |
| **Repository** | Eloquent queries; returns models/collections | Business rules, mail, HTTP concerns |
| **Model** | Schema, relationships, casts, query scopes | Business workflows, HTTP concerns, presentation |
| **API Resource** | Field selection, formatting, URL resolution | Business logic, DB queries |

### Repository tier

**Built.** `app/Repositories/` exists and `AuthService` goes through `UserRepository`
for every read and write — including Sanctum token issuance and revocation, so no
service performs persistence directly. It is the reference implementation of the
split; follow it when adding a domain.

Concrete repositories extend the abstract `BaseRepository`, which owns the generic
`find` / `findOrFail` / `all` / `paginate` / `create` / `update` / `delete` shape.
A concrete class therefore declares only two things: its model, and the queries that
are genuinely specific to it.

```php
/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    protected function model(): string
    {
        return User::class;
    }

    public function findByEmailAndRole(string $email, string $role): ?User
    {
        return $this->query()->where('role', $role)->where('email', $email)->first();
    }
}
```

This is one deliberate improvement over the reference app, which has no
`BaseRepository` and copy-pastes the CRUD methods across all seventeen classes.
Repositories arrive with the domain that needs them — see the per-phase table in
[roadmap.md](roadmap.md).

## Dependency injection

Constructor injection with **PHP 8 property promotion**, resolved by Laravel's
container. No facades inside services, no `app()` or `resolve()` calls, no `new` for
anything the container can build.

```php
abstract class BaseAuthController extends Controller
{
    public function __construct(
        protected readonly AuthService $authService,
    ) {}
}
```

- Mark promoted dependencies `readonly` — they are collaborators, not state.
- Type-hint concrete classes unless there are genuinely two implementations.
  Do not add an interface per class as a reflex; bind one when a second
  implementation or a test double actually requires it.
- Method injection is acceptable for Form Requests, which Laravel resolves and
  validates before the controller body runs.

## Thin controllers

A controller method should read as three steps: take validated input, call one
service method, return a response.

```php
public function verifyOtp(VerifyOtpRequest $request): JsonResponse
{
    [$user, $token] = $this->authService->verifyOtp(
        $request->validated('email'),
        $request->validated('otp'),
        $this->role(),
    );

    return $this->successResponse([
        'user' => new UserResource($user),
        'token' => $token,
    ], 'Email verified successfully.');
}
```

**Not allowed in a controller:**

| Anti-pattern | Belongs in |
| --- | --- |
| `$request->validate([...])` inline | a Form Request |
| `User::where(...)->first()` | a repository |
| Password hashing, token issuance, OTP generation | a service |
| `DB::transaction()` | a service |
| `Mail::send()` / `$user->notify()` | a service |
| `response()->json([...])` hand-rolled | the `ApiResponse` trait |
| Returning a raw model or ad-hoc array | an API Resource |

Share behaviour across sibling controllers with an abstract base class rather than
copy-paste — `Api/V1/Auth/BaseAuthController` holds all six auth actions, and each
role's controller declares only which role it serves.

## SOLID, as applied here

Concrete consequences, not theory:

| Principle | What it means in this codebase |
| --- | --- |
| **Single responsibility** | One service per domain concern. `AuthService` handles authentication; it does not also manage profiles. A class that needs "and" to describe it should be two classes. |
| **Open/closed** | `BaseAuthController` is extended per role, not modified per role. Adding a fifth role adds a subclass, not a conditional. |
| **Liskov substitution** | Every `BaseAuthController` subclass honours the same six-action contract. A subclass that silently disables an action (as admin does for OTP) must document it — see [features/authentication.md](features/authentication.md). |
| **Interface segregation** | Form Requests are per-action, not per-controller. `LoginRequest` carries only login's rules; nothing depends on validation it does not use. |
| **Dependency inversion** | Controllers depend on services, services on repositories — each on the layer's contract, never on a concrete query. This is the reason the repository tier exists at all. |

## API Resources

Every response containing a model or collection passes through a Resource. Beyond
field selection, the Resource owns **presentation decisions the model deliberately
refuses**:

- **URL resolution.** Models expose raw `image` / `image_url` columns only. Combining
  them into one absolute URL is the Resource's job, because a cross-origin SPA cannot
  use the root-relative paths the reference app returned.
- **No CSS, ever.** `accent`, `variant` and `perk_variant` ship as bare semantic
  tokens. The client maps tokens to styles.
- **Opaque tokens stay opaque.** `nav_menus.route_key` is a client-side routing token,
  not a Laravel route name. Never resolve it with `route()`.

## Response envelope

All responses use one shape via `App\Http\Traits\ApiResponse`. Framework-thrown
errors are reshaped into the same envelope for every `api/*` route by
`bootstrap/app.php`, so controllers never format those by hand.

```json
{ "success": true,  "data": { }, "message": "Optional" }
{ "success": false, "message": "Error", "errors": { "field": ["..."] } }
```

Lists go through `paginatedResponse()`, which lifts the rows to `data` and the
paginator state to a sibling `meta` — so `data` means the same thing on a list
endpoint as on a single-record one. Deletes return `noContentResponse()` (204, empty
body). Services raise expected failures as `App\Exceptions\DomainException`, never
`abort()`.

Status codes still carry meaning — the envelope does not replace them. The full wire
contract, including the pagination shape and every error status, is
[features/api-conventions.md](features/api-conventions.md); see
[postman-testing.md](postman-testing.md) for the codes each endpoint returns.

## Naming

| Artifact | Convention | Example |
| --- | --- | --- |
| Controller | `{Domain}Controller`, under `Api/V{n}/` | `Api/V1/Auth/RiderAuthController` |
| Service | `{Domain}Service` | `AuthService` |
| Repository | `{Model}Repository` | `UserRepository` |
| Form Request | `{Action}Request`, grouped by domain | `Auth/ResetPasswordRequest` |
| Resource | `{Model}Resource` | `UserResource` |
| Boolean method | reads as a question | `isRegisteredForDiscounts()` |

## Before writing new code

1. Search for an existing service, scope, trait, or Resource that already covers the
   behaviour — extend it rather than duplicating.
2. Logic used by two or more controllers or services belongs in a service, trait, or
   query scope.
3. Prefer composing shared rule sets over copying validation arrays between Form
   Requests.
4. Consult [roadmap.md](roadmap.md) before starting a domain — it carries the phase
   order and the Definition of Done every phase must satisfy.
