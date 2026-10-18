<?php

namespace App\Enums;

use App\Models\Fast;
use Carbon\CarbonImmutable;

enum ReminderType: string
{
    case BeforeGoal = 'before_goal';
    case GoalReached = 'goal_reached';
    case EatingWindowClosing = 'eating_window_closing';

    /**
     * The reminders that belong to a fast in progress. The eating window
     * reminder only exists once a fast has ended.
     *
     * @return array<int, self>
     */
    public static function duringFast(): array
    {
        return [self::BeforeGoal, self::GoalReached];
    }

    /**
     * When this reminder is due for the given fast, in UTC.
     */
    public function sendAtFor(Fast $fast): CarbonImmutable
    {
        return match ($this) {
            self::BeforeGoal => $fast->target_end_at->subMinutes(30),
            self::GoalReached => $fast->target_end_at,
            // The next fast is planned for the end of the eating window, which
            // starts when this fast actually ended.
            self::EatingWindowClosing => $fast->ended_at->addHours($fast->eating_hours)->subHour(),
        };
    }

    /**
     * The users column holding whether this type is switched on.
     */
    public function settingColumn(): string
    {
        return 'remind_'.$this->value;
    }
}
