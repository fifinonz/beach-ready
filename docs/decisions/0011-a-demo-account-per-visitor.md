# 11. A fresh demo account for each visitor

## Context

Reviewers should be able to see Beach Ready working without signing up. The plan was one shared demo account, with a few weeks of history, reset on a schedule.

A shared account has awkward edges on a public site. Visitors see each other's changes until the next reset: a fast someone started, a protocol someone changed, a "why" someone rewrote. One visitor could log the account out everywhere, or try to delete it. It also needs the email to be safe, since anyone can press "Forgot password?" for a known address.

## Decision

"Try the demo" on Sign up and Log in creates a fresh demo account for each visitor and signs them in.

- **History is already filled in.** Three weeks of 16:8 fasts in the visitor's own time zone, with varied lengths, a few ended early, some with a feeling, and rest days arranged so the streak shows the forgiving rule (decision 0003). A fast is in progress, so Today shows the timer straight away. When the visitor arrives late enough in the day, today's fast has already finished, so "this week" is never empty.
- **Visitors can't interfere with each other.** Each has their own account, so there's nothing to reset.
- **Demo accounts never get email**, in three layers:
  - The address uses the reserved `demo.invalid` domain (RFC 2606), which can never receive mail.
  - The app never queues email for one: no confirmation, no resend, no password reset.
  - A send-time guard stops any message to that domain, whatever queued it.

  The banner, the Reminders screen and Settings say plainly that it's a demo.
- **Demo accounts are deleted after 24 hours,** with everything that belongs to them, by the hourly `demo:prune` task. It uses the same steps as "Delete my account".
- **Abuse is limited.** A demo can be started 10 times an hour per IP address.

## Trade-off

- **More rows in the database.** Each visitor adds a user and about 20 fasts for up to a day. At portfolio traffic that's nothing, and the rate limit and the 24-hour clean-up bound it.
- **The data is the same pattern for every visitor.** That's deliberate, because it keeps the demo predictable and testable. It's realistic, not random.
- **Email can't be tried in the demo.** The confirmation email design is in `docs/design/08-confirmation-email.png`, and the code and tests cover it.
