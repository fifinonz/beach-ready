<?php

use App\Enums\ReminderType;
use App\Mail\ReminderMail;
use App\Models\Reminder;
use App\Models\User;

/*
| London changes its clocks: back an hour at 01:00 UTC on 25 October 2026,
| and forward an hour at 01:00 UTC on 29 March 2026. Fasts and reminders are
| measured in real hours, so a 16-hour fast is 16 hours long even when the
| wall clock says otherwise.
*/

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/London']);
    $this->actingAs($this->user);
});

function reminderOf(User $user, ReminderType $type): Reminder
{
    return Reminder::query()
        ->whereIn('fast_id', $user->fasts()->select('id'))
        ->where('type', $type)
        ->sole();
}

it('reaches the goal after 16 real hours on the night the clocks go back', function () {
    // 20:00 BST on Saturday 24 October.
    $this->travelTo('2026-10-24 19:00:00');

    $this->postJson('/api/fasts', ['fasting_hours' => 16])
        ->assertCreated()
        // 11:00 GMT on Sunday, not 12:00: the extra hour is a real hour.
        ->assertJsonPath('data.target_end_at', '2026-10-25T11:00:00Z');

    $beforeGoal = reminderOf($this->user, ReminderType::BeforeGoal);
    expect($beforeGoal->send_at->toIso8601ZuluString())->toBe('2026-10-25T10:30:00Z')
        ->and(reminderOf($this->user, ReminderType::GoalReached)->send_at->toIso8601ZuluString())
        ->toBe('2026-10-25T11:00:00Z');

    (new ReminderMail($beforeGoal))->assertSeeInText('16-hour goal, at 11:00 am.');
});

it('closes the eating window after 8 real hours on the night the clocks go forward', function () {
    // A 16:8 fast ending at 20:00 GMT on Saturday 28 March.
    $this->travelTo('2026-03-28 04:00:00');
    $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();
    $this->travelTo('2026-03-28 20:00:00');

    $this->postJson('/api/fasts/active/end')->assertOk();

    // The next fast is 8 hours later: 04:00 UTC, which is 05:00 BST.
    $eatingWindow = reminderOf($this->user, ReminderType::EatingWindowClosing);
    expect($eatingWindow->send_at->toIso8601ZuluString())->toBe('2026-03-29T03:00:00Z');

    (new ReminderMail($eatingWindow))->assertSeeInText('Your next fast is planned for 5:00 am');
});

it('reschedules reminders across the clock change when the start time is edited', function () {
    $this->travelTo('2026-10-24 19:00:00');
    $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();
    $this->travelTo('2026-10-25 02:00:00');

    // 23:00 BST on Saturday, typed as wall-clock time.
    $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-24T23:00'])->assertOk();

    expect(reminderOf($this->user, ReminderType::GoalReached)->send_at->toIso8601ZuluString())
        ->toBe('2026-10-25T14:00:00Z');
});

it('reads a repeated wall-clock time as the later of the two', function () {
    // 01:30 happens twice on 25 October: at 00:30 UTC (BST) and 01:30 UTC
    // (GMT). PHP takes the later one.
    $this->travelTo('2026-10-25 09:00:00');

    $this->postJson('/api/fasts', ['fasting_hours' => 16, 'started_at' => '2026-10-25T01:30'])
        ->assertCreated()
        ->assertJsonPath('data.started_at', '2026-10-25T01:30:00Z');
});

it('moves a wall-clock time that never happened forward an hour', function () {
    // 01:30 doesn't exist on 29 March: the clocks jump from 01:00 to 02:00.
    // PHP reads it as 02:30 BST.
    $this->travelTo('2026-03-29 09:00:00');

    $this->postJson('/api/fasts', ['fasting_hours' => 16, 'started_at' => '2026-03-29T01:30'])
        ->assertCreated()
        ->assertJsonPath('data.started_at', '2026-03-29T01:30:00Z');
});
