# Authentication

Role-scoped registration, email verification, login, and password reset for the four user roles — **customer, admin, restaurant, rider** — using Sanctum personal access tokens. Every role has its own set of endpoints; the controller fixes the role, so credentials for one role are never valid at another role's endpoints (a customer cannot log in at `/admin/login`). Registration and password reset are OTP-based (a 6-digit code emailed to the user), reusing the `otp` / `otp_expires_at` columns on `users`.

## Endpoints

Each role exposes the same eight actions. Customer lives at the `v1` root; admin, restaurant, and rider are namespaced under their own path prefix.

| Action | Customer | Admin | Restaurant | Rider | Auth |
| --- | --- | --- | --- | --- | --- |
| Register | `POST /api/v1/registration` | `POST /api/v1/admin/registration` | `POST /api/v1/restaurant/registration` | `POST /api/v1/rider/registration` | Public |
| Verify OTP | `POST /api/v1/verify-otp` | `…/admin/verify-otp` | `…/restaurant/verify-otp` | `…/rider/verify-otp` | Public (throttled 6/min) |
| Resend OTP | `POST /api/v1/resend-otp` | `…/admin/resend-otp` | `…/restaurant/resend-otp` | `…/rider/resend-otp` | Public (throttled 6/min) |
| Login | `POST /api/v1/login` | `…/admin/login` | `…/restaurant/login` | `…/rider/login` | Public (throttled 6/min) |
| Forgot password | `POST /api/v1/forgot-password` | `…/admin/forgot-password` | `…/restaurant/forgot-password` | `…/rider/forgot-password` | Public (throttled 6/min) |
| Reset password | `POST /api/v1/reset-password` | `…/admin/reset-password` | `…/restaurant/reset-password` | `…/rider/reset-password` | Public (throttled 6/min) |
| Change password | `POST /api/v1/change-password` | `…/admin/change-password` | `…/restaurant/change-password` | `…/rider/change-password` | `auth:sanctum` + `role:*` |
| Logout | `POST /api/v1/logout` | `…/admin/logout` | `…/restaurant/logout` | `…/rider/logout` | `auth:sanctum` + `role:*` |

## The role gate

`auth:sanctum` proves a token is valid; it says nothing about **whose** role it carries. Since all four roles share one `users` table and one token type, a customer's token would otherwise be accepted at `…/admin/logout`. Every authenticated route therefore carries `role:{role}` as well, backed by `App\Http\Middleware\EnsureUserHasRole` (aliased `role` in `bootstrap/app.php`).

```php
Route::middleware(['auth:sanctum', 'role:admin'])->group(...);   // one role
Route::middleware(['auth:sanctum', 'role:admin,restaurant'])->…; // any of several
```

- A valid token of the **wrong** role → `403` with `This action is not available for your account type.`
- **No** token → `401 Unauthenticated.` — deliberately not 403, which would confirm to an anonymous caller that the route exists for some role.
- The gate runs **before** the controller, so a rejected cross-role call has no side effects (a customer hitting `…/admin/logout` does not lose their own token).

The role is read from `users.role` on every request rather than being baked into the token as a Sanctum ability: a token minted before a role change would otherwise keep the stale ability. `tests/Feature/Auth/RoleGateTest.php` pins all of this.

## Request / Response

- Controllers: `App\Http\Controllers\Api\V1\Auth\{Customer,Admin,Restaurant,Rider}AuthController`, all extending `BaseAuthController` (shared endpoints) — customer/restaurant/rider go through the intermediate `SelfServiceAuthController` (shared `RegisterRequest`); admin overrides `register()` with its own request.
- Validated by: `App\Http\Requests\Auth\{RegisterRequest, AdminRegisterRequest, VerifyOtpRequest, ResendOtpRequest, LoginRequest, ForgotPasswordRequest, ResetPasswordRequest, ChangePasswordRequest}`. Login/verify/resend/forgot/reset requests are role-independent; the role comes from the endpoint, never the request body.
- Shaped by: `App\Http\Resources\UserResource`
- Business logic: `App\Services\AuthService` — every method takes a `$role` and scopes all `users` lookups to it. Data access goes through `App\Repositories\UserRepository`, including token issuance and revocation.
- Email: `App\Notifications\OtpNotification` — one role-parameterized class. The subject and the "what you can do once verified" line vary by `$role`; the greeting, code, and expiry do not. Password-reset mail ignores the role.
- All responses use the project-wide envelope via `App\Http\Traits\ApiResponse`. Framework-thrown errors (validation, auth, 404, etc.) are reshaped into the same envelope by `bootstrap/app.php`'s `withExceptions` for any `api/*` route — see [api-conventions.md](api-conventions.md).

`register` returns the created user. `verify-otp` and `login` return `{ user, token }`. `resend-otp`, `forgot-password`, `reset-password`, and `change-password` return no `data`, only a message.

## Business Rules & Edge Cases

- **Role scoping**: `AuthService` filters every lookup by `role`, so the same email address could in principle exist under two roles and each role's endpoints only ever see their own. Logging in with valid credentials at the wrong role's endpoint fails as an invalid-credentials error.
- **Self-service roles (customer, restaurant, rider)**: registration requires `firstName`, `mobile`, `email`, `password` (confirmed), and `accept_registration_tnc` (must be accepted); optional location (`country_id`/`county_id`/`city_id`), address, and zip. The account is created **unverified** with a 6-digit OTP (10-minute expiry) emailed to it, and **login is blocked until verified**.
- **Admin**: registration takes a reduced field set (`firstName`, `mobile`, `email`, `password` — no location or terms) and the account is created **pre-verified with no OTP email**, so admins can log in immediately. Admin accounts can only be created via `/admin/registration`; self-service registration cannot mint an admin.
- **Login** is blocked unless `is_email_verified` (except admins, who are always verified) and `status` (account active) are both true. Bad credentials, unverified email, and inactive account all return a `422` validation-style error on the `email` field, matching Laravel's own auth-failure convention.
- **verify-otp** checks the OTP matches and `otp_expires_at` is in the future, sets `is_email_verified = true`, clears the OTP, and issues a token (auto-login after verification).
- **resend-otp** re-issues the registration OTP for an unverified account. Like forgot-password it never reveals anything: an unknown address, and an address that is *already verified*, both produce the identical success body and send no mail — so the endpoint cannot be used to discover which addresses have accounts, or how far through signup they are. It deliberately has no `exists:users,email` rule, which would defeat that. Each call replaces the previous OTP and resets the 10-minute window.
- **forgot-password** never reveals whether an email is registered under a role: it always returns the same generic success message and only sends an OTP when a matching user exists.
- **reset-password** validates the OTP the same way, updates the password, and revokes all of the user's existing tokens (forces re-login everywhere).
- **change-password** is for a signed-in user, as opposed to reset-password, which is for someone locked out. It re-verifies the current password (`current_password:sanctum`) so a stolen but unexpired token cannot be used to lock the real owner out, and requires the new password to differ from the old. On success it revokes the user's **other** tokens but keeps the one that made the change — other devices are signed out, the current one is not. Contrast reset-password, which revokes everything.
- The `otp` column is `NOT NULL`, so a spent OTP is overwritten with a random 6-character string (never a valid numeric code) and `otp_expires_at` is nulled out — which is what `AuthService::otpIsValid()` actually checks.
- **logout** revokes only the token used for the current request, not all of the user's tokens.
- Emails are lowercased on both creation and lookup, so address casing never causes a duplicate account or a failed login.

## Not implemented

- **Mobile-number OTP login** (`sendMobileOtp` / `verifyMobileOtp` in the reference app) is **deferred**, not ported — see Phase 1b of [../roadmap.md](../roadmap.md). Two blockers: nothing in the reference app ever *delivers* the code (its `services.sms` config block is read by no code, so the endpoint is decorative), and `users.mobile` is nullable and non-unique here, so a passwordless login keyed on it resolves to an arbitrary matching row.
- **Link-based account activation.** The reference app's three `*VerifyAccountNotification` classes emailed an activation *link* to its own Blade routes. This API verifies by code, and a cross-origin SPA has no URL to link to until `FRONTEND_URL` arrives in Phase 7. Their only real per-role difference — one "what you can do once verified" line — was folded into `OtpNotification` instead.
