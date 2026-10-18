<?php

namespace App\Jobs;

use App\Enums\ReminderStatus;
use App\Mail\ReminderMail;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one reminder, at most once. The row is locked for the whole send, so
 * a second worker holding the same job waits, then finds it already sent.
 * Everything is re-checked after locking, because the fast may have been
 * edited or ended since the job was queued. See decision 0004.
 */
class SendReminder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $reminderId) {}

    /**
     * Stops the scheduler queueing the same reminder again while this job
     * is still waiting for a worker.
     */
    public function uniqueId(): string
    {
        return (string) $this->reminderId;
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $now = CarbonImmutable::now();
            $reminder = Reminder::query()->lockForUpdate()->find($this->reminderId);

            if ($reminder === null || ! $reminder->isDue($now)) {
                return;
            }

            $user = $reminder->fast->user;

            if ($reminder->isTooLate($now) || ! $user->wantsReminder($reminder->type)) {
                $reminder->update(['status' => ReminderStatus::Cancelled]);

                return;
            }

            // If sending throws, the transaction rolls back, the reminder
            // stays pending and the queue retries the job.
            Mail::to($user)->send(new ReminderMail($reminder));

            $reminder->update(['status' => ReminderStatus::Sent, 'sent_at' => $now]);
        });
    }
}
