<?php

namespace App\Http\Requests\Fasts;

use App\Http\Requests\Concerns\ValidatesStartTime;
use App\Models\Fast;
use Illuminate\Foundation\Http\FormRequest;

class StartFastRequest extends FormRequest
{
    use ValidatesStartTime;

    /**
     * 14:10, 16:8 and 18:6 are sent as 14, 16 and 18 fasting hours. Custom
     * fasts can be any whole number of hours up to the cap.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fasting_hours' => ['required', 'integer', 'between:1,'.Fast::MAX_FASTING_HOURS],
            'started_at' => ['nullable', 'string', 'date'],
        ];
    }
}
