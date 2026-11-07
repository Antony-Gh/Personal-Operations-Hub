<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'remindable_type',
        'remindable_id',
        'reminder_key',
        'category',
        'title',
        'message',
        'offset_label',
        'is_critical',
        'scheduled_at',
        'snoozed_until',
        'status',
        'sent_at',
        'dismissed_at',
        'attempts',
        'last_error',
        'suppressed_by_quiet_hours',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'scheduled_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'sent_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'attempts' => 'integer',
            'suppressed_by_quiet_hours' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDue(Builder $query, ?Carbon $nowUtc = null): Builder
    {
        $cutoff = ($nowUtc ?? now())->copy()->utc();

        return $query->where(function (Builder $q) use ($cutoff): void {
            $q->where(function (Builder $pending) use ($cutoff): void {
                $pending->where('status', 'pending')
                    ->where('scheduled_at', '<=', $cutoff);
            })->orWhere(function (Builder $snoozed) use ($cutoff): void {
                $snoozed->where('status', 'snoozed')
                    ->whereNotNull('snoozed_until')
                    ->where('snoozed_until', '<=', $cutoff);
            });
        });
    }
}
