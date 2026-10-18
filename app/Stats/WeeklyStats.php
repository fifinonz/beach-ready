<?php

namespace App\Stats;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The numbers at the top of the history screen. Days and weeks are the
 * user's own, in their time zone, with weeks starting on Monday. A fast
 * belongs to the day it ended on, and every finished fast counts, including
 * ones ended early.
 */
class WeeklyStats
{
    public function __construct(
        public readonly int $fastsThisWeek,
        public readonly ?int $averageFastSeconds,
        public readonly int $currentStreakDays,
    ) {}

    public static function for(User $user, CarbonImmutable $now): self
    {
        $localNow = $now->setTimezone($user->timezone);
        $weekStart = $localNow->startOfWeek(CarbonInterface::MONDAY)->utc();

        $thisWeek = $user->fasts()
            ->ended()
            ->where('ended_at', '>=', $weekStart)
            ->get();

        return new self(
            fastsThisWeek: $thisWeek->count(),
            averageFastSeconds: $thisWeek->isEmpty()
                ? null
                : (int) round($thisWeek->avg(fn ($fast) => $fast->durationInSeconds())),
            currentStreakDays: self::streak($user, $localNow),
        );
    }

    /**
     * How many rest days in a row a streak survives. See decision 0003.
     */
    private const REST_DAYS_ALLOWED = 1;

    /**
     * The number of days with a finished fast in the current streak, counting
     * back from today. A single rest day doesn't break a streak; two in a row
     * do. Today doesn't count as a rest day until it's over.
     */
    private static function streak(User $user, CarbonImmutable $localNow): int
    {
        $daysWithAFast = $user->fasts()
            ->ended()
            ->pluck('ended_at')
            ->map(fn (CarbonImmutable $endedAt) => $endedAt->setTimezone($user->timezone)->toDateString())
            ->flip();

        $day = $localNow->startOfDay();

        if (! $daysWithAFast->has($day->toDateString())) {
            $day = $day->subDay();
        }

        $streak = 0;
        $restDaysInARow = 0;

        while ($restDaysInARow <= self::REST_DAYS_ALLOWED) {
            if ($daysWithAFast->has($day->toDateString())) {
                $streak++;
                $restDaysInARow = 0;
            } else {
                $restDaysInARow++;
            }

            $day = $day->subDay();
        }

        return $streak;
    }
}
