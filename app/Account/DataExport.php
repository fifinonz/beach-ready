<?php

namespace App\Account;

use App\Consent\EmailTopic;
use App\Enums\ReminderType;
use App\Models\Fast;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * "Download my data": everything Beach Ready stores about a user, as JSON.
 * Times are UTC in ISO 8601, like the API. Passwords and session tokens are
 * left out; they're credentials, not the user's data.
 */
class DataExport
{
    /**
     * @return array<string, mixed>
     */
    public static function for(User $user): array
    {
        $fasts = $user->fasts()->with('reminders')->orderBy('started_at')->get();

        return [
            'exported_at' => CarbonImmutable::now()->toIso8601ZuluString(),
            'profile' => [
                'first_name' => $user->first_name,
                'email' => $user->email,
                'timezone' => $user->timezone,
                'fasting_hours' => $user->fasting_hours,
                'why' => $user->why,
                'created_at' => $user->created_at->toIso8601ZuluString(),
            ],
            'email_settings' => [
                ...collect(EmailTopic::cases())->mapWithKeys(fn (EmailTopic $topic) => [$topic->value => $user->{$topic->column()}]),
                'email_confirmed_at' => $user->email_verified_at?->toIso8601ZuluString(),
                'confirmation_sent_at' => $user->confirmation_sent_at?->toIso8601ZuluString(),
            ],
            'reminder_settings' => collect(ReminderType::cases())
                ->mapWithKeys(fn (ReminderType $type) => [$type->value => $user->{$type->settingColumn()}])
                ->all(),
            'fasts' => $fasts->map(fn (Fast $fast) => [
                'protocol' => "{$fast->fasting_hours}:{$fast->eating_hours}",
                'started_at' => $fast->started_at->toIso8601ZuluString(),
                'target_end_at' => $fast->target_end_at->toIso8601ZuluString(),
                'ended_at' => $fast->ended_at?->toIso8601ZuluString(),
                'ended_early' => $fast->ended_early,
                'feeling' => $fast->feeling?->value,
                'reminders' => $fast->reminders->sortBy('send_at')->values()->map(fn (Reminder $reminder) => [
                    'type' => $reminder->type->value,
                    'send_at' => $reminder->send_at->toIso8601ZuluString(),
                    'status' => $reminder->status->value,
                    'sent_at' => $reminder->sent_at?->toIso8601ZuluString(),
                ])->all(),
            ])->all(),
        ];
    }
}
