<?php

namespace App\Models;

use App\Services\ReminderService;
use App\Support\UserDateTime;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProjectMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'due_date',
        'due_time',
        'reminders_enabled',
        'reminder_offsets',
        'is_completed',
        'completed_at',
        'sort_order',
    ];

    protected $appends = [
        'formatted_due_time',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'reminders_enabled' => 'boolean',
            'reminder_offsets' => 'array',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ProjectMilestone $milestone): void {
            app(ReminderService::class)->syncMilestoneReminders($milestone);
        });

        static::deleted(function (ProjectMilestone $milestone): void {
            app(ReminderService::class)->cancelPendingRemindersFor($milestone);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Get the formatted 12-hour due time with AM/PM (e.g., 2:30 PM).
     */
    protected function formattedDueTime(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?string => UserDateTime::formatTimeAmPm($attributes['due_time'] ?? null),
        );
    }

    /**
     * Helper method to get the milestone due time with AM/PM.
     */
    public function dueTimeAmPm(): ?string
    {
        return UserDateTime::formatTimeAmPm($this->due_time);
    }
}
