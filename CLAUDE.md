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
- **Database**: MySQL — **shared with the sibling [MealHub](../MealHub) Laravel app**. Both projects connect to the same `mealhub` database (see `.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=mealhub`). MealHub is the origin of this schema (`Country`, `County`, `City`, `User`, `TermCondition`, `TermConditionUser`, etc.) — see [`../MealHub/database/migrations`](../MealHub/database/migrations) and [`../MealHub/app/Models`](../MealHub/app/Models).
- **Style**: PSR-12, enforced via Pint

## Architecture: Thin Controllers, Fat Services

Controllers must only: validate (via a Form Request), delegate to a service, and shape the response. No business logic, no direct multi-step Eloquent orchestration, no conditionals beyond simple guards in a controller method.

- `app/Http/Controllers/Api/{Version}/` — versioned API controllers (e.g. `app/Http/Controllers/Api/V1/`). Follow existing versioning convention once the first versioned controller exists.
- `app/Services/` — business logic lives here. One service per domain concern (e.g. `MealPlanService`, `OrderService`), injected via constructor property promotion.
- `app/Http/Requests/` — one Form Request per validated action (`StoreMealRequest`, `UpdateMealRequest`, etc.). Controllers must not call `$request->validate()` inline.
- `app/Http/Resources/` — every API response that returns a model or collection goes through an Eloquent API Resource. Controllers must not return raw models or arrays built ad hoc.
- `app/Models/` — Eloquent models. Use the `casts()` method (not the `$casts` property) for new casts, per Laravel 12 convention.

Before adding a new service, action, or resource, search the codebase for an existing one that already covers the behavior (or most of it) and extend/reuse it instead of duplicating logic. This includes shared query scopes, form request rules, and resource transformations.

## Shared Database with MealHub

MealHubApi and MealHub point at the **same MySQL database** (`mealhub`). MealHub owns the canonical schema and already has migrations for `countries`, `counties`, `cities`, `users`, `term_conditions`, and `term_condition_users`.

- **Never write a MealHubApi migration that creates or redefines a table MealHub already owns.** Check [`../MealHub/database/migrations`](../MealHub/database/migrations) before creating any migration here.
- MealHubApi migrations should only add tables/columns that are genuinely API-specific (e.g. Sanctum's `personal_access_tokens`, API-only pivot/config tables) and don't already exist via MealHub.
- Mirror MealHub's existing model definitions (fillable, casts, relationships) for shared tables like `User` rather than redefining them differently — check [`../MealHub/app/Models`](../MealHub/app/Models) first. Divergent model definitions against the same table are a common source of subtle bugs.
- Because the schema is shared, coordinate schema changes with MealHub — a migration run in one app affects the other immediately.

## Consistent JSON API Responses

All API responses must follow one predictable envelope. Until a shared response helper/trait exists, the first one added should live in `app/Http` (e.g. a `ApiResponse` trait or a base `ApiController`) and every subsequent controller/service must reuse it rather than hand-rolling `response()->json()` calls with differing shapes.

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

## Authentication (Sanctum)

- API auth uses Sanctum personal access tokens unless a task explicitly calls for SPA cookie-based auth for the React frontend.
- Protect authenticated routes with the `auth:sanctum` middleware in `routes/api.php`.
- Never log, echo, or return raw plaintext tokens outside of the initial issuance response.

## Testing

- Every API endpoint (each route + each meaningful outcome: happy path, validation failure, authorization failure, not-found) needs a Feature test under `tests/Feature/`. Follow the existing PHPUnit conventions in this file (already using `--phpunit` style tests per the Boost rules above).
- Use model factories for all test data; add factory states instead of manually overriding attributes inline when a pattern repeats.
- Do not consider a feature done until its tests exist and pass.

## Documentation Per Feature

For every feature (new endpoint, service, or significant behavior change), add or update a short Markdown doc under `docs/features/`, named after the feature (e.g. `docs/features/meal-plans.md`). Each doc should cover, briefly:

- What the feature does and why
- Endpoints (method, path, auth requirement)
- Request/response shape (reference the Form Request and Resource used)
- Notable edge cases or business rules

Keep these short and current — update the existing doc rather than leaving a stale one when behavior changes.

## Don't Duplicate Code

- Before writing a new service, action, trait, scope, or resource, grep for existing ones that do the same or similar thing and extend/reuse them.
- Shared logic used by 2+ controllers or services belongs in a service, trait, or query scope — not copy-pasted.
- Prefer extending an existing Form Request/Resource's parent or composing shared rule sets over duplicating validation arrays.

