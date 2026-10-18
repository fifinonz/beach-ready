<?php

use App\Enums\ReminderStatus;
use App\Jobs\SendReminder;
use App\Models\Reminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->travelTo('2026-10-19 10:00:00');
});

it('queues a job for each pending reminder that is due', function () {
    $due = Reminder::factory()->create(['send_at' => '2026-10-19 10:00:00']);
    $overdue = Reminder::factory()->create(['send_at' => '2026-10-19 09:30:00']);

    $this->artisan('reminders:dispatch-due')
        ->expectsOutput('Queued 2 due reminders.')
        ->assertSuccessful();

    Queue::assertPushed(SendReminder::class, 2);
    Queue::assertPushed(SendReminder::class, fn (SendReminder $job) => $job->reminderId === $due->id);
    Queue::assertPushed(SendReminder::class, fn (SendReminder $job) => $job->reminderId === $overdue->id);
});

it('leaves reminders that are not due yet', function () {
    Reminder::factory()->create(['send_at' => '2026-10-19 10:00:01']);

    $this->artisan('reminders:dispatch-due')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('leaves reminders that were sent or cancelled', function (ReminderStatus $status) {
    Reminder::factory()->create(['send_at' => '2026-10-19 09:00:00', 'status' => $status]);

    $this->artisan('reminders:dispatch-due')->assertSuccessful();

    Queue::assertNothingPushed();
})->with([ReminderStatus::Sent, ReminderStatus::Cancelled]);

it('runs every minute, one run at a time, on one server', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'reminders:dispatch-due'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
