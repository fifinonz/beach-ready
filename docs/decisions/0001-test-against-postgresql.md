# 1. Test against PostgreSQL

## Context

Laravel's default test setup runs on an in-memory SQLite database. It's fast and needs no setup, but production runs on PostgreSQL, and the two behave differently in exactly the places this app cares about most:

- **Dates and times.** SQLite has no real timestamp type: it stores text and compares strings. PostgreSQL has typed timestamps and real date arithmetic. Reminders, streaks and fasts that cross midnight are all time logic.
- **Constraints.** PostgreSQL enforces column types, check constraints and partial unique indexes (for example "one active fast per user") more strictly than SQLite.
- **Concurrency.** Stage 2 relies on row locking so a reminder is never sent twice. SQLite locks the whole database, so it can't show whether that works.

A test suite that passes on SQLite can still fail in production on any of these.

## Decision

The tests run against PostgreSQL, the same engine and version as development. Lando creates a separate `beach_ready_testing` database on the existing database service, and `phpunit.xml` points at it. Feature tests use `RefreshDatabase`, which migrates once per run and wraps each test in a transaction that is rolled back. A test asserts the suite is on the testing database, so it can never touch development data.

## Trade-off

- **Tests need Lando running.** `lando test` works; running `vendor/bin/pest` on the host doesn't, because the host can't reach the database service. CI (stage 6) will need a PostgreSQL service container.
- **Slower than in-memory SQLite.** Migrating and the transactions cost a little time per run. That's negligible at this size, and we'll revisit it if the suite gets slow.
