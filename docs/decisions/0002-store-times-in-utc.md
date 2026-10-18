# 2. Store times in UTC

## Context

Every interesting thing in Beach Ready is a time: when a fast started, when it reaches its goal, when it ended, and soon when a reminder goes out. Users live in different time zones, some of which change their clocks for daylight saving, and some users travel. If times are stored as local wall-clock values, "16 hours after 20:30" is ambiguous across a clock change, and two users' times can't be compared or scheduled against each other.

## Decision

- **Every timestamp is a UTC instant.** The fast columns are PostgreSQL `timestamptz`, the app's time zone is `UTC`, and the database connection's session time zone is pinned to `UTC` in `config/database.php`, so Laravel's writes and reads can't be shifted by a server setting.
- **Each user has an IANA time zone** (for example `Africa/Nairobi`), validated at registration. Abbreviations like `EAT` and fixed offsets like `+03:00` are refused, because they don't carry daylight saving rules.
- **Convert only at the edges.** A start time the user types is sent as wall-clock time without an offset and read in their time zone (`User::localTimeToUtc`); a time sent with an offset is taken as it is. Responses return UTC in ISO 8601 (`2026-10-18T17:30:00Z`), and the client formats them for display.
- **Calendar questions use the user's calendar.** "This week" and streak days are computed by converting UTC instants into the user's time zone first. A fast belongs to the day it ended on.

## Trade-off

- Times are harder to eyeball in the database: a fast that started at 20:30 in Nairobi shows as 17:30. That's the cost of having one unambiguous value per moment.
- If a user changes their time zone, past fasts keep their real instants, so a fast may move to a different local day in their history. That's the honest result, and better than rewriting history.
- Grouping by local day happens in PHP, not SQL. That's fine at the scale of one person's history; if it grows, PostgreSQL can do the same with `AT TIME ZONE`.
