<?php

namespace App\Http\Controllers\Fasts;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The numbers on the Profile screen: how many fasts the person has finished
 * and the longest one, counting ended-early fasts like any other.
 */
class AllTimeStatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $stats = $request->user()->fasts()
            ->ended()
            ->toBase()
            ->selectRaw('count(*) as fasts_logged')
            ->selectRaw('max(extract(epoch from ended_at - started_at)) as longest_fast_seconds')
            ->first();

        return response()->json(['data' => [
            'fasts_logged' => (int) $stats->fasts_logged,
            'longest_fast_seconds' => $stats->longest_fast_seconds === null ? null : (int) round($stats->longest_fast_seconds),
        ]]);
    }
}
