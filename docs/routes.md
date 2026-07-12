# Routes

All API routes are defined in `routes/api.php` and auto-prefixed with `/api`. Everything below sits under the `/api/v1` version group. Route names are prefixed `api.v1.<role>.` (e.g. `api.v1.admin.login`).

Regenerate the live list any time with:

```bash
php artisan route:list --path=api
```

## Utility

| Method | Path | Name | Middleware |
| --- | --- | --- | --- |
| GET | `/api/user` | — | `auth:sanctum` |

Returns the currently authenticated user (Sanctum default).

## Authentication (role-scoped)

Every role exposes the same six actions via a shared route-registration closure in `routes/api.php`. Customer sits at the `v1` root; admin, restaurant, and rider are namespaced under a path prefix. Sensitive endpoints are rate-limited `throttle:6,1` (6 requests/minute); `logout` requires `auth:sanctum`. See [features/authentication.md](features/authentication.md) and [controllers.md](controllers.md).

| Action | Customer | Admin | Restaurant | Rider | Middleware |
| --- | --- | --- | --- | --- | --- |
| Register | `POST /api/v1/registration` | `POST /api/v1/admin/registration` | `POST /api/v1/restaurant/registration` | `POST /api/v1/rider/registration` | — |
| Verify OTP | `POST /api/v1/verify-otp` | `…/admin/verify-otp` | `…/restaurant/verify-otp` | `…/rider/verify-otp` | `throttle:6,1` |
| Login | `POST /api/v1/login` | `…/admin/login` | `…/restaurant/login` | `…/rider/login` | `throttle:6,1` |
| Forgot password | `POST /api/v1/forgot-password` | `…/admin/forgot-password` | `…/restaurant/forgot-password` | `…/rider/forgot-password` | `throttle:6,1` |
| Reset password | `POST /api/v1/reset-password` | `…/admin/reset-password` | `…/restaurant/reset-password` | `…/rider/reset-password` | `throttle:6,1` |
| Logout | `POST /api/v1/logout` | `…/admin/logout` | `…/restaurant/logout` | `…/rider/logout` | `auth:sanctum` |

Each action maps to the same controller method name across roles: `register`, `verifyOtp`, `login`, `forgotPassword`, `resetPassword`, `logout`.

## Conventions for new routes

- Add them under the `/api/v1` group (or the current version); a new version means a new prefix + controller namespace.
- Protect authenticated routes with `auth:sanctum`; rate-limit sensitive/public endpoints with `throttle:…`.
- Name routes (`->name(...)`) so they can be referenced via `route()`.
- Point routes at a thin controller action; keep no logic in the route file beyond wiring (the shared auth closure is the one allowed helper, kept local to the file).
