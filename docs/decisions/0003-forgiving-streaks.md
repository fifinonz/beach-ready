# 3. Forgiving streaks

## Context

The history screen shows a "current streak". The usual definition is strict: consecutive days with a finished fast, reset to zero by any day without one.

That fits badly with how people fast. Many plans build in rest days, and life gets in the way. A strict streak punishes one rest day by wiping out weeks of progress, which is the kind of failure message Beach Ready avoids everywhere else: ending early is normal, and a rest day should be too.

## Decision

- A streak only breaks after **two local days in a row** without a finished fast. A single rest day doesn't reset it.
- The number is the count of **days with a finished fast** in the streak. Rest days keep it alive but don't add to it. Monday, Tuesday, rest Wednesday, Thursday is a 3-day streak.
- Days are the user's own, in their time zone, and a fast counts on the day it ended (decision 0002). Any finished fast counts, including one ended early.
- Today doesn't count as a rest day until it's over, so the streak doesn't drop in the morning before today's fast has ended.

## Trade-off

- "Streak" no longer means strictly consecutive days, which may surprise someone used to other apps. The screen's label can explain it if that turns out to confuse people.
- Fasting every other day keeps a streak going indefinitely. That's intended: it's a steady habit.
- The rule is a single constant (`REST_DAYS_ALLOWED` in `App\Stats\WeeklyStats`), so it's easy to change later if real use suggests otherwise.
