<?php

namespace App\Models;

use App\Enums\Feeling;
use Carbon\CarbonImmutable;
use Database\Factories\FastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $target_end_at
 * @property CarbonImmutable|null $ended_at
 */
#[Fillable(['fasting_hours', 'eating_hours', 'started_at', 'target_end_at', 'ended_at', 'ended_early', 'feeling'])]
class Fast extends Model
{
    /** @use HasFactory<FastFactory> */
    use HasFactory;

    /**
     * The longest fast anyone can set, in hours. Protocols are a fasting
     * window followed by an eating window, adding up to a 24-hour day.
     */
    public const MAX_FASTING_HOURS = 20;

    public const HOURS_IN_DAY = 24;

    protected $attributes = [
        'ended_early' => false,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'target_end_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'ended_early' => 'boolean',
            'feeling' => Feeling::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Fast>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /**
     * @param  Builder<Fast>  $query
     */
    public function scopeEnded(Builder $query): void
    {
        $query->whereNotNull('ended_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Moves the start time and keeps the goal the same number of hours after it.
     */
    public function startAt(CarbonImmutable $startedAt): void
    {
        $this->started_at = $startedAt;
        $this->target_end_at = $startedAt->addHours($this->fasting_hours);
    }

    /**
     * Ending before the goal is a normal way to finish a fast; it is recorded,
     * not judged.
     */
    public function end(CarbonImmutable $endedAt, ?Feeling $feeling): void
    {
        $this->ended_at = $endedAt;
        $this->ended_early = $endedAt->lt($this->target_end_at);
        $this->feeling = $feeling;
    }

    /**
     * Seconds fasted, always calculated from the stored start time.
     */
    public function durationInSeconds(?CarbonImmutable $now = null): int
    {
        $until = $this->ended_at ?? $now ?? CarbonImmutable::now();

        return (int) $this->started_at->diffInSeconds($until);
    }
}
