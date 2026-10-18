<?php

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Jobs\SendReminder;
use App\Mail\ReminderMail;
use App\Models\Fast;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2026-10-19 10:00:00');
    $this->user = User::factory()->create(['timezone' => 'Africa/Nairobi']);
    $this->fast = Fast::factory()->for($this->user)->create([
        'started_at' => '2026-10-18 18:00:00',
        'target_end_at' => '2026-10-19 10:00:00',
    ]);
});

function dueReminder(Fast $fast, array $attributes = []): Reminder
{
    return Reminder::factory()->for($fast)->create(array_merge([
        'type' => ReminderType::GoalReached,
        'send_at' => '2026-10-19 10:00:00',
    ], $attributes));
}

it('sends a due reminder and marks it sent', function () {
    $reminder = dueReminder($this->fast);

    SendReminder::dispatchSync($reminder->id);

    Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo($this->user->email)
        && $mail->reminder->is($reminder));
    expect($reminder->fresh())
        ->status->toBe(ReminderStatus::Sent)
        ->sent_at->toIso8601ZuluString()->toBe('2026-10-19T10:00:00Z');
});

it('sends a reminder only once, however many times the job runs', function () {
    $reminder = dueReminder($this->fast);

    SendReminder::dispatchSync($reminder->id);
    SendReminder::dispatchSync($reminder->id);
    SendReminder::dispatchSync($reminder->id);

    Mail::assertSentCount(1);
});

it('does nothing for a reminder that is not due yet', function () {
    // Rescheduled to later after the job was queued.
    $reminder = dueReminder($this->fast, ['send_at' => '2026-10-19 10:01:00']);

    SendReminder::dispatchSync($reminder->id);

    Mail::assertNothingSent();
    expect($reminder->fresh()->status)->toBe(ReminderStatus::Pending);
});

it('does nothing for a reminder that was cancelled or sent', function (ReminderStatus $status) {
    $reminder = dueReminder($this->fast, ['status' => $status]);

    SendReminder::dispatchSync($reminder->id);

    Mail::assertNothingSent();
    expect($reminder->fresh()->status)->toBe($status);
})->with([ReminderStatus::Cancelled, ReminderStatus::Sent]);

it('does nothing for a reminder that no longer exists', function () {
    $reminder = dueReminder($this->fast);
    $reminder->delete();

    SendReminder::dispatchSync($reminder->id);

    Mail::assertNothingSent();
});

it('sends a reminder up to 10 minutes late', function () {
    $reminder = dueReminder($this->fast, ['send_at' => '2026-10-19 09:50:00']);

    SendReminder::dispatchSync($reminder->id);

    Mail::assertSentCount(1);
});

it('cancels a reminder more than 10 minutes late instead of sending it', function () {
    $reminder = dueReminder($this->fast, ['send_at' => '2026-10-19 09:49:59']);

    SendReminder::dispatchSync($reminder->id);

    Mail::assertNothingSent();
    expect($reminder->fresh()->status)->toBe(ReminderStatus::Cancelled);
});

it('cancels the reminder if the email address is not confirmed', function () {
    $this->user->forceFill(['email_verified_at' => null])->save();
    $reminder = dueReminder($this->fast);

    SendReminder::dispatchSync($reminder->id);

    Mail::assertNothingSent();
    expect($reminder->fresh()->status)->toBe(ReminderStatus::Cancelled);
});

it('cancels the reminder if its type is switched off', function (ReminderType $type) {
    $this->user->forceFill([$type->settingColumn() => false])->save();
    $reminder = dueReminder($this->fast, ['type' => $type]);

    SendReminder::dispatchSync($reminder->id);

    Mail::assertNothingSent();
    expect($reminder->fresh()->status)->toBe(ReminderStatus::Cancelled);
})->with(ReminderType::cases());

it('checks the settings when sending, not when scheduling', function () {
    $this->user->forceFill(['remind_eating_window_closing' => false])->save();
    $reminder = dueReminder($this->fast, ['type' => ReminderType::EatingWindowClosing]);

    $this->user->forceFill(['remind_eating_window_closing' => true])->save();
    SendReminder::dispatchSync($reminder->id);

    Mail::assertSentCount(1);
});

it('leaves the reminder pending for a retry if sending fails', function () {
    $reminder = dueReminder($this->fast);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));

    expect(fn () => SendReminder::dispatchSync($reminder->id))->toThrow(RuntimeException::class);

    expect($reminder->fresh())
        ->status->toBe(ReminderStatus::Pending)
        ->sent_at->toBeNull();
});

it('cancels a reminder that keeps failing once it is more than 10 minutes late', function () {
    $reminder = dueReminder($this->fast);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));

    // While it's at most 10 minutes late, each attempt fails and leaves it
    // pending, so the dispatcher queues it again the next minute.
    foreach (range(0, 10) as $minutesLate) {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00:00')->addMinutes($minutesLate));

        expect(fn () => SendReminder::dispatchSync($reminder->id))->toThrow(RuntimeException::class);
        expect($reminder->fresh()->status)->toBe(ReminderStatus::Pending);
        expect(Reminder::query()->due(CarbonImmutable::now())->pluck('id')->all())->toBe([$reminder->id]);
    }

    // Eleven minutes late it's no longer useful, so the next attempt cancels
    // it without trying to send.
    $this->travelTo('2026-10-19 10:11:00');
    SendReminder::dispatchSync($reminder->id);

    expect($reminder->fresh()->status)->toBe(ReminderStatus::Cancelled);
    expect(Reminder::query()->due(CarbonImmutable::now())->count())->toBe(0);
});

describe('the email', function () {
    it('tells the user how long is left, in their time zone', function () {
        $mail = new ReminderMail(dueReminder($this->fast, [
            'type' => ReminderType::BeforeGoal,
            'send_at' => '2026-10-19 09:30:00',
        ]));

        $mail->assertHasSubject('30 minutes to go');
        $mail->assertSeeInText("You're 30 minutes from your 16-hour goal, at 1:00 pm.");
    });

    it('says the goal is reached', function () {
        $mail = new ReminderMail(dueReminder($this->fast));

        $mail->assertHasSubject("You've reached your fasting goal");
        $mail->assertSeeInText("You've fasted 16 hours. End your fast whenever you're ready.");
    });

    it('says when the next fast is planned, in their time zone', function () {
        $mail = new ReminderMail(dueReminder($this->fast, [
            'type' => ReminderType::EatingWindowClosing,
            'send_at' => '2026-10-19 16:30:00',
        ]));

        $mail->assertHasSubject('Your eating window closes in an hour');
        $mail->assertSeeInText('Your next fast is planned for 8:30 pm');
    });

    it('greets the user and links to their email preferences', function () {
        $mail = new ReminderMail(dueReminder($this->fast));

        $mail->assertSeeInText("Hi {$this->user->first_name},");
        $mail->assertSeeInHtml('Email preferences');
        $mail->assertSeeInHtml('Unsubscribe');
    });
});
