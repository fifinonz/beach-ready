<?php

namespace App\Reminders;

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Models\Fast;
use App\Models\Reminder;
use Carbon\CarbonImmutable;

/**
 * Keeps a fast's reminders in step with the fast. Call it in the same
 * database transaction that saves the fast, so the two never disagree.
 */
class ReminderScheduler
{
    public function fastStarted(Fast $fast, CarbonImmutable $now): void
    {
        // Starting a fast closes the eating window, so a reminder that it's
        // about to close is no longer needed.
        Reminder::query()
            ->whereIn('fast_id', $fast->user->fasts()->select('id'))
            ->where('type', ReminderType::EatingWindowClosing)
            ->pending()
            ->update(['status' => ReminderStatus::Cancelled]);

        foreach (ReminderType::duringFast() as $type) {
            $this->plan($fast, $type, $now);
        }
    }

    public function startTimeChanged(Fast $fast, CarbonImmutable $now): void
    {
        foreach (ReminderType::duringFast() as $type) {
            $this->plan($fast, $type, $now);
        }
    }

    public function fastEnded(Fast $fast, CarbonImmutable $now): void
    {
        $fast->reminders()
            ->whereIn('type', ReminderType::duringFast())
            ->pending()
            ->update(['status' => ReminderStatus::Cancelled]);

        $this->plan($fast, ReminderType::EatingWindowClosing, $now);
    }

    /**
     * Sets this fast's reminder of the given type to its current due time.
     * A reminder that was already sent is left alone, so it's never sent
     * twice. One whose time has passed is cancelled rather than sent late.
     */
    private function plan(Fast $fast, ReminderType $type, CarbonImmutable $now): void
    {
        // Locking waits for a send in progress to finish, so its "sent" is
        // seen here and not overwritten.
        $reminder = $fast->reminders()->where('type', $type)->lockForUpdate()->first()
            ?? $fast->reminders()->make(['type' => $type]);

        if ($reminder->status === ReminderStatus::Sent) {
            return;
        }

        $reminder->send_at = $type->sendAtFor($fast);
        $reminder->status = $reminder->send_at->lt($now) ? ReminderStatus::Cancelled : ReminderStatus::Pending;
        $reminder->save();
    }
}
