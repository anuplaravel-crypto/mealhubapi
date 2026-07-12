# Authentication

Role-scoped registration, email verification, login, and password reset for the four user roles — **customer, admin, restaurant, rider** — using Sanctum personal access tokens. Every role has its own set of endpoints; the controller fixes the role, so credentials for one role are never valid at another role's endpoints (a customer cannot log in at `/admin/login`). Registration and password reset are OTP-based (a 6-digit code emailed to the user), reusing the `otp` / `otp_expires_at` columns on `users`.

## Endpoints

Each role exposes the same six actions. Customer lives at the `v1` root; admin, restaurant, and rider are namespaced under their own path prefix.

| Action | Customer | Admin | Restaurant | Rider | Auth |
| --- | --- | --- | --- | --- | --- |
| Register | `POST /api/v1/registration` | `POST /api/v1/admin/registration` | `POST /api/v1/restaurant/registration` | `POST /api/v1/rider/registration` | Public |
| Verify OTP | `POST /api/v1/verify-otp` | `…/admin/verify-otp` | `…/restaurant/verify-otp` | `…/rider/verify-otp` | Public (throttled 6/min) |
| Login | `POST /api/v1/login` | `…/admin/login` | `…/restaurant/login` | `…/rider/login` | Public (throttled 6/min) |
| Forgot password | `POST /api/v1/forgot-password` | `…/admin/forgot-password` | `…/restaurant/forgot-password` | `…/rider/forgot-password` | Public (throttled 6/min) |
| Reset password | `POST /api/v1/reset-password` | `…/admin/reset-password` | `…/restaurant/reset-password` | `…/rider/reset-password` | Public (throttled 6/min) |
| Logout | `POST /api/v1/logout` | `…/admin/logout` | `…/restaurant/logout` | `…/rider/logout` | `auth:sanctum` |

## Request / Response

- Controllers: `App\Http\Controllers\Api\V1\Auth\{Customer,Admin,Restaurant,Rider}AuthController`, all extending `BaseAuthController` (shared endpoints) — customer/restaurant/rider go through the intermediate `SelfServiceAuthController` (shared `RegisterRequest`); admin overrides `register()` with its own request.
- Validated by: `App\Http\Requests\Auth\{RegisterRequest, AdminRegisterRequest, VerifyOtpRequest, LoginRequest, ForgotPasswordRequest, ResetPasswordRequest}`. Login/verify/forgot/reset requests are role-independent; the role comes from the endpoint, never the request body.
- Shaped by: `App\Http\Resources\UserResource`
- Business logic: `App\Services\AuthService` — every method takes a `$role` and scopes all `users` lookups to it.
- Email: `App\Notifications\OtpNotification` (subject/body vary by `purpose`: `registration` or `password_reset`)
- All responses use the project-wide envelope via `App\Http\Traits\ApiResponse`. Framework-thrown errors (validation, auth, 404, etc.) are reshaped into the same envelope by `bootstrap/app.php`'s `withExceptions` for any `api/*` route.

`register` returns the created user. `verify-otp` and `login` return `{ user, token }`. `forgot-password` and `reset-password` return no `data`, only a message.

## Business Rules & Edge Cases

- **Role scoping**: `AuthService` filters every lookup by `role`, so the same email address could in principle exist under two roles and each role's endpoints only ever see their own. Logging in with valid credentials at the wrong role's endpoint fails as an invalid-credentials error.
- **Self-service roles (customer, restaurant, rider)**: registration requires `firstName`, `mobile`, `email`, `password` (confirmed), and `accept_registration_tnc` (must be accepted); optional location (`country_id`/`county_id`/`city_id`), address, and zip. The account is created **unverified** with a 6-digit OTP (10-minute expiry) emailed to it, and **login is blocked until verified**.
- **Admin**: registration takes a reduced field set (`firstName`, `mobile`, `email`, `password` — no location or terms) and the account is created **pre-verified with no OTP email**, so admins can log in immediately. Admin accounts can only be created via `/admin/registration`; self-service registration cannot mint an admin.
- **Login** is blocked unless `is_email_verified` (except admins, who are always verified) and `status` (account active) are both true. Bad credentials, unverified email, and inactive account all return a `422` validation-style error on the `email` field, matching Laravel's own auth-failure convention.
- **verify-otp** checks the OTP matches and `otp_expires_at` is in the future, sets `is_email_verified = true`, clears the OTP, and issues a token (auto-login after verification).
- **forgot-password** never reveals whether an email is registered under a role: it always returns the same generic success message and only sends an OTP when a matching user exists.
- **reset-password** validates the OTP the same way, updates the password, and revokes all of the user's existing tokens (forces re-login everywhere).
- The `otp` column is `NOT NULL`, so a spent OTP is overwritten with a random 6-character string (never a valid numeric code) and `otp_expires_at` is nulled out — which is what `AuthService::otpIsValid()` actually checks.
- **logout** revokes only the token used for the current request, not all of the user's tokens.
- Emails are lowercased on both creation and lookup, so address casing never causes a duplicate account or a failed login.
