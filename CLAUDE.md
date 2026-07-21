<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

# MealHubApi Project Guide

## Overview

MealHubApi is a Laravel REST API backend. The frontend is a **React SPA in a separate repository** that consumes this API over HTTP — this repo never renders Blade views or owns frontend assets beyond what Laravel ships by default. All application functionality is exposed through versioned JSON API endpoints.

- **Framework**: Laravel 12 (upgrade path to 13 expected)
- **Auth**: Laravel Sanctum (token-based API authentication, not session/cookie SPA auth unless the React app is later served same-site)
- **Database**: MySQL — **dedicated database**, `mealhubapi` (see `.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=mealhubapi`). This is a separate database from the sibling [MealHub](../MealHub) Laravel app's `mealhub` database — the two apps no longer share a schema. MealHubApi owns and migrates its own full schema (`User`, and any other tables it needs) independently.
- **Style**: PSR-12, enforced via Pint

## Architecture: Controller → Service → Repository

Controllers must only: validate (via a Form Request), delegate to a service, and shape the response. No business logic, no direct multi-step Eloquent orchestration, no conditionals beyond simple guards in a controller method.

| Tier | Does | Must not do |
| --- | --- | --- |
| Controller | validate → delegate → shape response | business logic, Eloquent |
| Service | business rules, transactions, orchestrating repositories | direct `Model::query()` |
| Repository | Eloquent queries only; returns models/collections | business rules, Request/Response objects, mail |

The **authentication module is the established reference implementation** of the controller/service/repository/Form Request/Resource split — follow its structure for new domains (see `app/Http/Controllers/Api/V1/Auth/`, `app/Services/AuthService.php`, `app/Repositories/UserRepository.php`, and [docs/controllers.md](docs/controllers.md)).

- `app/Http/Controllers/Api/{Version}/` — versioned API controllers. **Versioning has started at `V1`**; put new versioned controllers under `app/Http/Controllers/Api/V1/`, grouped in a sub-namespace by domain when a domain has several (e.g. `Api/V1/Auth/`). Share behavior across related controllers via an abstract base controller rather than copy-paste (see `Auth/BaseAuthController`).
- `app/Services/` — business logic lives here, injected via constructor property promotion. `AuthService` is the current domain example; one service per domain concern. (`app/Services/Docs/` is a separate non-domain tooling engine behind `docs:generate` — see "Documentation" — not a template for domain services.)
- `app/Repositories/` — every Eloquent query lives here, one class per model, extending the abstract `BaseRepository` (which owns the generic `find`/`findOrFail`/`all`/`paginate`/`create`/`update`/`delete` shape, so concrete classes declare only their model and their genuinely specific queries). `UserRepository` is the current example. Repositories return models and collections — never arrays, never DTOs. Repositories arrive with the domain that needs them; see the per-phase table in [docs/roadmap.md](docs/roadmap.md).
- `app/Http/Requests/` — one Form Request per validated action, grouped by domain (e.g. `app/Http/Requests/Auth/`). Controllers must not call `$request->validate()` inline. `authorize()` returns `true` for public/pre-auth endpoints.
- `app/Http/Resources/` — every API response that returns a model or collection goes through an Eloquent API Resource (`UserResource` is the current example). Controllers must not return raw models or arrays built ad hoc.
- `app/Http/Traits/` — shared HTTP concerns (currently the `ApiResponse` envelope trait; see "Consistent JSON API Responses" below).
- `app/Exceptions/` — `DomainException` is the base for expected business-rule failures. Services throw it with an explicit status instead of calling `abort()` (an HTTP concern) or throwing `ValidationException` for a failure that is not a validation failure.
- `app/Notifications/` — mail/database notifications (currently `OtpNotification`, used for registration and password-reset OTP emails).
- `app/Models/` — Eloquent models. Use the `casts()` method (not the `$casts` property) for new casts, per Laravel 12 convention. See [docs/models.md](docs/models.md) for the current schema and relationships.

Before adding a new service, action, or resource, search the codebase for an existing one that already covers the behavior (or most of it) and extend/reuse it instead of duplicating logic. This includes shared query scopes, form request rules, and resource transformations.

## Independent Database from MealHub

MealHubApi has its **own dedicated MySQL database** (`mealhubapi`), separate from MealHub's `mealhub` database. MealHubApi owns its full schema and is free to create, modify, and migrate any table it needs (`users`, domain tables, Sanctum's `personal_access_tokens`, etc.) without coordinating with MealHub.

- MealHubApi's own migrations are the source of truth for its schema — there's no shared-table constraint to check against MealHub's migrations anymore.
- Model definitions (fillable, casts, relationships) should reflect MealHubApi's own schema and needs; they no longer need to mirror MealHub's models.
- If the two apps need to exchange data going forward, that should happen over an API or explicit sync mechanism, not a shared database connection.

## Consistent JSON API Responses

All API responses follow one predictable envelope, implemented by the **`App\Http\Traits\ApiResponse` trait** (`successResponse()` / `errorResponse()`). Controllers `use` it rather than hand-rolling `response()->json()` calls with differing shapes. Framework-thrown errors (validation, auth, 404, 500, …) are reshaped into this **same** envelope for every `api/*` route by the exception handler in `bootstrap/app.php` (`withExceptions` → `shouldRenderJsonWhen` + `respond`), so controllers never format those by hand.

Success:
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional human-readable message"
}
```

Error:
```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": { "field": ["validation message"] }
}
```

Use standard HTTP status codes (200/201/204, 401/403/404/422, 500) alongside the envelope — the envelope does not replace status codes.

Three more rules the trait enforces; the full wire contract is [docs/features/api-conventions.md](docs/features/api-conventions.md):

- **Lists go through `paginatedResponse()`**, which lifts the rows to `data` and the paginator state to a sibling `meta`. Never pass a paginator to `successResponse()` — the client would read `data.data[0]`.
- **`errorResponse($message, $status, $errors = null)` takes its status as a required second argument.** It has no default; a forgotten status used to silently report 403s and 404s as 422s.
- **Deletes and other command-style endpoints return `noContentResponse()`** — 204 with an empty body.

## Authentication (Sanctum)

- API auth uses Sanctum personal access tokens unless a task explicitly calls for SPA cookie-based auth for the React frontend.
- Protect authenticated routes with the `auth:sanctum` middleware in `routes/api.php`.
- Never log, echo, or return raw plaintext tokens outside of the initial issuance response.

🔴 **There is no role authorization yet.** `auth:sanctum` proves only *that* a token is valid, not *whose role* it carries — so a customer's token can today call `POST /api/v1/admin/logout` (harmless: it revokes only the caller's own token, but it demonstrates the gap). MealHub enforced roles in `JwtAuthMiddleware`; that mechanism was never ported. **Do not add any admin- or role-scoped endpoint until a `role:` middleware exists** (Phase 1 of [docs/roadmap.md](docs/roadmap.md)) — role-scoping in `AuthService` covers credential lookup only, not request authorization.

**Implemented and role-scoped.** The four roles (`customer`, `admin`, `restaurant`, `rider`) share one `users` table and each have their own registration/verify-otp/login/forgot-password/reset-password/logout endpoints under `/api/v1` (customer at the root; admin/restaurant/rider under a path prefix). `AuthService` scopes every lookup by role, so credentials for one role are never valid at another's endpoints. Registration and password reset are **OTP-based** (6-digit code emailed via `OtpNotification`), not link-based. Full behavior lives in [docs/features/authentication.md](docs/features/authentication.md) — reference it rather than restating the rules here.

## Domain Models Beyond Auth (schema only — no API surface yet)

Beyond `User`, the schema carries several domains that have models, migrations, factories, and seed data wired up but **no controllers, services, Form Requests, Resources, or routes yet** — the only live endpoints are auth (see `php artisan route:list --path=api`). When you build their API, follow the auth module's thin-controller/fat-service pattern.

- **Geo reference data** — `Country`, `County`, `City` (nested hierarchy). `User` `belongsTo` each via `country_id` / `county_id` / `city_id`. Seeded by `LocationSeeder` (3 countries / 8 counties / 24 cities).
- **Terms & conditions** — `TermCondition` (role-scoped, versioned, `is_active`, authored by an admin via `created_by`) and the `TermConditionUser` pivot recording acceptance (`accepted_at`, `ip_address`). `User::acceptedTerms()` / `authoredTerms()` and `TermCondition::scopeActiveForRole()` are the entry points. Registration already captures `accept_registration_tnc` / `marketing_consent` on `User`.
- **Rider onboarding** — `RiderVehicle` (one per rider, `User::vehicles()`). `is_active` is meant to track the rider's `users.status`, which an admin flips on approval.
- **Home-page CMS** — `SiteSetting` (singleton), `HomeStat`, `NavMenu`, `HomeSection` + `SectionFeature`, `MealCategory`, `FeaturedRestaurant`, `Testimonial`. All seeded with the content MealHub's public home page ships, so a client rendering from these produces the same site.
- **Newsletter** — `NewsletterSubscriber` (double opt-in; `status` and `is_mailable` are derived from the two timestamps, never stored).

### Ported from MealHub — decisions that must not be undone

These models came from the sibling [MealHub](../MealHub) Blade app. Three adaptations were deliberate and are easy to "helpfully" revert:

- **`nav_menus.route_key` is not a Laravel route name.** MealHub's column was `route_name` and its model resolved it via `route()`. This API has no named web routes and the SPA owns its routing, so the column holds an opaque token the client maps. Never reintroduce `route()` against it.
- **No model resolves an image URL.** MealHub's models had `logoUrl` / `avatarSrc` / `imageSrc` accessors returning **root-relative** paths, which a cross-origin SPA cannot use. Models here expose raw `image` / `image_url` (and `logo`, `avatar` / `avatar_url`) columns only; resolving the pair into one absolute URL is the API Resource's job. The `IMAGE_COLLECTION` constants are kept as the storage-layout contract for the upload service that has not landed yet.
- **No model returns CSS.** `accent`, `variant` and `perk_variant` ship as bare semantic tokens; MealHub's Bootstrap-class helpers (`accentClass`, `linkClass`, `perkClass`, `accentSoft`, `starIcons`) were dropped. Known debt in the other direction: the `icon_class` columns still hold Bootstrap Icons strings inherited from the seed data — treat them as opaque tokens to map, and expect a reseed to bare names ("shop") eventually.

`tests/Feature/Database/SeederIntegrityTest.php` pins the seeded row counts and the rules above (stat-bar values digits-only, the About badge's newline, no source-wrapping in section bodies). `FactoryIntegrityTest.php` exercises every factory state.

See [docs/models.md](docs/models.md) (auto-generated) for the full field list.

## Testing

- Every API endpoint (each route + each meaningful outcome: happy path, validation failure, authorization failure, not-found) needs a Feature test under `tests/Feature/`. Follow the existing PHPUnit conventions in this file (already using `--phpunit` style tests per the Boost rules above).
- Use model factories for all test data; add factory states instead of manually overriding attributes inline when a pattern repeats.
- Do not consider a feature done until its tests exist and pass — see the Definition of Done below.
- `tests/Feature/Database/` runs on **SQLite**, which ignores varchar limits. Run `php artisan migrate:fresh --seed` against MySQL before trusting a new factory or migration.

## Documentation

The `docs/` tree is the project's living documentation, rendered as a site with **MkDocs + Material** (`mkdocs.yml`, `requirements-docs.txt`; run `mkdocs serve` to preview). New top-level pages must be added to `mkdocs.yml`'s `nav:` by hand — it is explicit, so an unlisted file is invisible on the site. It has three kinds of files, maintained three different ways:

- **Auto-generated reference docs** — `docs/architecture.md`, `docs/controllers.md`, `docs/models.md`, `docs/routes.md`, and `docs/README.md` are produced by **`php artisan docs:generate`** (the `app/Services/Docs/` extractor/renderer engine), which runs automatically on every file edit via the PostToolUse hook. **Never hand-edit these** (they carry a "do not hand-edit" banner and would be overwritten) — if the content is wrong, fix the extractor/renderer in `app/Services/Docs/`. `docs:generate --check` fails if they're stale (for CI).
- **Hand-written per-feature docs** (`docs/features/<feature>.md`) — one per domain concern, maintained by the `/project-docs` skill. For every feature, add/update: what it does and why, endpoints, request/response shape (reference the Form Request and Resource), and business rules/edge cases. These carry the intent the generator can't introspect.
- **[docs/roadmap.md](docs/roadmap.md)** — the MealHub → MealHubApi migration plan: what is already ported, the phase order, per-domain file checklists, and the **Definition of Done** every phase must satisfy. Hand-written, not owned by `/project-docs` or the generator. Consult it before starting a new domain; update it when a phase completes or the plan changes.
- A **`.githooks/pre-commit`** reminder warns (non-blocking) when code under `app/`, `routes/`, or `database/migrations/` is staged without any **`docs/features/`** change. It deliberately ignores the auto-generated reference docs — the PostToolUse hook regenerates those on every edit, so counting them would silence the reminder permanently. Enable per-clone with `git config core.hooksPath .githooks`.

## Definition of Done

No feature or roadmap phase is complete until every box is ticked. This is the checklist for every session — [docs/roadmap.md](docs/roadmap.md) carries the same list plus the per-phase file inventory.

**Architecture**

- [ ] Controllers contain no business logic — validate, delegate, respond
- [ ] Services contain no direct `Model::query()` — all data access goes through a repository
- [ ] Repositories contain no business rules, and no `Request`/`Response`/mail/events
- [ ] Every validated action has its own Form Request (no inline `$request->validate()`)
- [ ] Every model or collection response passes through an API Resource
- [ ] Existing services, repositories, scopes and traits were searched before writing new ones

**Security**

- [ ] Every route carries the correct middleware (`auth:sanctum` plus `role:*` where applicable)
- [ ] Ownership is verified by a Policy wherever an ID arrives from the URL
- [ ] A test asserts 403 for a token of the wrong role

**Tests**

- [ ] Each endpoint covers happy path, validation failure, auth failure, and not-found
- [ ] `php artisan test --compact` is green
- [ ] Test data comes from factories, not inline attribute overrides
- [ ] `php artisan migrate:fresh --seed` run against **MySQL** — SQLite ignores varchar limits

**Quality and documentation**

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `docs/features/<name>.md` created or updated (`/project-docs`)
- [ ] `php artisan docs:generate --check` passes
- [ ] No N+1 — query counts asserted on list and dashboard endpoints
- [ ] One phase, one commit (`/commit-push`)

## Don't Duplicate Code

- Before writing a new service, action, trait, scope, or resource, grep for existing ones that do the same or similar thing and extend/reuse them.
- Shared logic used by 2+ controllers or services belongs in a service, trait, or query scope — not copy-pasted.
- Prefer extending an existing Form Request/Resource's parent or composing shared rule sets over duplicating validation arrays.

