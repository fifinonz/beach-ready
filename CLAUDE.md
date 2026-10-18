# Beach Ready: project guide for Claude Code

## What this is

Beach Ready is an intermittent fasting timer: a Laravel API with a Vue 3 front end, installable as a PWA. It is a public portfolio project for a Technical Lead application, so the code, the tests and the commit history are all part of what reviewers will read. Clarity beats cleverness.

The owner, Muthoni, is the reviewer of every change. You are the pair programmer.

## Working agreement

- **Work in the stages in `docs/build-plan.md`.** Do one stage at a time. At the end of a stage, stop, summarise what you did and what you decided, and wait for review before starting the next.
- **Small commits** with clear messages in the imperative mood ("Add fast model and migration"). One logical change per commit. Never commit directly with failing tests.
- **Ask before adding any package** that isn't named in this file. Say what it's for and what the alternative is.
- **Write tests as you go** with Pest. Every behaviour in the stage gets a test, especially time and scheduling logic.
- **Record real decisions** in `docs/decisions/` as short numbered notes (`0001-store-times-in-utc.md`): the context, the decision, the trade-off. Only for choices someone might reasonably question.
- **Once a migration is merged to main, never edit it.** Add a new migration instead.
- **Never commit secrets.** Real values live in `.env`, which is git-ignored. Every new environment variable is added to `.env.example` with a placeholder and a one-line comment.
- **Keep the README honest.** When a feature is finished and tested, tick it in the README checklist. Never tick anything early.

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel (latest stable), PHP 8.4 |
| Auth | Laravel Sanctum, cookie-based SPA authentication |
| Frontend | Vue 3 with the Composition API, Vue Router, Vite |
| Database | PostgreSQL |
| Queues | Laravel queues, database driver |
| Scheduling | Laravel task scheduler |
| Email | MailerSend in production, a local mail catcher in development |
| Local environment | Lando, Laravel recipe |
| Tests | Pest |
| Code style | Laravel Pint |
| CI | GitHub Actions: Pint and every test suite on PostgreSQL 17 |
| Deployment | Laravel Cloud with Serverless Postgres 17 (see `docs/deployment.md`) |

## Domain rules

These are product decisions. Don't change them without asking.

- **All times are stored in UTC.** Each user has an IANA time zone (for example `Africa/Nairobi`). Convert only at the edges: when displaying, and when interpreting times the user types.
- **Protocols:** 14:10, 16:8, 18:6 and custom. Custom fasts are capped at 20 hours. The cap is enforced on the server, not only in the UI.
- **One active fast per user** at a time.
- **Ending early is normal.** An ended-early fast is saved to history like any other. No language anywhere implies failure.
- **The timer never counts.** Elapsed time is always calculated from the stored start time, on both server and client.
- **Consent before email.** Email reminders stay off until the address is confirmed through a signed link that expires after 48 hours. Unconfirmed addresses get exactly one confirmation email, plus resends the user explicitly requests, limited to one per 60 seconds.
- **Every email** includes a working unsubscribe link and a link to the preference center.
- **Users can download their data as JSON and delete their account**, which removes everything.

## Brand

Match the design: Fira Sans throughout. Colours: foam `#F7FBFA` (background), deep `#0C3B4A` (text), lagoon `#12808A` (primary actions), shallows `#DDF1EE` (tints), sun `#F5B82E` (highlights only), line `#CFE3E0` (borders). Touch targets at least 44px. Text contrast at least 4.5:1.

Screen designs are in `docs/design/`, one PNG per screen, numbered in flow order. When building a screen, open its image first and match the layout, spacing, copy and colours. If something in a design conflicts with the domain rules above, the domain rules win: ask.

## Commands

Fill this in during stage 0, once the commands are confirmed to work, so later sessions can rely on it:

- Start the environment: `lando start` (URLs: `lando info`)
- Run tests: `lando test` (PHP, Pest) and `lando npm test` (front end, Vitest)
- Run code style: `lando pint` (check only: `lando pint --test`)
- Run the queue worker: `lando queue`
- Run the scheduler: `lando schedule`
- Front-end dev server: `lando npm run dev`
- Production front-end build: `lando npm run build`
- Artisan and Composer: `lando artisan ...`, `lando composer ...`

Tests run against the `beach_ready_testing` PostgreSQL database (set in `phpunit.xml`), so they need Lando running. See `docs/decisions/0001-test-against-postgresql.md`.
