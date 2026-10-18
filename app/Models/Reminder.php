<?php

namespace App\Models;

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ReminderType $type
 * @property ReminderStatus $status
 * @property CarbonImmutable $send_at
 * @property CarbonImmutable|null $sent_at
 */
#[Fillable(['type', 'send_at', 'status', 'sent_at'])]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * A reminder this late is no longer useful ("30 minutes to go", an hour
     * after the goal), so it's cancelled instead of sent.
     */
    public const MAX_LATENESS_MINUTES = 10;

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'status' => ReminderStatus::class,
            'send_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function fast(): BelongsTo
    {
        return $this->belongsTo(Fast::class);
    }

    /**
     * @param  Builder<Reminder>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ReminderStatus::Pending);
    }

    /**
     * @param  Builder<Reminder>  $query
     */
    public function scopeDue(Builder $query, CarbonImmutable $now): void
    {
        $query->pending()->where('send_at', '<=', $now);
    }

    public function isDue(CarbonImmutable $now): bool
    {
        return $this->status === ReminderStatus::Pending && $this->send_at->lte($now);
    }

    public function isTooLate(CarbonImmutable $now): bool
    {
        return $this->send_at->addMinutes(self::MAX_LATENESS_MINUTES)->lt($now);
    }
}
