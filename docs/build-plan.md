# Beach Ready: build plan

Eight stages. Paste one stage's prompt into Claude Code, review the result, then move on. Each stage ends with working, tested, committed code.

Read `CLAUDE.md` before every stage.

---

## Stage 0: Environment

> Read CLAUDE.md, then set up the local environment for this repo.
>
> 1. The repo already contains README.md, CLAUDE.md, .gitignore and docs/. Keep all of them. Laravel's installer needs an empty folder, so install the latest stable Laravel into a temporary folder and copy it into the repo root, without overwriting our README.md or .gitignore. Merge any entries from Laravel's .gitignore that ours is missing.
> 2. Create a `.lando.yml` using the Laravel recipe with PHP 8.4, PostgreSQL, Node for Vite, and a local mail catcher that Lando supports, so emails can be viewed in a browser during development.
> 3. Configure `.env` and `.env.example` for PostgreSQL and the mail catcher. The database queue driver.
> 4. Install Pest and confirm the default tests pass. Confirm Pint runs.
> 5. Fill in the Commands section of CLAUDE.md with the exact commands that work.
> 6. Update the README's "Running it locally" section with real setup steps.
>
> Commit in small steps. Stop when `lando start` brings up a working Laravel welcome page and the tests pass, then summarise.

---

## Stage 1: Data model and fasting API

> Read CLAUDE.md. Build the core fasting API.
>
> - Sanctum SPA authentication: register, log in, log out, current user. Registration takes first name, email and password (minimum 10 characters) and a time zone.
> - A `fasts` table: user, protocol (fasting hours and eating hours), `started_at`, `target_end_at`, `ended_at` (nullable), `ended_early`, and an optional `feeling` (great, okay, hungry, unwell). All timestamps in UTC.
> - Endpoints: start a fast, get the active fast, edit the start time of the active fast, end the active fast, list history with pagination, and weekly stats (fasts this week, average length, current streak).
> - Enforce the domain rules on the server: one active fast per user, custom fasts capped at 20 hours, a start time can't be in the future.
> - Use form requests for validation and API resources for responses.
> - Pest feature tests for every endpoint, including the rules above and a fast that crosses midnight in the user's time zone.
>
> Write a decision note on storing times in UTC. Stop and summarise.

---

## Stage 2: Reminders

> Read CLAUDE.md. Build the reminder system. This is the most important stage, so explain your design before writing code and wait for my go-ahead.
>
> - Reminder types: 30 minutes before the goal, goal reached, eating window closing (1 hour before the next planned fast).
> - Reminders are rows in a `reminders` table with a type, the fast they belong to, a UTC `send_at` and a status (pending, sent, cancelled).
> - A scheduled command runs every minute, finds due pending reminders and dispatches a queued job for each. Sending must be idempotent: a reminder is never sent twice, even if two workers pick it up.
> - When a fast is started, its reminders are created. When the start time is edited, they are rescheduled. When a fast ends, any pending ones are cancelled.
> - Reminders only send if the user has confirmed their email and has that reminder type switched on (store the per-type settings now; stage 3 adds confirmation).
> - Tests: rescheduling, cancelling, idempotency, and a daylight saving change in a time zone that has one (for example `Europe/London`).
>
> Write a decision note comparing this design with dispatching delayed jobs directly. Stop and summarise.

---

## Stage 2.5: CI and deployment

> Read CLAUDE.md. Set up CI and prepare for deployment to Laravel Cloud, so every stage from here on is tested by CI before it's merged.
>
> 1. Move Lando from PostgreSQL 16 to 17 to match Laravel Cloud, and confirm all tests pass.
> 2. A GitHub Actions workflow that runs Pint and every test suite, including Concurrency, against a PostgreSQL 17 service on every push and pull request.
> 3. Prepare the app for Laravel Cloud: document in `docs/deployment.md` the environment variables, the scheduler, the queue, and the database setup. I'll create the Laravel Cloud project and connect GitHub myself, so tell me exactly what to configure in its dashboard.
> 4. Write a decision note on choosing Laravel Cloud over a VPS.
> 5. Update Stage 6 in the build plan: it now covers the demo account, uptime monitoring and failed-job alerts, since deployment moves here.
>
> Commit in logical steps, then stop.

---

## Stage 3: Double opt-in, preferences and data rights

> Read CLAUDE.md. Build consent and preferences.
>
> - When a user turns on email reminders, send a confirmation email with a signed link that expires after 48 hours. Clicking it confirms the address. Resends are limited to one per 60 seconds.
> - Email topics: fasting reminders, weekly summary, product news. Everything is off by default except what the user turns on.
> - A preference center to change topics, plus one-click unsubscribe from all emails. Every email includes an unsubscribe link and the List-Unsubscribe headers.
> - Use MailerSend's Laravel integration for production and the local mail catcher in development. Ask me before installing the package.
> - Download my data (JSON of the user's profile, fasts, reminders and email settings) and delete my account (removes everything, including pending reminders).
> - Tests for each, including an expired link and a tampered link.
> - The user factory marks emails as verified by default. Decide the right default, since confirmation controls whether reminders send.
> - Add an endpoint to read and change the per-type reminder settings.
>
> Stop and summarise.

---

## Stage 4: Abuse protection

> Read CLAUDE.md. Protect the public endpoints.
>
> - Rate limits on registration, log in, the confirmation resend and the public preference center.
> - A honeypot field on registration that silently rejects bots.
> - Tests for each limit.
>
> Stop and summarise.

---

## Stage 5: Vue front end and PWA

> Read CLAUDE.md. Build the front end to match the design.
>
> Build each screen to match its design in `docs/design/`, opening the image before you start:
>
> | Screen | Design |
> |---|---|
> | Sign up | `01-sign-up.png` |
> | Choose a protocol | `02-protocol.png` |
> | Today: live timer with a progress ring | `03-today.png` |
> | End fast early | `04-end-early.png` |
> | History with a weekly chart | `05-history.png` |
> | Profile | `06-profile.png` |
> | Reminders | `07-reminders.png` |
> | Settings, reached from the gear icon | `09-settings.png` |
> | Today on desktop | `10-desktop-today.png` |
> | History on desktop | `11-desktop-history.png` |
>
> The tab bar is Today, History, Reminders and Profile. Settings is not a tab. The confirmation email (`08-confirmation-email.png`) is the design for the stage 3 email template.
>
> - The timer calculates elapsed time from the start time on every tick, so a sleeping phone or background tab never drifts.
> - Accessible as built: real buttons and links, labelled inputs, switches with `role="switch"`, visible focus states.
> - Mobile-first and responsive. Below 768px, match the phone designs. At 1024px and above, the tab bar becomes a left sidebar with Settings at the bottom; Today and History use the desktop designs (`docs/design/10-desktop-today.png` and `11-desktop-history.png`); other screens sit in a centred column next to the sidebar. Test at 390px, 768px and 1280px.
> - On desktop Today, a fast in progress shows as a dashed bar in the weekly chart and doesn't count as finished in the stats.
> - Installable PWA: manifest, icons, and a service worker that caches the app shell. On iOS, show a one-time "Add to Home Screen" hint. When offline, the timer keeps running and actions that need the network show a clear message. Ask me before installing a package for it.
> - Use the brand colours and Fira Sans from CLAUDE.md.
> - Decide whether to keep or remove the skeleton's Tailwind, concurrently and @laravel/multiplex.
> - On sign-up, render the `favourite_colour` honeypot field hidden from sight, screen readers and the tab order, with `autocomplete="off"` (decision 0007).
>
> Do this in sub-steps, one screen at a time, and stop after each screen for review.

---

## Stage 6: Demo account and monitoring

CI and deployment moved to Stage 2.5. This stage gets the live app ready for visitors.

> Read CLAUDE.md and `docs/deployment.md`. Prepare the live app for reviewers and keep an eye on it.
>
> - A "Try the demo" button that creates a fresh demo account for each visitor, already filled with a few weeks of fasts. Its address uses a reserved domain that can't receive mail and stays unconfirmed, so nothing is sent to it. A scheduled job deletes demo accounts after 24 hours, so no resets are needed and visitors can't interfere with each other. (This replaced a shared demo account that reset on a schedule; see decision 0011.)
> - Uptime monitoring and failed-job alerts are moved to the README's features to develop.
> - Document any new Laravel Cloud settings in `docs/deployment.md`. Ask me before adding a package or an external service.
> - Update the README with the live demo link and tick everything that's done.
>
> Stop and summarise.
