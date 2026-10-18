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
     * Consecutive days with at least one finished fast, counting back from
     * today. A streak stays alive through today until midnight, so if nothing
     * has ended yet today it counts back from yesterday.
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

        while ($daysWithAFast->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }
}
