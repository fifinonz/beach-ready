<?php

namespace App\Http\Controllers\Fasts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fasts\EndFastRequest;
use App\Http\Requests\Fasts\UpdateStartTimeRequest;
use App\Http\Resources\FastResource;
use App\Models\Fast;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function update(UpdateStartTimeRequest $request): FastResource
    {
        $fast = $this->activeFastOrFail($request);

        $fast->startAt($request->startedAt());
        $fast->save();

        return FastResource::make($fast);
    }

    public function end(EndFastRequest $request): FastResource
    {
        $fast = $this->activeFastOrFail($request);

        $fast->end(CarbonImmutable::now(), $request->feeling());
        $fast->save();

        return FastResource::make($fast);
    }

    private function activeFastOrFail(Request $request): Fast
    {
        $fast = $request->user()->activeFast;

        abort_if($fast === null, 404, "You don't have a fast in progress.");

        return $fast;
    }
}
