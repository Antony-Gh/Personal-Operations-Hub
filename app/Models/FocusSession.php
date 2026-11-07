<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FocusSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'mode',
        'planned_minutes',
        'elapsed_seconds',
        'logged_minutes',
        'status',
        'started_at',
        'last_resumed_at',
        'paused_at',
        'ended_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_minutes' => 'integer',
            'elapsed_seconds' => 'integer',
            'logged_minutes' => 'integer',
            'started_at' => 'datetime',
            'last_resumed_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Calculate accurate elapsed seconds using timestamp deltas rather than interval ticks.
     */
    public function currentElapsedSeconds(?Carbon $now = null): int
    {
        $base = (int) $this->elapsed_seconds;

        if ($this->status === 'running' && $this->last_resumed_at !== null) {
            $reference = $now ?? now();
            $delta = max(0, (int) $this->last_resumed_at->diffInSeconds($reference, false));

            return $base + $delta;
        }

        return $base;
    }

    /**
     * Remaining seconds for countdown display.
     */
    public function remainingSeconds(?Carbon $now = null): int
    {
        $plannedSeconds = max(60, ((int) $this->planned_minutes) * 60);

        return max(0, $plannedSeconds - $this->currentElapsedSeconds($now));
    }
}
