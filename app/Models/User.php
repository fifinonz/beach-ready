<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
     * Reads a time the user typed. A value with a UTC offset is taken as it
     * is; one without is read as wall-clock time in the user's time zone.
     */
    public function localTimeToUtc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $this->timezone)->utc();
    }
}
