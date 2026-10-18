<?php

namespace App\Http\Requests\Account;

use App\Models\Fast;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Any of the profile fields. Fields left out keep their current value.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            // Sent by the app whenever the phone's time zone changes, so
            // reminders and "today" follow the person when they travel.
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'why' => ['sometimes', 'nullable', 'string', 'max:500'],
            'fasting_hours' => ['sometimes', 'required', 'integer', 'between:'.Fast::MIN_FASTING_HOURS.','.Fast::MAX_FASTING_HOURS],
        ];
    }
}
