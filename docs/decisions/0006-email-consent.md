# 6. Email consent: double opt-in, topics and signed links

## Context

CLAUDE.md sets the rules: no email until the address is confirmed through a signed link that expires after 48 hours; exactly one confirmation email to an unconfirmed address, plus resends the user asks for, at most one per 60 seconds; and in every email, a working unsubscribe link and a link to the preference center. Stage 3 adds three topics (fasting reminders, weekly summary, product news), all off by default.

## Decision

- **Confirmed means `email_verified_at`.** It's Laravel's own column for "this person controls this address", which is exactly what the link proves. There's no separate account verification to confuse it with.
- **Topics are three booleans on `users`.** They're off by default, including in the user factory, so tests have to opt in the way real users do. A reminder needs all three of: a confirmed address, the fasting reminders topic on, and that reminder type on. This is checked when the reminder is sent.
- **Turning on any topic sends the first confirmation email, once.** That covers both the sign-up checkbox and the settings screens. Turning topics off and on again doesn't send another. Later ones only go out when the user presses "Resend".
- **The 60-second limit lives in the database**, as `confirmation_sent_at`, and is claimed with one conditional `UPDATE`. So two requests at the same moment can't both send, and the rule doesn't depend on a cache. Stage 4's rate limits are a separate, per-IP layer on top.
- **The confirmation link** is a temporary signed route. It's valid for 48 hours and carries a hash of the address, so it stops working if the address changes. An expired or tampered link shows a page explaining what to do, not a bare 403.
- **The preference center and unsubscribe links** are signed but don't expire, and work without logging in. An unsubscribe link in a year-old email must still work, and people read email on devices where they aren't logged in.
- **Unsubscribe asks first; a POST does it.** Mail scanners open links on their own, so opening the link only shows a button. The POST does it, and it's also the one-click endpoint mail clients call from the `List-Unsubscribe-Post` header (RFC 8058). That POST is exempt from CSRF, because mail clients can't send a token; the signature is the proof.
- **Unsubscribing turns every topic off but keeps the address confirmed.** Opting back in later doesn't need a new confirmation email, since the address hasn't changed.
- **Every email extends `BeachReadyMail`.** That base class adds the `List-Unsubscribe` headers and the footer: why you're getting the email, the preference center and unsubscribe links, and the postal address. A test lists every email class, so a new one can't skip the footer.
- **Deleting an account needs the current password.** It can't be undone, and a session left open on a shared device shouldn't be enough. Cascading foreign keys remove fasts and reminders, every session is deleted, and a reminder job already queued finds its reminder gone and does nothing.

## Trade-off

- **Mail scanners can confirm an address.** Some corporate mail scanners open every link in an email, so they could confirm an address its owner never meant to confirm. That's how almost every double opt-in works, and the worst outcome is reminders the person can stop with one click. If it turns out to happen, the link could open a page with a "Confirm" button instead, like unsubscribe does.
- **A forwarded email gives control of its preferences.** Anyone with a forwarded Beach Ready email can change that person's email preferences. They can't see fasting data or log in. This is the usual trade for unsubscribe links that work without logging in.
- **The pages are plain Blade.** The confirmation, preference center and unsubscribe pages are server-rendered, not part of the Vue app, because they have to work before Stage 5 exists and without a login. They use the brand colours and font, and Stage 5 may restyle them.
- **SMTP, not MailerSend's package.** Production sends through MailerSend's SMTP relay with Laravel's built-in mailer, so there's no extra dependency and changing provider is a configuration change. MailerSend-only features such as templates and tags aren't available that way, and Beach Ready doesn't use them.
