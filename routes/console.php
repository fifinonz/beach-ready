<?php

use Illuminate\Support\Facades\Schedule;

// Reminders are rows with a due time; this queues the ones that are due.
// See decision 0004. onOneServer keeps it to one run per minute when Laravel
// Cloud runs more than one replica; see docs/deployment.md.
Schedule::command('reminders:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Demo accounts last 24 hours (decision 0011). Hourly is often enough.
Schedule::command('demo:prune')
    ->hourly()
    ->onOneServer();
