<?php

namespace App\Console\Commands;

use App\Jobs\SendReminder;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:dispatch-due')]
#[Description('Queue a job for every pending reminder that is due')]
class DispatchDueReminders extends Command
{
    /**
     * Only queues the work. Whether each reminder is still due, wanted and
     * not too late is decided by the job, after it has locked the row.
     */
    public function handle(): int
    {
        $count = 0;

        Reminder::query()
            ->due(CarbonImmutable::now())
            ->select('id')
            ->chunkById(500, function ($reminders) use (&$count) {
                foreach ($reminders as $reminder) {
                    SendReminder::dispatch($reminder->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} due reminders.");

        return self::SUCCESS;
    }
}
