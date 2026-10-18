<?php

namespace App\Http\Requests\Concerns;

use App\Models\Fast;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Shared by the requests that set a fast's start time. The time may be sent
 * with or without a UTC offset (see User::localTimeToUtc). It can't be in the
 * future, more than 24 hours ago, or before the user's previous fast ended,
 * so fasts never overlap.
 */
trait ValidatesStartTime
{
    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('started_at') || ! $this->filled('started_at')) {
                    return;
                }

                $problem = $this->startTimeProblem($this->startedAt(), CarbonImmutable::now());

                if ($problem !== null) {
                    $validator->errors()->add('started_at', $problem);
                }
            },
        ];
    }

    private function startTimeProblem(CarbonImmutable $startedAt, CarbonImmutable $now): ?string
    {
        if ($startedAt->gt($now)) {
            return "The start time can't be in the future.";
        }

        if ($startedAt->lt($now->subHours(Fast::MAX_BACKDATE_HOURS))) {
            return "The start time can't be more than 24 hours ago.";
        }

        $previousFastEndedAt = $this->user()->fasts()->ended()->latest('ended_at')->value('ended_at');

        if ($previousFastEndedAt !== null && $startedAt->lt($previousFastEndedAt)) {
            return "The start time can't be before your last fast ended.";
        }

        return null;
    }

    public function startedAt(): CarbonImmutable
    {
        if (! $this->filled('started_at')) {
            return CarbonImmutable::now();
        }

        return $this->user()->localTimeToUtc((string) $this->input('started_at'));
    }
}
