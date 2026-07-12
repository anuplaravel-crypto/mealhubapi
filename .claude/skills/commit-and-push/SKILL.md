---
name: commit-and-push
description: Writes a Conventional Commits-style commit message from the repo's actual staged/unstaged changes, creates the commit, and pushes it to the GitHub remote. Use this whenever the user asks to "commit", "commit this", "commit and push", "save my changes to git", "push this up", or wants a proper conventional commit message written for them instead of writing one by hand. Always pauses for explicit user confirmation before the push step, since pushing is a visible, hard-to-reverse action.
---

# Commit and Push

Turns the current working tree changes into a well-formed Conventional Commits
message, commits them, and pushes to `origin`. The commit and the push are
two separate trust levels: committing is local and easy to undo, pushing is
visible to others and hard to take back. Treat them accordingly.

## Step 1: Look at what's actually changing

Run, in parallel:

```
git status
git diff HEAD
git diff --staged
git log --oneline -20
```

If `git log` fails with "not a git repository", stop and tell the user —
don't silently `git init` for them unless they ask.

If there is nothing staged and nothing modified/untracked, say so and stop.
There's nothing to commit.

If `git log --oneline -20` shows existing history, read it. Match this
repo's established style — the type/scope vocabulary it already uses,
whether scopes are used at all, capitalization, and typical message length.
Conventional Commits is a format, not a fixed vocabulary; a repo that's been
writing `fix(auth): ...` wants you to keep using `auth` as a scope, not
invent a new one that means the same thing. If this is the first commit
(empty log), there's no local style to match yet — just follow the spec
cleanly (see Step 3) and this history becomes the style for next time.

## Step 2: Screen for secrets before staging anything

Before running `git add`, check the list of untracked/modified files against
patterns that usually mean credentials: `.env` (but not `.env.example`),
`*.pem`, `*.key`, `credentials*.json`, `id_rsa*`, or anything with `secret`
or `token` in the filename. Also skim `git diff` output for lines that look
like live API keys or passwords being added (not removed).

If anything matches, do not stage it silently. Tell the user exactly which
file(s) triggered this and ask whether to exclude them or whether it's a
false positive (e.g. `.env.example` with placeholder values is fine). Never
argue past a "yes it's fine" answer, but don't skip asking.

Stage files explicitly by path (`git add <path> <path>...`), not `git add
-A` or `git add .` — this keeps the secret screen meaningful and avoids
sweeping in files the user didn't intend.

## Step 3: Write the message

Format:

```
<type>(<scope>): <short imperative summary, no trailing period>

<optional body — the *why*, not a restatement of the diff, wrap ~72 cols>

<optional footer — BREAKING CHANGE: ..., Refs #123, etc.>
```

**Types** (standard Conventional Commits set — use the one that actually
fits, don't default to `chore` for everything):

| type | when |
| --- | --- |
| `feat` | a new capability a user or API consumer can observe |
| `fix` | a bug fix |
| `docs` | documentation only (`docs/`, README, PHPDoc-only changes) |
| `refactor` | code restructuring with no behavior change |
| `test` | adding or fixing tests only |
| `perf` | a performance improvement |
| `style` | formatting/whitespace only, no logic change (e.g. a Pint run) |
| `build` | dependency or build tooling changes (composer.json, package.json, vite config) |
| `ci` | CI/CD config changes |
| `chore` | everything else that doesn't fit above (config, housekeeping) |
| `revert` | reverting a previous commit |

**Scope**: derive it from where the change actually lives, using this
Laravel app's own structure as the vocabulary — e.g. a change confined to
`app/Services/MealPlanService.php` and its tests is scope `meal-plans`, a
change to `app/Http/Controllers/Api/V1/OrderController.php` is scope
`orders`, a migration touching `personal_access_tokens` is scope `auth`.
Skip the scope entirely if the change is genuinely repo-wide (e.g. a
dependency bump touching `composer.lock` with no single domain).

**Body**: only write one if the *why* isn't obvious from the summary line
and the diff — a subtle constraint, a bug's root cause, a tradeoff. Don't
restate "added a function that does X" when the type+summary already says
that. Skip it for small, self-explanatory changes.

**Breaking changes**: if the diff removes/renames a public API surface
(route, exported class/interface, required config), add a footer:
`BREAKING CHANGE: <what breaks and what callers must do>`.

Show the drafted message to the user as part of your normal response before
creating the commit — don't create it silently in the background.

## Step 4: Commit

```
git commit -m "$(cat <<'EOF'
<type>(<scope>): <summary>

<body if any>

<footer if any>
EOF
)"
```

Rules, no exceptions:
- Never `--amend` — always a new commit. If a pre-commit hook fails, fix the
  issue, re-stage, and commit again as a new commit.
- Never `--no-verify` or any other hook-skipping flag. If a hook blocks the
  commit, that's the hook doing its job — fix the underlying issue.
- Run `git status` after committing to confirm it landed cleanly.

## Step 5: Pause before pushing — this step cannot be automated away

Pushing publishes the commit to `https://github.com/anuplaravel-crypto/mealhubapi`
where others (and CI) can see it. That crosses from "local, reversible" to
"visible, hard to reverse," so stop here and ask the user in chat, plainly:

> Ready to push `<branch>` → `origin/<branch>` (`<n>` commit(s), latest:
> "`<summary line>`"). Push now?

Wait for an explicit yes. A user approving push once earlier in the session
does not carry forward to a later commit — ask each time this step is
reached.

If they say no, stop — the commit already exists locally and can be pushed
later by anyone with access to the repo.

## Step 6: Push

```
git push origin <branch>
```

If the branch has no upstream yet, use `git push -u origin <branch>` (still
requires the same confirmation from Step 5 first).

Never use `--force` or `--force-with-lease`. If the push is rejected because
the remote has commits this branch doesn't, stop and tell the user — don't
pull/rebase/merge and retry on your own, since resolving that is a judgment
call about whose history wins.

## Edge cases

- **No remote configured**: tell the user and stop; don't guess a remote
  URL.
- **Detached HEAD**: tell the user they're not on a branch and ask what
  branch to commit to instead of guessing.
- **Merge conflicts already in progress**: this skill is for ordinary
  commits, not conflict resolution — flag it and stop.
- **Nothing but secrets changed**: after excluding secret-like files in
  Step 2, if nothing safe remains to stage, say so instead of committing an
  empty change.
