<?php

namespace App\Consent;

use App\Models\User;

/**
 * The kinds of email a user can choose to get. All are off until they turn
 * one on, and none is sent until the address is confirmed.
 */
enum EmailTopic: string
{
    case FastingReminders = 'fasting_reminders';
    case WeeklySummary = 'weekly_summary';
    case ProductNews = 'product_news';

    public function column(): string
    {
        return 'email_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::FastingReminders => 'Fasting reminders',
            self::WeeklySummary => 'Weekly summary',
            self::ProductNews => 'Product news',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FastingReminders => 'Only the ones you set up',
            self::WeeklySummary => 'Sunday evening, one email',
            self::ProductNews => 'New features, a few times a year',
        };
    }

    /**
     * Whether the user has any topic switched on.
     */
    public static function anyOnFor(User $user): bool
    {
        return collect(self::cases())->contains(fn (self $topic) => $user->{$topic->column()});
    }

    /**
     * Switches every topic off: "Unsubscribe from all emails".
     */
    public static function unsubscribeFromAll(User $user): void
    {
        foreach (self::cases() as $topic) {
            $user->{$topic->column()} = false;
        }

        $user->save();
    }
}
