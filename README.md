# Beach Ready

A calm, honest intermittent fasting timer, with reminders you have to ask for.

Beach Ready is a Laravel and Vue 3 web app that runs as an installable PWA. Start a fast, watch the timer, and get a gentle reminder before your goal: but only after you've confirmed your email. It's built as a portfolio project to show how I approach the parts of a product that are easy to get wrong: background jobs, time zones, consent and abuse protection.

> **Status:** in active development. The checklist below shows what's built and what's next.

## Features

- [ ] Accounts with email and password
- [ ] Fasting protocols: 14:10, 16:8, 18:6 and custom (capped at 20 hours)
- [ ] Live timer that stays correct when the phone sleeps (calculated from the start time, not by counting ticks)
- [ ] End a fast early, with no guilt: every fast is saved to history
- [ ] History, weekly chart and streaks
- [ ] Reminders sent as queued jobs: "30 minutes to go", "goal reached", "eating window closing"
- [ ] Reminders that reschedule or cancel when a fast is edited or ended
- [ ] Double opt-in for email: nothing is sent until the address is confirmed
- [ ] Preference center: per-topic email settings and one-click unsubscribe
  (the weekly summary and product news topics are stored, but nothing sends them yet)
- [ ] Download my data and delete my account
- [x] Rate limiting on public endpoints
- [ ] Honeypot on sign-up
- [ ] Installable on a phone as a PWA

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel, PHP 8.4 |
| Frontend | Vue 3 (Composition API), Vite |
| Database | PostgreSQL |
| Queues and scheduling | Laravel queues and the task scheduler |
| Email | MailerSend |
| Local environment | Lando |
| Tests | Pest |
| CI | GitHub Actions |
| Deployment | Laravel Cloud |

## Engineering notes

The interesting problems in a fasting app aren't the timer. They're these:

- **Reminders across time zones.** Times are stored in UTC and shown in the user's time zone. Reminders are scheduled from UTC timestamps, so daylight saving changes and travel don't shift them.
- **Rescheduling queued jobs.** When someone edits their start time or ends a fast early, pending reminders must be cancelled or moved, without sending a stale one.
- **Consent first.** Email reminders stay off until the confirmation link is clicked. Confirmation links are signed and expire after 48 hours.
- **A timer you can trust.** The client never counts seconds. It calculates elapsed time from the stored start timestamp, so a sleeping phone or a background tab can't drift.

Each of these will get a short write-up here as it's built.

## Running it locally

You need [Lando](https://docs.lando.dev/install/) and Docker. Everything else (PHP 8.4, PostgreSQL, Node and Mailpit) runs in containers.

```bash
git clone https://github.com/fifinonz/beach-ready.git
cd beach-ready
cp .env.example .env
lando start
lando composer install
lando artisan key:generate
lando artisan migrate
lando npm run build
```

Open the app at the URL `lando start` prints, normally https://beach-ready.lndo.site. Emails the app sends are caught by Mailpit at https://mail.beach-ready.lndo.site.

If something else on your machine already uses ports 80 and 443, Lando serves on another port (for example http://beach-ready.lndo.site:8080). Set `APP_URL` in `.env` to the exact URL it prints, or Vite's dev server will refuse the browser's requests.

Day-to-day commands:

| Task | Command |
|---|---|
| Run the tests | `lando test` |
| Check code style | `lando pint --test` (drop `--test` to fix) |
| Front-end dev server with hot reload | `lando npm run dev` |
| Queue worker | `lando queue` |
| Scheduler | `lando schedule` |

## Design

The screens were designed before any code was written: sign-up, protocol picker, live timer, end-early flow, history, reminders, the confirmation email, profile and settings.

[DESIGN LINK]

## How this was built

I'm building Beach Ready with [Claude Code](https://docs.claude.com/en/docs/claude-code/overview) as a pair programmer. The architecture, the trade-offs and the review of every change are mine. The commit history shows the work stage by stage.

## Author

**Muthoni Nguthi**, engineer and technical lead, Nairobi.
[Portfolio](https://muthoni-nguthi.subscribepage.io) · [LinkedIn](https://www.linkedin.com/in/mnguthi/)

## Copyright

© 2026 Muthoni Nguthi. All rights reserved.

This code is shared publicly for review. No licence is granted to copy, modify, distribute or use it, in whole or in part, without written permission.

Beach Ready is a timer, not medical advice. If you're pregnant, diabetic or have a history of disordered eating, talk to a doctor before fasting.
