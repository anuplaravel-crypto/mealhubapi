---
name: project-docs
description: Ensures MealHubApi's docs stay in sync with the code — per-feature docs under docs/features/*.md plus the four project-reference docs (docs/architecture.md, docs/controllers.md, docs/models.md, docs/routes.md) and the docs/README.md index. Creates whatever is missing, updates whatever is stale. Use proactively right after adding or changing an API endpoint, service, model, route, or significant business rule. Also run manually for a full documentation sweep ("document this project", "update the docs", "check the docs are current").
---

# Project Documentation

Keeps the `docs/` tree in sync with the actual code, per CLAUDE.md's "Documentation Per Feature" rule. This is the counterpart to the `/update-claude-md` command — that command maintains CLAUDE.md's own project-guide section; this skill never touches CLAUDE.md.

## What this skill maintains

- `docs/features/<feature-name>.md` — one doc per feature (endpoints, request/response, business rules)
- `docs/architecture.md` — layers, request lifecycle, cross-cutting conventions
- `docs/controllers.md` — the API controllers, their inheritance, what each delegates to
- `docs/models.md` — Eloquent models: schema summary, relationships, scopes
- `docs/routes.md` — the full API route surface with middleware
- `docs/README.md` — the index linking every reference doc and every file under `docs/features/`

The four reference docs describe the project as a whole; the feature docs describe one domain each. Keep them consistent — a new endpoint usually touches its feature doc, `routes.md`, and often `controllers.md`.

## How to run a full sweep

### Part A — feature docs (`docs/features/*.md`)

1. Enumerate features by reading `app/Http/Controllers/Api/**`, `php artisan route:list --path=api`, and the `app/Services/**` each controller delegates to. Group routes/controllers/services that belong to the same domain concern into one feature (e.g. all meal-plan endpoints → one `meal-plans` feature, not one doc per route).
2. Derive each feature's canonical doc name in kebab-case (e.g. `meal-plans.md`) from its domain, matching the naming already used for any existing docs.
3. For each feature, check `docs/features/<name>.md`:
   - **Missing** → create it using the template below, populated only from what's actually implemented (routes, the Form Request(s), the Resource(s), the service, and any validation/authorization rules found in the code).
   - **Present** → re-read it, compare its claims (endpoints, request/response shape, business rules) against the current code, and update only what's stale. Leave still-accurate sections untouched — don't rewrite the whole file.

### Part B — reference docs (`docs/architecture.md`, `controllers.md`, `models.md`, `routes.md`)

For each of the four reference docs, re-read it and reconcile it with the current code, updating only what's stale (same surgical approach as feature docs — don't rewrite wholesale):

- **`architecture.md`** — layers, request lifecycle, directory map, cross-cutting conventions (envelope, versioning, role scoping). Update when a new layer/trait/convention is introduced or the directory structure changes. This is the most stable doc; most sweeps leave it untouched.
- **`controllers.md`** — verify the controller inventory and inheritance against `app/Http/Controllers/Api/**`. Update the tables when controllers are added/removed/renamed or their delegation changes.
- **`models.md`** — verify model list, schema summary, relationships, and scopes against `app/Models/**` and `database/migrations/**`. Update when a model or its fillable/casts/relationships/columns change. Describe the schema; don't restate every column type from the migration.
- **`routes.md`** — regenerate the picture from `php artisan route:list --path=api`; update the tables when routes, prefixes, names, or middleware change.

If a reference doc doesn't exist yet, create it from the current code. If the project is still a bare skeleton (no custom controllers/models/routes), say so rather than fabricating content.

### Part C — index and report

4. Update `docs/README.md` so it links all four reference docs and every file under `docs/features/` (features listed alphabetically, one line each with a short description). Create it if it doesn't exist.
5. Report back concisely: which docs were created, which were updated (and why), which needed no change.

## Feature doc template

```markdown
# <Feature Name>

<One or two sentences: what it does and why it exists.>

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| ... | ... | ... |

## Request / Response

- Validated by: `App\Http\Requests\...`
- Shaped by: `App\Http\Resources\...`
- <brief notes on shape/fields that matter — not a field-by-field restatement of the Form Request/Resource source>

## Business Rules & Edge Cases

- ...
```

## Guardrails

- Never invent endpoints, fields, rules, controllers, models, or routes that aren't in the actual code — read the source (controller, service, Form Request, Resource, model, migration, route file) before writing any claim.
- Keep each doc short: describe behavior and structure, don't restate the source line-by-line (e.g. summarize the schema, don't transcribe every column type from the migration).
- The reference docs (architecture/controllers/models/routes) and feature docs must agree — when a change touches both, update both in the same sweep rather than leaving them contradicting each other.
- Don't touch CLAUDE.md or its `<laravel-boost-guidelines>` block — use `/update-claude-md` for CLAUDE.md.
- If a feature's business logic still lives directly in the controller (no dedicated service yet), document what exists today and say so plainly rather than describing the idealized thin-controller version.
- If there are no features to document yet (fresh skeleton, no custom controllers/services beyond defaults), say so instead of fabricating placeholder docs.
