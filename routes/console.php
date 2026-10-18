<?php

use Illuminate\Support\Facades\Schedule;

// Reminders are rows with a due time; this queues the ones that are due.
// See decision 0004.
Schedule::command('reminders:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping();
