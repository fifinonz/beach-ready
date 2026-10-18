<?php

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Models\Fast;
use App\Models\Reminder;
use App\Models\User;

beforeEach(function () {
    // 21:00 in Nairobi (UTC+3).
    $this->travelTo('2026-10-18 18:00:00');
    $this->user = User::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->actingAs($this->user);
});

/**
 * The user's reminders as [type => [send_at in UTC, status]].
 *
 * @return array<string, array{0: string, 1: string}>
 */
function remindersOf(User $user): array
{
    return Reminder::query()
        ->whereIn('fast_id', $user->fasts()->select('id'))
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn (Reminder $reminder) => [
            $reminder->type->value => [$reminder->send_at->toIso8601ZuluString(), $reminder->status->value],
        ])
        ->all();
}

describe('starting a fast', function () {
    it('schedules the 30-minute and goal reminders', function () {
        $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();

        expect(remindersOf($this->user))->toBe([
            'before_goal' => ['2026-10-19T09:30:00Z', 'pending'],
            'goal_reached' => ['2026-10-19T10:00:00Z', 'pending'],
        ]);
    });

    it('cancels a reminder whose time has already passed', function () {
        // Started 15h45m ago, so the goal is 15 minutes away.
        $this->postJson('/api/fasts', ['fasting_hours' => 16, 'started_at' => '2026-10-18T05:15'])
            ->assertCreated();

        expect(remindersOf($this->user))->toBe([
            'before_goal' => ['2026-10-18T17:45:00Z', 'cancelled'],
            'goal_reached' => ['2026-10-18T18:15:00Z', 'pending'],
        ]);
    });

    it('cancels the previous fast\'s eating window reminder', function () {
        $previous = Fast::factory()->for($this->user)->between('2026-10-18 01:00', '2026-10-18 17:00')->create();
        $eatingWindow = Reminder::factory()->for($previous)->create([
            'type' => ReminderType::EatingWindowClosing,
            'send_at' => '2026-10-19 00:00:00',
        ]);

        $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();

        expect($eatingWindow->fresh()->status)->toBe(ReminderStatus::Cancelled);
    });

    it('leaves other users\' reminders alone', function () {
        $theirs = Reminder::factory()->create([
            'type' => ReminderType::EatingWindowClosing,
            'send_at' => '2026-10-19 00:00:00',
        ]);

        $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();

        expect($theirs->fresh()->status)->toBe(ReminderStatus::Pending);
    });
});

describe('editing the start time', function () {
    beforeEach(function () {
        $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();
    });

    it('moves pending reminders with the goal', function () {
        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T19:00'])->assertOk();

        expect(remindersOf($this->user))->toBe([
            'before_goal' => ['2026-10-19T07:30:00Z', 'pending'],
            'goal_reached' => ['2026-10-19T08:00:00Z', 'pending'],
        ]);
    });

    it('cancels a reminder moved into the past, and restores it if moved back', function () {
        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T05:15'])->assertOk();

        expect(remindersOf($this->user)['before_goal'])->toBe(['2026-10-18T17:45:00Z', 'cancelled']);

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T20:00'])->assertOk();

        expect(remindersOf($this->user)['before_goal'])->toBe(['2026-10-19T08:30:00Z', 'pending']);
    });

    it('never reschedules a reminder that was already sent', function () {
        $sent = $this->user->activeFast->reminders()->where('type', ReminderType::BeforeGoal)->first();
        $sent->update(['status' => ReminderStatus::Sent, 'sent_at' => now()]);

        $this->patchJson('/api/fasts/active', ['started_at' => '2026-10-18T19:00'])->assertOk();

        expect($sent->fresh())
            ->status->toBe(ReminderStatus::Sent)
            ->send_at->toEqual($sent->send_at);
    });
});

describe('ending a fast', function () {
    beforeEach(function () {
        $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();
        $this->travelTo('2026-10-19 06:00:00'); // Ending early, 12 hours in.
    });

    it('cancels pending reminders and schedules the eating window reminder', function () {
        $this->postJson('/api/fasts/active/end')->assertOk();

        // The next fast is planned 8 hours after this one ended.
        expect(remindersOf($this->user))->toBe([
            'before_goal' => ['2026-10-19T09:30:00Z', 'cancelled'],
            'goal_reached' => ['2026-10-19T10:00:00Z', 'cancelled'],
            'eating_window_closing' => ['2026-10-19T13:00:00Z', 'pending'],
        ]);
    });

    it('keeps reminders that were already sent', function () {
        $this->user->activeFast->reminders()
            ->where('type', ReminderType::BeforeGoal)
            ->update(['status' => ReminderStatus::Sent, 'sent_at' => now()]);

        $this->postJson('/api/fasts/active/end')->assertOk();

        expect(remindersOf($this->user)['before_goal'][1])->toBe('sent');
    });

    it('times the eating window from when the fast actually ended', function () {
        $this->travelTo('2026-10-19 11:30:00'); // 90 minutes past the goal.

        $this->postJson('/api/fasts/active/end')->assertOk();

        expect(remindersOf($this->user)['eating_window_closing'])->toBe(['2026-10-19T18:30:00Z', 'pending']);
    });

    it('cancels the eating window reminder when the next fast starts', function () {
        $this->postJson('/api/fasts/active/end')->assertOk();
        $this->travelTo('2026-10-19 12:00:00');

        $this->postJson('/api/fasts', ['fasting_hours' => 16])->assertCreated();

        expect(remindersOf($this->user)['eating_window_closing'][1])->toBe('cancelled');
    });
});
