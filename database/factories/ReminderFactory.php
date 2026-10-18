<?php

namespace Database\Factories;

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Models\Fast;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * A pending "goal reached" reminder that's due now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fast_id' => Fast::factory(),
            'type' => ReminderType::GoalReached,
            'send_at' => now(),
            'status' => ReminderStatus::Pending,
        ];
    }
}
