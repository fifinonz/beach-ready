<?php

namespace App\Http\Requests\Fasts;

use App\Enums\Feeling;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EndFastRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'feeling' => ['nullable', Rule::enum(Feeling::class)],
        ];
    }

    public function feeling(): ?Feeling
    {
        return $this->enum('feeling', Feeling::class);
    }
}
