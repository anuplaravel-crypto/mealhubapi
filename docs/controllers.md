# Controllers

Every controller is **thin**: validate via a Form Request, delegate to a service, shape the response through a Resource + the `ApiResponse` trait. No business logic lives here. See [architecture.md](architecture.md) for the layering rules.

All API controllers live under `App\Http\Controllers\Api\{Version}\`.

## Auth controllers (`Api\V1\Auth\`)

Authentication is role-scoped. A small inheritance chain keeps the six shared endpoints defined once while letting each role fix its own role name and (for admin) its own registration rules.

```
Controller
 └─ BaseAuthController (abstract)         all shared endpoints; role() is abstract
     ├─ SelfServiceAuthController (abstract)   register() using RegisterRequest
     │   ├─ CustomerAuthController            role() => 'customer'
     │   ├─ RestaurantAuthController          role() => 'restaurant'
     │   └─ RiderAuthController               role() => 'rider'
     └─ AdminAuthController                    role() => 'admin'; register() using AdminRegisterRequest
```

| Controller | Role | Notes |
| --- | --- | --- |
| `BaseAuthController` | — (abstract) | Defines `verifyOtp`, `login`, `forgotPassword`, `resetPassword`, `logout`, and a protected `completeRegistration()` helper. Injects `AuthService`. Declares abstract `role()`. |
| `SelfServiceAuthController` | — (abstract) | Adds `register(RegisterRequest)` for the roles that share the self-service registration shape. |
| `CustomerAuthController` | customer | Endpoints at the `/api/v1` root. |
| `RestaurantAuthController` | restaurant | Prefixed `/api/v1/restaurant`. |
| `RiderAuthController` | rider | Prefixed `/api/v1/rider`. |
| `AdminAuthController` | admin | Prefixed `/api/v1/admin`. Overrides `register(AdminRegisterRequest)` (reduced fields, pre-verified account). |

Each concrete controller is only a few lines — it names its role and, for admin, its registration request. All behavior differences between roles are enforced in `AuthService` by the `role` argument, not by copy-pasted controller logic. See [features/authentication.md](features/authentication.md).

### Endpoint → method → collaborators

| Method | Delegates to | Request | Response shape |
| --- | --- | --- | --- |
| `register` | `AuthService::register()` | `RegisterRequest` / `AdminRegisterRequest` | `UserResource` |
| `verifyOtp` | `AuthService::verifyOtp()` | `VerifyOtpRequest` | `{ user: UserResource, token }` |
| `login` | `AuthService::login()` | `LoginRequest` | `{ user: UserResource, token }` |
| `forgotPassword` | `AuthService::forgotPassword()` | `ForgotPasswordRequest` | message only |
| `resetPassword` | `AuthService::resetPassword()` | `ResetPasswordRequest` | message only |
| `logout` | `AuthService::logout()` | (authenticated request) | message only |

## Conventions for new controllers

- Place under `App\Http\Controllers\Api\V1\` (or the current version), grouped in a sub-namespace by domain when a domain has several controllers (e.g. `Auth\`).
- Constructor-inject the domain service via property promotion (`private readonly FooService $fooService`).
- `use ApiResponse` and return via `successResponse(...)` / `errorResponse(...)`; never hand-roll `response()->json()` with a different shape.
- Type-hint a Form Request on every action that takes input — don't validate inline.
- Return models/collections through an API Resource, never raw.
