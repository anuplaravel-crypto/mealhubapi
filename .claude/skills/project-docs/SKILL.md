---
name: project-docs
description: Ensures every MealHubApi feature has an accurate doc under docs/features/*.md — creates the doc if it doesn't exist yet, updates it if it does. Use proactively right after adding or changing an API endpoint, service, or significant business rule. Also run manually for a full documentation sweep of the project ("document this project", "update the docs", "check the docs are current").
---

# Project Documentation

Keeps `docs/features/*.md` (and its index) in sync with the actual code, per CLAUDE.md's "Documentation Per Feature" rule. This is the per-feature counterpart to the `/update-claude-md` command — that command maintains CLAUDE.md's own project-guide section; this skill never touches CLAUDE.md.

## What this skill maintains

- `docs/features/<feature-name>.md` — one doc per feature
- `docs/README.md` — a short index linking every file under `docs/features/`

## How to run a full sweep

1. Enumerate features by reading `app/Http/Controllers/Api/**`, `php artisan route:list --path=api`, and the `app/Services/**` each controller delegates to. Group routes/controllers/services that belong to the same domain concern into one feature (e.g. all meal-plan endpoints → one `meal-plans` feature, not one doc per route).
2. Derive each feature's canonical doc name in kebab-case (e.g. `meal-plans.md`) from its domain, matching the naming already used for any existing docs.
3. For each feature, check `docs/features/<name>.md`:
   - **Missing** → create it using the template below, populated only from what's actually implemented (routes, the Form Request(s), the Resource(s), the service, and any validation/authorization rules found in the code).
   - **Present** → re-read it, compare its claims (endpoints, request/response shape, business rules) against the current code, and update only what's stale. Leave still-accurate sections untouched — don't rewrite the whole file.
4. Update `docs/README.md` to list every file under `docs/features/`, alphabetically, one line each with a short description. Create it if it doesn't exist.
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

- Never invent endpoints, fields, or rules that aren't in the actual code — read the controller, service, Form Request, and Resource before writing any claim.
- Keep each doc short: describe behavior, don't restate the source line-by-line.
- Don't touch CLAUDE.md or its `<laravel-boost-guidelines>` block — use `/update-claude-md` for CLAUDE.md.
- If a feature's business logic still lives directly in the controller (no dedicated service yet), document what exists today and say so plainly rather than describing the idealized thin-controller version.
- If there are no features to document yet (fresh skeleton, no custom controllers/services beyond defaults), say so instead of fabricating placeholder docs.
