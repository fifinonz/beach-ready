<?php

namespace App\Http\Controllers\Fasts;

use App\Http\Controllers\Controller;
use App\Stats\WeeklyStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyStatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $stats = WeeklyStats::for($request->user(), CarbonImmutable::now());

        return response()->json(['data' => [
            'fasts_this_week' => $stats->fastsThisWeek,
            'average_fast_seconds' => $stats->averageFastSeconds,
            'current_streak_days' => $stats->currentStreakDays,
        ]]);
    }
}
