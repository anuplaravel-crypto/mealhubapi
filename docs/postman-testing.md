# Testing the API in Postman

Hand-written guide for exercising every endpoint by hand. **Not** auto-generated —
`php artisan docs:generate` does not touch this file, so it must be updated by hand
when endpoints change.

Covers the **25 routes that exist today** (authentication only). Everything else in
[roadmap.md](roadmap.md) is planned, not built — if an endpoint is not listed here,
it does not exist yet.

A generated Postman collection will replace the manual setup below once the API
surface stabilises; until then this document is the contract.

---

## 1. Prerequisites

```bash
php artisan migrate:fresh --seed     # locations + home CMS + dev users
php artisan serve                    # binds http://127.0.0.1:8000
```

Mail must reach somewhere you can read, because **registration and password reset
both depend on an emailed OTP**. Confirm `.env` has a real mailer:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
```

With `MAIL_MAILER=log` the OTP is written to `storage/logs/laravel.log` instead of
being sent — usable, but you must tail the log rather than open an inbox.

## 2. Postman environment

Create an environment with these variables:

| Variable | Initial value | Set by |
| --- | --- | --- |
| `base_url` | `http://127.0.0.1:8000` | you |
| `token` | *(empty)* | copy from a `verify-otp` or `login` response |
| `email` | `customer@example.com` | you |
| `otp` | *(empty)* | copy from the Mailtrap inbox |

Reference them as `{{base_url}}`, `{{token}}` and so on.

### Optional: capture the token automatically

Paste this into the **Scripts → Post-response** tab of the `login` and `verify-otp`
requests, and you never have to copy the token by hand:

```javascript
const body = pm.response.json();
if (body.data && body.data.token) {
    pm.environment.set("token", body.data.token);
}
```

## 3. Required headers

| Header | Value | When |
| --- | --- | --- |
| `Accept` | `application/json` | always |
| `Content-Type` | `application/json` | every POST (all endpoints are POST) |
| `Authorization` | `Bearer {{token}}` | `logout` only |

`Accept: application/json` is strongly recommended but not strictly required —
`bootstrap/app.php` forces JSON for any `api/*` route regardless. Send it anyway, so
your requests behave the same if that ever changes.

## 4. Response envelope

Every response — including framework errors — uses one shape.

**Success**

```json
{ "success": true, "data": { }, "message": "Optional message" }
```

**Error**

```json
{ "success": false, "message": "Human-readable error", "errors": { "field": ["..."] } }
```

| Status | Means |
| --- | --- |
| 200 | OK |
| 201 | Created (registration only) |
| 401 | Missing or invalid token |
| 422 | Validation failed, bad credentials, or bad/expired OTP |
| 429 | Rate limit hit (see §8) |

Note that **wrong credentials and expired OTPs return 422, not 401** — they are
thrown as validation errors, so the detail is in `errors`, not `message`.

## 5. Endpoints

Four roles, six actions each. Customer lives at the `v1` root; the others sit under
a path prefix.

| Action | Customer | Admin | Restaurant | Rider | Auth |
| --- | --- | --- | --- | --- | --- |
| Register | `POST {{base_url}}/api/v1/registration` | `…/api/v1/admin/registration` | `…/api/v1/restaurant/registration` | `…/api/v1/rider/registration` | public |
| Verify OTP | `…/api/v1/verify-otp` | `…/api/v1/admin/verify-otp` | `…/api/v1/restaurant/verify-otp` | `…/api/v1/rider/verify-otp` | public |
| Login | `…/api/v1/login` | `…/api/v1/admin/login` | `…/api/v1/restaurant/login` | `…/api/v1/rider/login` | public |
| Forgot password | `…/api/v1/forgot-password` | `…/api/v1/admin/forgot-password` | `…/api/v1/restaurant/forgot-password` | `…/api/v1/rider/forgot-password` | public |
| Reset password | `…/api/v1/reset-password` | `…/api/v1/admin/reset-password` | `…/api/v1/restaurant/reset-password` | `…/api/v1/rider/reset-password` | public |
| Logout | `…/api/v1/logout` | `…/api/v1/admin/logout` | `…/api/v1/restaurant/logout` | `…/api/v1/rider/logout` | `Bearer` |

There is also `GET /api/user`, a debug route returning the raw authenticated user.
It bypasses `UserResource` and the envelope, so use it only as a quick "is my token
alive?" check, never as a shape reference.

## 6. Request bodies

### Register — customer / restaurant / rider

```json
{
  "firstName": "Test",
  "lastName": "User",
  "email": "customer@example.com",
  "mobile": "01712345678",
  "password": "password123",
  "password_confirmation": "password123",
  "accept_registration_tnc": true,
  "marketing_consent": false,
  "address1": "12 Example Road",
  "zip_code": "1207",
  "country_id": 1,
  "county_id": 1,
  "city_id": 1
}
```

| Field | Rule |
| --- | --- |
| `firstName` | required, max 20 |
| `lastName` | optional, max 20 |
| `email` | required, valid email, max 50, **unique** |
| `mobile` | required, max 20 |
| `preferred_language` | optional, max 20 |
| `password` | required, **min 8**, must match `password_confirmation` |
| `accept_registration_tnc` | required, must be truthy (`true` / `1` / `"yes"` / `"on"`) |
| `marketing_consent` | optional boolean |
| `address1`, `address2` | optional, max 255 |
| `zip_code` | optional, max 50 |
| `country_id`, `county_id`, `city_id` | optional, must exist — the seeder provides 3 countries, 8 counties, 24 cities |

### Register — admin

Admin takes a **smaller** body. Address, location and consent fields are not
accepted:

```json
{
  "firstName": "Admin",
  "lastName": "User",
  "email": "admin@example.com",
  "mobile": "01712345679",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### Verify OTP

```json
{ "email": "customer@example.com", "otp": "123456" }
```

`email` must belong to an existing user; `otp` must be exactly 6 digits.

### Login

```json
{ "email": "customer@example.com", "password": "password123" }
```

### Forgot password

```json
{ "email": "customer@example.com" }
```

### Reset password

```json
{
  "email": "customer@example.com",
  "otp": "123456",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

### Logout

No body. Requires `Authorization: Bearer {{token}}`.

## 7. Testing sequences

### A. Customer, restaurant, or rider — full flow

1. **Register** → `201`. Returns the user. **No token yet**, and the response never
   contains the OTP.
2. **Read the OTP from Mailtrap** — see §9.
3. **Verify OTP** → `200`, returns `{ user, token }`. Save the token.
4. **Logout** with the token → `200`.
5. **Login** → `200`, returns a fresh `{ user, token }`.

Restaurant and rider are identical; only the path prefix changes.

### B. Admin — different, and easy to get wrong

Admin registration sets `is_email_verified = true` and **sends no email**
(`AuthService::register()`). So:

1. **Register** → `201`
2. **Login** directly → `200` with a token

Do not wait for an OTP; none is sent. The `admin/verify-otp` route is registered but
unusable after registration — admin accounts are created with a random consumed
placeholder in `users.otp` that no email ever reveals. Calling it returns `422`.

### C. Password reset — any role

1. **Forgot password** → `200`
2. Read the new OTP from Mailtrap (subject differs from the registration one)
3. **Reset password** → `200`
4. **Login with the old password** → `422`, proving the change took
5. **Login with the new password** → `200`

⚠️ Reset password **revokes every existing token** for that user. Any token you
captured earlier is dead — expect `401` until you log in again.

### D. Role scoping — the negative test worth running

Register a customer, then call `POST /api/v1/admin/login` with those credentials.

Expected: `422` "These credentials do not match our records." Every lookup in
`AuthService` is scoped by role, so one role's credentials are never valid at
another role's endpoint. Repeat across prefixes to confirm.

### E. Validation failures

Send a register body with a duplicate `email`, a `password` under 8 characters, or
`accept_registration_tnc: false`. Each returns `422` with the offending field listed
under `errors`.

## 8. Rate limits

`verify-otp`, `login`, `forgot-password` and `reset-password` are throttled to
**6 requests per minute per IP** (`throttle:6,1`). Registration and logout are not
throttled.

Iterating on a wrong OTP will hit this quickly and return `429`. Wait 60 seconds —
there is no bypass.

## 9. Retrieving the OTP from Mailtrap

1. Sign in to Mailtrap and open the inbox matching your `.env` credentials
2. Open the newest message — subject differs by purpose (registration vs password
   reset), so confirm you opened the right one
3. Copy the 6-digit code into the Postman `otp` environment variable
4. Send `verify-otp` (or `reset-password`) **within 10 minutes**

**The OTP expires 10 minutes after it is issued** (`OTP_TTL_MINUTES`). An expired
code returns `422` "The provided OTP is invalid or has expired." — the same message
as a wrong code, so an expiry and a typo are indistinguishable from the response.

If you use `MAIL_MAILER=log` instead, the mail body lands in
`storage/logs/laravel.log`; `php artisan pail` tails it live.

## 10. Behaviours that look like bugs but are not

- **`forgot-password` always returns `200`**, even for an email that does not exist
  or belongs to a different role. This is deliberate — the endpoint must not reveal
  whether an address is registered. No email is sent in that case.
- **Login on an unverified account returns `422`**, not `401` — "Please verify your
  email before logging in."
- **Login on a deactivated account returns `422`** — "Your account is inactive."
  `status` defaults to `true` on registration, so you will only see this after an
  admin disables the account.
- **A token from `verify-otp` and one from `login` are equivalent.** Both are named
  `auth-token`; verifying does not grant a lesser token.
- **Logout is not role-checked.** A customer's token can call
  `/api/v1/admin/logout`, and it succeeds — it revokes only the caller's own token.
  This is the missing role middleware described in CLAUDE.md, scheduled for Phase 1
  of [roadmap.md](roadmap.md). Do not read it as evidence that role gating works.
