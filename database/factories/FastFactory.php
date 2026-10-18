<?php

namespace Database\Factories;

use App\Models\Fast;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fast>
 */
class FastFactory extends Factory
{
    /**
     * An active 16:8 fast that started two hours ago.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = CarbonImmutable::now()->subHours(2);

        return [
            'user_id' => User::factory(),
            'fasting_hours' => 16,
            'eating_hours' => 8,
            'started_at' => $startedAt,
            'target_end_at' => $startedAt->addHours(16),
        ];
    }

    /**
     * A finished fast between two UTC instants, ended early if it stopped
     * before the goal.
     */
    public function between(string $startedAt, string $endedAt, int $fastingHours = 16): static
    {
        $start = CarbonImmutable::parse($startedAt, 'UTC');
        $end = CarbonImmutable::parse($endedAt, 'UTC');
        $target = $start->addHours($fastingHours);

        return $this->state([
            'fasting_hours' => $fastingHours,
            'eating_hours' => Fast::HOURS_IN_DAY - $fastingHours,
            'started_at' => $start,
            'target_end_at' => $target,
            'ended_at' => $end,
            'ended_early' => $end->lt($target),
        ]);
    }
}
