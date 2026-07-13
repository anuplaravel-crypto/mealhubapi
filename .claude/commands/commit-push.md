---
description: Review staged changes, write a Conventional Commits message, commit, and push to GitHub automatically — no confirmation pause before push.
---

Turn staged changes into a well-formed commit and push it to the remote **in one pass, without pausing to ask permission before the push**. Follow the `git-workflow` skill for the commit message format (type/scope/subject/body/footer) — don't invent your own format here.

Note: this command deliberately does not pause before pushing — every `/commit-push` run pushes straight to the remote after committing. Still show the commit (Step 4) so the user can see what went out, just don't wait for a yes/no before Step 5.

## Step 1 — See what's actually changed

Run these to understand the change, not just the file list:

```
git status
git diff --staged
git log -5 --oneline
```

`git diff --staged` is the source of truth for the commit message — read the actual code changes, don't infer intent from filenames alone. `git log -5 --oneline` shows this repo's existing message style — see the `git-workflow` skill's "Matching the existing repo's style" note.

## Step 2 — Handle what's staged (or isn't)

- **Nothing staged, but there are modified/untracked files:** stage them yourself, but do it deliberately — add files by name (or by directory), never a blind `git add -A` or `git add .`. Before staging, scan `git status` for anything that looks like a secret or local-only file (`.env`, `.env.*`, `*.key`, `credentials*`, anything already covered by `.gitignore` but showing up untracked, which usually means it *shouldn't* be added). If you see something like that, leave it out and tell the user why.
- **Some things staged, some not:** commit only what's staged. Don't sweep in the rest — the user may have left it unstaged on purpose (WIP, unrelated change, something they're still testing).
- **Truly nothing to commit:** say so and stop. Don't invent a commit.

## Step 3 — Write the message

Follow the `git-workflow` skill's format exactly (type, scope, subject, body, footer). Do not restate that spec here — read the skill.

## Step 4 — Show the commit

After committing, run `git log -1 --stat` and show the user:
- the final commit message
- the files it touched

Then proceed straight to Step 5 — no need to ask whether to push, and no need to wait for a reply.

## Step 5 — Push

Check whether the current branch has an upstream:

```bash
git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>&1
```

- **Has upstream:** `git push`
- **No upstream** (first push of a new branch, or a freshly `git init`'d repo): `git push -u origin <branch-name>`, where `<branch-name>` comes from `git branch --show-current`.

**If the push is rejected** (non-fast-forward / remote has commits the local branch doesn't): stop and explain the situation to the user — remote has diverged, they need to pull/rebase or merge first. Don't run `git pull`, `git push --force`, or `git push --force-with-lease` on their behalf; picking how to reconcile diverged history is their call, not something to resolve silently.

**Never**: force-push, or skip hooks (`--no-verify`). Pushing to `main`/`master` without a per-push confirmation is intentional here — that's the whole point of this command.

## If a pre-commit hook fails

The commit did not happen — don't retry with `--no-verify`. Fix whatever the hook flagged, re-stage, and re-run the commit as a **new** attempt (not an amend, since the failed attempt never actually created a commit).
