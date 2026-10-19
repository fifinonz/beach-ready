# 9. Account emails don't need consent

## Context

Decision 0006 says nothing is emailed until the address is confirmed, and every email has an unsubscribe link and a link to the preference center. Those rules are about emails a person chooses to receive: reminders, the weekly summary and product news.

A password reset email is different. Someone asked for it moments ago, and it's the only way back into an account whose password they've forgotten. If it waited for a confirmed address, anyone who signed up without ticking the reminders box could never reset their password. And an unsubscribe link would make no sense: there's no list to leave.

## Decision

- **Account emails are their own kind.** They extend `App\Mail\AccountMail`, not `BeachReadyMail`. The guard test in `EveryEmailTest` allows exactly these two bases, so any new email has to choose one on purpose.
- **They're sent whether or not the address is confirmed,** and whatever the person's topic choices, including after "Unsubscribe from all emails". They don't pass through `User::wantsReminder()` or any topic check.
- **They have no unsubscribe link and no `List-Unsubscribe` headers.** The footer says why the email was sent ("someone asked to reset the password for your Beach Ready account"), and shows the postal address.
- **They only ever answer something the person just did.** The only account email today is the password reset. Anything sent unprompted, such as a summary, news or "we miss you", is a `BeachReadyMail` and needs consent.
- **They can't be used to flood an inbox.** A reset can be requested five times an hour per IP address and five times an hour per email address. The response is the same whether or not the account exists, so the form can't be used to discover who has an account.
- **They follow the same safety rules as other queued emails.** They store only the user's ID and are dropped if the account is deleted first.

## Trade-off

- An attacker who knows someone's address can send them up to five reset emails an hour. That's an annoyance, not a risk: the link only works for the inbox's owner, expires after 60 minutes, works once, and changes nothing if ignored.
- An unconfirmed address could belong to someone else, if a person signed up with an address that isn't theirs. The reset email then goes to that address's owner, who can ignore it. That's the same as every other site's password reset, and safer than refusing resets for unconfirmed accounts.
