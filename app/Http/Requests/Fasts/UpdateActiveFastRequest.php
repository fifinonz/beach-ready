<?php

namespace App\Http\Requests\Fasts;

use App\Http\Requests\Concerns\ValidatesStartTime;
use App\Models\Fast;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing the fast in progress: its start time ("Edit start time"), its
 * protocol ("16:8 · change"), or both. Either way the goal moves with it.
 */
class UpdateActiveFastRequest extends FormRequest
{
    use ValidatesStartTime;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'started_at' => ['required_without:fasting_hours', 'string', 'date'],
            'fasting_hours' => ['required_without:started_at', 'integer', 'between:'.Fast::MIN_FASTING_HOURS.','.Fast::MAX_FASTING_HOURS],
        ];
    }
}
