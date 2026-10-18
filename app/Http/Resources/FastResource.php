<?php

namespace App\Http\Resources;

use App\Models\Fast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Times are UTC, in ISO 8601. The client converts them to the user's time
 * zone for display.
 *
 * @mixin Fast
 */
class FastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'protocol' => "{$this->fasting_hours}:{$this->eating_hours}",
            'fasting_hours' => $this->fasting_hours,
            'eating_hours' => $this->eating_hours,
            'started_at' => $this->started_at->toIso8601ZuluString(),
            'target_end_at' => $this->target_end_at->toIso8601ZuluString(),
            'ended_at' => $this->ended_at?->toIso8601ZuluString(),
            'ended_early' => $this->ended_early,
            'feeling' => $this->feeling,
            'duration_seconds' => $this->isActive() ? null : $this->durationInSeconds(),
        ];
    }
}
