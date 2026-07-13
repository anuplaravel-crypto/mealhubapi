---
name: git-workflow
description: Conventional Commits message format and git conventions used across projects — type/scope/subject/body/footer rules. This is the standard; the action of actually committing and pushing lives in the /commit-push command, which follows this skill. Works in any git repo (Laravel or otherwise) — not tied to one project.
---

# Git Workflow Conventions

The standard this project (and others) follows for commit messages. Any command that creates a commit — `/commit-push` today, potentially others later (e.g. a future `/create-feature` that commits scaffolding) — should follow this, not invent its own format.

## Commit message format

```
<type>(<scope>): <subject>

<body — optional, only if the "why" isn't obvious from the subject>
```

**Types** (pick the one that best matches the *primary* effect of the diff — if a change is mixed, pick the type of the change that matters most to a reader scanning history later):

| Type | When |
|---|---|
| `feat` | new user-facing capability |
| `fix` | bug fix |
| `refactor` | code restructuring, no behavior change |
| `docs` | documentation only |
| `style` | formatting/whitespace, no logic change |
| `test` | adding/fixing tests only |
| `perf` | performance improvement |
| `build` | build tooling, dependencies, package manager files |
| `ci` | CI/CD pipeline config |
| `chore` | everything else routine (config tweaks, cleanup) |
| `revert` | reverting a previous commit |

**Scope:** the module/area touched, inferred from the changed paths — not a fixed list, derive it from the actual directory structure of *this* repo. For this Laravel API that typically looks like the meaningful segment under `app/Http/Controllers/Api/V1/...`, `app/Services`, `app/Models`, `routes/`, `database/migrations`, etc. — e.g. a change under `app/Http/Controllers/Api/V1/Auth/` is scope `auth`, a change to `routes/api.php` is scope `routes`. If changes span several unrelated scopes, either omit the scope or pick the dominant one — don't stack multiple scopes into one commit message (that's usually a sign the change should've been split into multiple commits, which is worth mentioning to the user but not something to fix unilaterally).

**Subject:** imperative mood ("add", not "added" or "adds"), no trailing period, under ~72 chars, describes *what changed* concretely enough that someone scanning `git log --oneline` a year from now understands it without opening the diff.

**Body:** only add one when the *why* isn't obvious from the subject and the diff — e.g. a non-obvious bug cause, a deliberate tradeoff, a workaround for something external. Don't restate the diff in prose.

**Footer:** append the same co-author line this environment's commits already use:
```
Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
```

Pass the whole message via heredoc so multi-line formatting survives:

```bash
git commit -m "$(cat <<'EOF'
feat(auth): add rider onboarding endpoint

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

## Matching the existing repo's style

Before writing a message, check `git log -5 --oneline` — some repos use scopes, some don't, some are terser than others. Match the repo's existing convention rather than imposing this format rigidly if the repo has clearly established something different.

## Branch naming

Not yet standardized for this project — no established convention to enforce. If/when one is adopted (e.g. `type/short-description`), document it here so every command that creates branches (commit-related or otherwise) follows the same rule instead of each command inventing its own.
