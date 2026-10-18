<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ReminderType;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'remind_before_goal' => 'boolean',
            'remind_goal_reached' => 'boolean',
            'remind_eating_window_closing' => 'boolean',
        ];
    }

    public function fasts(): HasMany
    {
        return $this->hasMany(Fast::class);
    }

    public function activeFast(): HasOne
    {
        return $this->hasOne(Fast::class)->whereNull('ended_at');
    }

    /**
     * Whether a reminder of this type may be emailed now. Checked at send
     * time, so turning a type on or confirming mid-fast takes effect for
     * reminders still to come. For now, confirmed means email_verified_at;
     * stage 3 builds the confirmation and adds the "Fasting reminders" topic.
     */
    public function wantsReminder(ReminderType $type): bool
    {
        return $this->email_verified_at !== null && $this->{$type->settingColumn()};
    }

    /**
     * Reads a time the user typed. A value with a UTC offset is taken as it
     * is; one without is read as wall-clock time in the user's time zone.
     */
    public function localTimeToUtc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $this->timezone)->utc();
    }
}
