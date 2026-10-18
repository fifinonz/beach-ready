<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => 'Africa/Nairobi',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * New users haven't confirmed their address, like real sign-ups. This
     * state is a user who has.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => now(),
            'confirmation_sent_at' => now(),
        ]);
    }

    /**
     * A user who turned on fasting reminder emails and confirmed the address.
     */
    public function receivingReminders(): static
    {
        return $this->confirmed()->state(fn (array $attributes) => [
            'email_fasting_reminders' => true,
        ]);
    }
}
