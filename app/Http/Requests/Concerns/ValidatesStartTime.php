<?php

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Shared by the requests that set a fast's start time. The time may be sent
 * with or without a UTC offset (see User::localTimeToUtc), and can't be in
 * the future.
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

                if ($this->startedAt()->isFuture()) {
                    $validator->errors()->add('started_at', "The start time can't be in the future.");
                }
            },
        ];
    }

    public function startedAt(): CarbonImmutable
    {
        if (! $this->filled('started_at')) {
            return CarbonImmutable::now();
        }

        return $this->user()->localTimeToUtc((string) $this->input('started_at'));
    }
}
