<?php

namespace App\Http\Requests\Settings;

use App\Enums\ReminderType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderSettingsRequest extends FormRequest
{
    /**
     * Any of the reminder types, each switched on or off. Types left out
     * keep their current setting.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(ReminderType::cases())
            ->mapWithKeys(fn (ReminderType $type) => [$type->value => ['sometimes', 'boolean']])
            ->all();
    }
}
