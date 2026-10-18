# 4. Reminders as rows, sent by a scheduled command

## Context

Each fast has reminders: 30 minutes before the goal and when the goal is reached, and once the fast ends, an hour before the eating window closes. They have to move when the start time is edited, stop when the fast ends, never go out twice, and never go out stale.

Laravel offers two ways to send something later:

1. **Delayed jobs.** When the fast starts, dispatch `SendReminder` with `->delay($sendAt)`. The queue holds it until it's due.
2. **Rows plus a scheduler.** Store each reminder as a row with a `send_at` and a `status`. A command runs every minute, finds due pending rows and queues a job for each.

## Decision

Rows plus a scheduler.

- `reminders` holds one row per type per fast (`unique(fast_id, type)`), with a UTC `send_at` and a status of `pending`, `sent` or `cancelled`.
- `ReminderScheduler` creates, moves and cancels those rows in the same transaction that saves the fast. Editing a start time updates `send_at` in place. A reminder already sent is never touched again, and one whose time has passed is cancelled rather than sent late.
- `reminders:dispatch-due` runs every minute (`withoutOverlapping`) and queues `SendReminder` with only the reminder's ID. The job is `ShouldBeUnique`, so a reminder waiting in the queue isn't queued again the next minute.
- `SendReminder` locks the row (`SELECT … FOR UPDATE`), then re-checks everything: the reminder is still pending and due, it's no more than 10 minutes late, and the user still wants it (confirmed email and that type switched on). It sends, marks the reminder sent and commits. A second worker with the same job waits on the lock, then sees `sent` and does nothing.

## Why send while holding the lock

The other common way to stop two workers sending the same reminder is **claim, then send**: one short transaction moves the row from `pending` to a `sending` status (only one worker can win that update), the email goes out with no transaction open, and a second update marks it `sent`. Nothing is locked during the slow SMTP call.

That approach needs more than one extra status. A worker can die between the claim and the send, leaving a row stuck in `sending` forever. So it also needs a claim timestamp, a sweeper that returns stale claims to `pending`, and a timeout long enough not to steal a send that's merely slow. That's three more moving parts, each with its own edge cases to test.

Holding the lock gets the same guarantee from PostgreSQL directly. If a worker dies mid-send, its connection closes, the transaction rolls back, the lock is released and the reminder is simply pending again. Nothing gets stuck, so there's nothing to sweep. The cost is one row lock and one connection held for the length of an SMTP call. For an app sending a few reminders a minute that's negligible, so the simpler design wins. If sending ever became slow or high-volume, claim-then-send is the upgrade path.

## When sending keeps failing

1. **Retries.** `SendReminder` makes 3 attempts, 60 seconds apart. Each failure rolls back, so the reminder stays `pending`. While the job is waiting to retry, its `ShouldBeUnique` lock stops the dispatcher queueing a duplicate.
2. **Giving up.** After the third failure, the job goes to `failed_jobs` and releases its unique lock. The reminder is still `pending`.
3. **Re-queued.** The next minute, the dispatcher finds the reminder still due and queues a fresh job, which starts its own 3 attempts.
4. **Cancelled.** Once the reminder is more than 10 minutes late, the next attempt cancels it without trying to send. A late reminder ("30 minutes to go", 40 minutes later) is worse than none.

So a mail outage costs at most 10 minutes of retries per reminder, then the reminder is cancelled cleanly. It's never sent late and never left pending forever. The failed attempts remain in `failed_jobs` as a record of the outage; Stage 6 adds alerts for them. A test walks a reminder through 10 minutes of failures to cancellation.

## Why not delayed jobs

- **They can't be moved.** A queued job can't be rescheduled or cancelled. Editing a start time would mean dispatching new jobs and teaching the old ones to recognise they're out of date, which is the same "re-check against the database" logic, plus a version number to compare.
- **They're invisible.** "Which reminders are pending for this fast?" is a query on a table, not a search through serialised queue payloads. The same goes for support, data export (stage 3) and account deletion, which must remove pending reminders.
- **They depend on the queue keeping them.** Clearing or losing the queue loses every future reminder. Rows survive, and the scheduler picks up whatever is due.
- **Long delays are fragile.** Some queue drivers cap how far ahead a job can be delayed. The database driver doesn't, but a design that doesn't care is easier to move.

## Trade-off

- **Up to a minute late.** The scheduler runs every minute, so a reminder can arrive up to about a minute after its time. That's fine for a fasting reminder.
- **Duplicate window.** The email is sent inside the transaction holding the lock. If the send succeeds but the commit then fails (the database goes away between the two), the reminder stays pending and is sent again on retry. That window is a few milliseconds. The alternative, marking it sent before sending, would lose the reminder whenever the mail server has a hiccup, which is far more common.
- **A transaction held open during a send.** Each send holds one row lock and one connection for the length of an SMTP call. At this scale that's nothing. See "Why send while holding the lock" for the alternative.
- **Wall-clock times that don't exist or happen twice.** On clock-change nights a typed time can be ambiguous. PHP reads a repeated time (01:30 when the clocks go back) as the later occurrence, and a skipped one (01:30 when they go forward) as an hour later. Both are tested and are at most an hour off what the user might have meant.
