<?php

namespace App\Http\Controllers\Fasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fasts\StartFastRequest;
use App\Http\Resources\FastResource;
use App\Models\Fast;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function store(StartFastRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->activeFast()->exists(), 409, 'You already have a fast in progress.');

        $fastingHours = $request->integer('fasting_hours');
        $fast = new Fast([
            'fasting_hours' => $fastingHours,
            'eating_hours' => Fast::HOURS_IN_DAY - $fastingHours,
        ]);
        $fast->startAt($request->startedAt());

        try {
            $user->fasts()->save($fast);
        } catch (UniqueConstraintViolationException) {
            // Another request started a fast between the check and the insert.
            abort(409, 'You already have a fast in progress.');
        }

        return FastResource::make($fast)->response()->setStatusCode(201);
    }
}
