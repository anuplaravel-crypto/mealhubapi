# Git hooks

Version-controlled git hooks for this project, so every clone can use the same ones.

## One-time setup (per clone)

Git doesn't run these automatically until you point it at this directory:

```bash
git config core.hooksPath .githooks
```

Run that once after cloning. It's a local setting (not committed), so each developer enables it themselves.

## Hooks

- **`pre-commit`** — prints a reminder when you stage changes under `app/`, `routes/`, or `database/migrations/` but no `docs/` file is staged. It **never blocks** the commit; it just nudges you to keep the docs in sync (or run `/project-docs`). To skip it for a one-off commit, use `git commit --no-verify`.
