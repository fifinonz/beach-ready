<?php

namespace App\Http\Controllers\Fasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fasts\StartFastRequest;
use App\Http\Resources\FastResource;
use App\Models\Fast;
use App\Reminders\ReminderScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FastController extends Controller
{
    private const HISTORY_PAGE_SIZE = 20;

    /**
     * Finished fasts, most recent first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $fasts = $request->user()->fasts()
            ->ended()
            ->orderByDesc('ended_at')
            ->paginate(self::HISTORY_PAGE_SIZE);

        return FastResource::collection($fasts);
    }

    public function store(StartFastRequest $request, ReminderScheduler $reminders): JsonResponse
    {
        $user = $request->user();

        abort_if($user->activeFast()->exists(), 409, 'You already have a fast in progress.');

        $fast = new Fast;
        $fast->changeProtocol($request->integer('fasting_hours'));
        $fast->startAt($request->startedAt());

        try {
            DB::transaction(function () use ($user, $fast, $reminders) {
                $user->fasts()->save($fast);
                $reminders->fastStarted($fast, CarbonImmutable::now());
            });
        } catch (UniqueConstraintViolationException) {
            // Another request started a fast between the check and the insert.
            abort(409, 'You already have a fast in progress.');
        }

        return FastResource::make($fast)->response()->setStatusCode(201);
    }
}
