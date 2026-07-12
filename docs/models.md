# Models

Eloquent models under `App\Models\`. New casts use the `casts()` method (Laravel 12 convention), not the `$casts` property. Column definitions are the migrations in `database/migrations/`; this doc summarizes the schema and the relationships/scopes that matter.

## Entity relationships

```
Country ─< County ─< City
   ▲         ▲        ▲
   └─────────┴────────┴──  User (country_id, county_id, city_id — all nullable)

User ─< TermCondition            (authoredTerms: created_by, admin authors)
User >──< TermCondition          (acceptedTerms: via term_condition_users pivot)
```

## User

`App\Models\User` — extends `Authenticatable`; uses `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`. One table backs all four roles, distinguished by `role`.

Key columns: `firstName`, `lastName?`, `email` (unique), `mobile?`, `preferred_language?`, `image?`, `role` (enum: admin/restaurant/customer/rider, default customer), `password`, `accept_registration_tnc`, `marketing_consent`, `otp` (6 chars, NOT NULL), `otp_expires_at?`, `status` (active flag), `is_email_verified`, `address1?`, `address2?`, `zip_code?`, `doc_image1?`, `doc_image2?` (restaurant/rider verification docs), `latitude?`, `longitude?`, `country_id?`, `county_id?`, `city_id?`.

- **Hidden**: `password`, `remember_token`, `otp`.
- **Casts**: `password` → hashed, `otp_expires_at` → datetime, `accept_registration_tnc`/`marketing_consent`/`status`/`is_email_verified` → boolean, `latitude` → decimal:8, `longitude` → decimal:8.
- **Relationships**: `country()`, `county()`, `city()` (belongsTo); `acceptedTerms()` (belongsToMany `TermCondition` through `term_condition_users`, with `accepted_at` / `ip_address` pivot + timestamps); `authoredTerms()` (hasMany `TermCondition` on `created_by`).
- The `otp` column is non-nullable, so a spent OTP is overwritten with a random non-numeric string rather than nulled; validity is judged by `otp_expires_at`. See [features/authentication.md](features/authentication.md).

## Country / County / City

A three-level location cascade.

- **`Country`** — `name`. `counties()` hasMany.
- **`County`** — `name`, `country_id`. `country()` belongsTo, `cities()` hasMany.
- **`City`** — `name`, `county_id`. `county()` belongsTo.

User FKs onto these are `nullOnDelete` + `cascadeOnUpdate`, so deleting a location nulls the reference on the user rather than deleting the user.

## TermCondition

`App\Models\TermCondition` — versioned terms-and-conditions documents, one active version per role.

- **Columns**: `role` (enum: customer/restaurant/rider), `title`, `content` (longText), `version` (unsigned int, default 1), `is_active` (default true), `created_by?` (FK users, nullOnDelete). Indexed on `(role, is_active)`.
- **Casts**: `is_active` → boolean, `version` → integer.
- **Relationships**: `author()` belongsTo `User` on `created_by`; `users()` belongsToMany `User` through `term_condition_users` (pivot `accepted_at`, `ip_address` + timestamps).
- **Scope**: `scopeActiveForRole($query, $role)` → active docs for a role, newest version first.

## TermConditionUser

`App\Models\TermConditionUser` — the pivot recording that a user accepted a specific terms document.

- **Columns**: `user_id`, `term_condition_id` (both FK, cascadeOnDelete), `accepted_at?`, `ip_address?`. Unique on `(user_id, term_condition_id)` — a user accepts a given document at most once.
- **Casts**: `accepted_at` → datetime.
- **Relationships**: `user()`, `termCondition()` (belongsTo). Usually reached through the `User`/`TermCondition` belongsToMany relations rather than directly.

## Other tables (no dedicated model)

`personal_access_tokens` (Sanctum), `password_reset_tokens`, `sessions`, `cache`, `jobs`, `notifications` — framework/infra tables created by their migrations; accessed via framework facilities, not app models.
