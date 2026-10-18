<?php

namespace App\Http\Requests\Fasts;

use App\Http\Requests\Concerns\ValidatesStartTime;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStartTimeRequest extends FormRequest
{
    use ValidatesStartTime;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'started_at' => ['required', 'string', 'date'],
        ];
    }
}
