# Documentation

Reference and feature documentation for MealHubApi.

## Project reference

- [architecture.md](architecture.md) — how the app is structured: layers, request lifecycle, cross-cutting conventions.
- [controllers.md](controllers.md) — the API controllers, their inheritance, and what each delegates to.
- [models.md](models.md) — Eloquent models, schema summary, relationships, and scopes.
- [routes.md](routes.md) — the full API route surface with middleware.

## Features

One file per domain concern under `docs/features/`.

- [features/authentication.md](features/authentication.md) — role-scoped registration, OTP email verification, login, forgot/reset password, logout.
