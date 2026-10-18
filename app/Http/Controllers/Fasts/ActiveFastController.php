<?php

namespace App\Http\Controllers\Fasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fasts\EndFastRequest;
use App\Http\Requests\Fasts\UpdateActiveFastRequest;
use App\Http\Resources\FastResource;
use App\Models\Fast;
use App\Reminders\ReminderScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActiveFastController extends Controller
{
    /**
     * The fast in progress, or null when there isn't one. Not having a fast
     * running is a normal state, so this isn't a 404.
     */
    public function show(Request $request): JsonResponse
    {
        $fast = $request->user()->activeFast;

        return response()->json(['data' => $fast ? FastResource::make($fast) : null]);
    }

    public function update(UpdateActiveFastRequest $request, ReminderScheduler $reminders): FastResource
    {
        $fast = $this->activeFastOrFail($request);

        DB::transaction(function () use ($fast, $request, $reminders) {
            if ($request->filled('fasting_hours')) {
                $fast->changeProtocol($request->integer('fasting_hours'));
            }

            $fast->startAt($request->filled('started_at') ? $request->startedAt() : $fast->started_at);
            $fast->save();
            $reminders->startTimeChanged($fast, CarbonImmutable::now());
        });

        return FastResource::make($fast);
    }

    public function end(EndFastRequest $request, ReminderScheduler $reminders): FastResource
    {
        $fast = $this->activeFastOrFail($request);
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($fast, $request, $reminders, $now) {
            $fast->end($now, $request->feeling());
            $fast->save();
            $reminders->fastEnded($fast, $now);
        });

        return FastResource::make($fast);
    }

    private function activeFastOrFail(Request $request): Fast
    {
        $fast = $request->user()->activeFast;

        abort_if($fast === null, 404, "You don't have a fast in progress.");

        return $fast;
    }
}
