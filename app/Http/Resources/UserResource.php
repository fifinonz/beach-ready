<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'email_confirmed' => $this->hasConfirmedEmail(),
            'fasting_hours' => $this->fasting_hours,
            'why' => $this->why,
            'member_since' => $this->created_at?->toIso8601ZuluString(),
            'is_demo' => $this->is_demo,
        ];
    }
}
