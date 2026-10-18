<?php

namespace App\Http\Requests\Settings;

use App\Consent\EmailTopic;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailSettingsRequest extends FormRequest
{
    /**
     * Any of the email topics, each switched on or off. Topics left out keep
     * their current setting.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(EmailTopic::cases())
            ->mapWithKeys(fn (EmailTopic $topic) => [$topic->value => ['sometimes', 'boolean']])
            ->all();
    }
}
