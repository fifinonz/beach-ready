<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ReminderType;
use App\Mail\ResetPasswordMail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

#[Fillable(['first_name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The same defaults as the columns, so a user who was just created has
     * them before being read back from the database.
     */
    protected $attributes = [
        'remind_before_goal' => true,
        'remind_goal_reached' => true,
        'remind_eating_window_closing' => false,
        'email_fasting_reminders' => false,
        'email_weekly_summary' => false,
        'email_product_news' => false,
    ];

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
            'email_fasting_reminders' => 'boolean',
            'email_weekly_summary' => 'boolean',
            'email_product_news' => 'boolean',
            'confirmation_sent_at' => 'immutable_datetime',
            'fasting_hours' => 'integer',
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
     * Sends our own reset email instead of Laravel's default notification,
     * queued so the response takes as long whether or not the account
     * exists.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        Mail::to($this)->queue(new ResetPasswordMail($this, $token));
    }

    /**
     * Whether the address was confirmed through the link in the confirmation
     * email. Nothing is emailed until it is.
     */
    public function hasConfirmedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Whether a reminder of this type may be emailed now: the address is
     * confirmed, fasting reminder emails are on, and so is this type. Checked
     * at send time, so a change mid-fast applies to reminders still to come.
     */
    public function wantsReminder(ReminderType $type): bool
    {
        return $this->hasConfirmedEmail()
            && $this->email_fasting_reminders
            && $this->{$type->settingColumn()};
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
