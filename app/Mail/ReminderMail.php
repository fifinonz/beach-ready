<?php

namespace App\Mail;

use App\Enums\ReminderType;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent by the SendReminder job, never queued on its own: the job decides
 * whether and when it goes. Times are shown in the user's time zone.
 */
class ReminderMail extends BeachReadyMail
{
    public function __construct(public Reminder $reminder) {}

    protected function recipient(): User
    {
        return $this->reminder->fast->user;
    }

    protected function reason(): string
    {
        return "You're getting this because you turned on fasting reminders.";
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->reminder->type) {
            ReminderType::BeforeGoal => '30 minutes to go',
            ReminderType::GoalReached => "You've reached your fasting goal",
            ReminderType::EatingWindowClosing => 'Your eating window closes in an hour',
        });
    }

    public function content(): Content
    {
        $fast = $this->reminder->fast;
        $user = $fast->user;
        $localTime = fn ($time) => $time->setTimezone($user->timezone)->format('g:i a');

        return new Content(
            markdown: 'mail.reminder',
            text: 'mail.text.reminder',
            with: [
                'firstName' => $user->first_name,
                'body' => match ($this->reminder->type) {
                    ReminderType::BeforeGoal => "You're 30 minutes from your {$fast->fasting_hours}-hour goal, at {$localTime($fast->target_end_at)}.",
                    ReminderType::GoalReached => "You've fasted {$fast->fasting_hours} hours. End your fast whenever you're ready.",
                    ReminderType::EatingWindowClosing => "Your next fast is planned for {$localTime($this->reminder->send_at->addHour())}, so now is a good time for your last meal.",
                },
                ...$this->footer(),
            ],
        );
    }
}
