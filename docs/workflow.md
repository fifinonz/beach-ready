# Workflow

How work moves from an idea to `main` in this repo. Claude Code follows this file; so do I.

## Branches

| Branch | Purpose |
|---|---|
| `main` | Always working, always tested. Nobody commits to it directly. Changes arrive only through merged pull requests. |
| `stage-N-short-name` | One branch per stage in `docs/build-plan.md`. |
| `fix/short-description` | A bug fix outside a stage. |
| `docs/short-description` | Documentation-only changes. |
| `chore/short-description` | Tooling, dependencies, config. |

### Naming convention

Lower case, words joined with hyphens, short and descriptive.

- Stages: `stage-0-environment`, `stage-1-fasting-api`, `stage-2-reminders`, `stage-2-5-ci-deployment`, `stage-3-consent`, `stage-4-abuse-protection`, `stage-5-frontend`, `stage-6-demo-and-monitoring`
- Others: `fix/timer-drift-on-resume`, `docs/reminder-decision-note`, `chore/upgrade-laravel`

## Commits

- **Small and focused:** one logical change per commit.
- **Imperative mood, under 72 characters:** "Add fast model and migration", not "Added fasts" or "WIP".
- **Tests pass** at every commit on a stage branch, as far as possible, and always at the last commit before a pull request.
- **No secrets, ever.** If one is committed by mistake, rotate the key immediately; removing it from history is not enough.

## The stage routine

1. **Start from an up-to-date main.**
   ```bash
   git checkout main
   git pull
   git checkout -b stage-N-short-name
   ```
2. **Run the stage.** In Claude Code: *"Do Stage N from docs/build-plan.md."* Claude Code commits on the stage branch as it works and stops at the end with a summary.
3. **Review locally.** Run the tests, try the feature, read the diff:
   ```bash
   git diff main...HEAD
   ```
   Ask for changes until it's right.
4. **Update the build log.** Add a few lines to `docs/build-log.md`: what was proposed, what I changed and why.
5. **Push the branch.**
   ```bash
   git push -u origin stage-N-short-name
   ```
6. **Open a pull request** into `main` on GitHub. The template fills in the headings: paste Claude Code's stage summary and complete the checklist.
7. **Review on GitHub** using the **Files changed** tab, one last time.
8. **Merge with "Create a merge commit".** Not squash, not rebase: a merge commit keeps the small commits visible, so the history shows how each stage was built.
9. **Clean up.**
   ```bash
   git checkout main
   git pull
   git branch -d stage-N-short-name
   ```
   Delete the remote branch with the button on the merged pull request.

## Review checklist

Before merging any pull request:

- [ ] Tests pass locally and in CI
- [ ] Code style passes (Pint)
- [ ] No secrets, and every new environment variable is in `.env.example`
- [ ] The domain rules in `CLAUDE.md` still hold
- [ ] Any decision someone might question has a note in `docs/decisions/`
- [ ] README checklist updated, ticking only what's finished and tested
- [ ] For UI changes: matches the screen in `docs/design/`

## What Claude Code may and may not do

**May:** create the branch for the current stage, commit on it, run tests and tools, and push the stage branch when asked.

**Must not:** commit to `main`, merge anything, force-push, rewrite history that has been pushed, or delete branches. Merging is a review decision, and reviews are mine.

## Branch protection

CI runs on every push and pull request (Stage 2.5). Protect `main` in **Settings → Rules**: require a pull request before merging, require the **Code style** and **Tests** checks to pass, and block force pushes. Laravel Cloud deploys every push to `main`, so this is what stops untested code going live.
