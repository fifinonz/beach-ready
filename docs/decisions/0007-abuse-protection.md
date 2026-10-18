# 7. Abuse protection: rate limits and a honeypot

## Context

Four endpoints are open to anyone, or anything, on the internet: sign-up, log-in, the confirmation resend (open to any signed-in account) and the preference center reached from email links. Without limits, a script can create accounts in bulk, guess passwords, flood someone's inbox with confirmation emails, or hammer the preference center.

## Decision

Named rate limiters in `AppServiceProvider`, applied with Laravel's `throttle` middleware. A request over a limit gets a 429 with a `Retry-After` header: JSON for the API, and a short "Please wait a moment" page for the email pages.

| Endpoint | Limit | Keyed by | Against |
|---|---|---|---|
| `POST /api/register` | 10 an hour | IP address | Accounts created in bulk |
| `POST /api/login` | 5 a minute | email and IP address | Guessing one person's password |
| | 20 a minute | IP address | Trying many accounts from one place |
| `POST /api/settings/email/confirmation` | 5 an hour | account | Flooding an inbox. The one-per-60-seconds rule (decision 0006) still applies underneath. |
| `/email/preferences/*` | 30 a minute | IP address | Hammering the public pages, including with made-up signatures |
| Every signed-in `/api/*` request | 120 a minute | account | A script hammering the API with a stolen or scripted session. The app makes a few requests per screen. |
| `DELETE /api/account` | 5 a minute | account | Guessing the password from an open session, since deleting checks it |

- **The numbers are generous for people.** Ten sign-ups an hour covers an office or a household sharing one IP address. Five log-in attempts a minute is more than anyone needs to remember a password. The owner of an account under attack can still log in from their own IP address.
- **Unsubscribe isn't rate limited.** Mail providers send one-click unsubscribes for many people from the same servers, so a per-IP limit could block real unsubscribes, and an unsubscribe that doesn't work breaks the rule that every email has a working one. It's signed, so it can't be pointed at other people, and doing it twice changes nothing.
- **A honeypot on sign-up.** The form has a `favourite_colour` field that the front end hides from sight, screen readers and the tab order. The name avoids the words browsers and password managers look for when autofilling (name, email, phone, address, company, website and so on), so a person's browser never fills it in for them. People leave it empty; bots that fill in every field don't. A sign-up with it filled in gets the same 201 a person would, so the bot learns nothing, but no account is created, nobody is logged in and no email is sent. The check runs before validation, so a bot can't learn anything from validation errors either. Every rejection is logged with its IP address, so a false positive (a real person caught by the honeypot) can be spotted in the logs.

## Trade-off

- **Limits are per IP address, and IP addresses are shared.** A large office or a mobile carrier's network can put many people behind one address. If real people start hitting the sign-up limit, the key can change to IP plus email.
- **The real client IP depends on the proxy.** On Laravel Cloud, Laravel trusts Cloud's proxy automatically, so `$request->ip()` is the visitor's address. Behind any other proxy, trusted proxies must be configured, or everyone shares the proxy's address and one limit.
- **The counters live in the cache.** That's the `database` cache store in production, so they survive deploys and work across replicas. Clearing the cache resets them, which is harmless.
- **A honeypot only stops simple bots.** A bot written for this form, or a person, gets past it. The rate limits are the backstop. Something stronger, such as a CAPTCHA, would add friction for every person and isn't justified at this size.
