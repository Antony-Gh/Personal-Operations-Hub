<?php

namespace App\Models;

use App\Services\RecurringTaskService;
use App\Services\ReminderService;
use App\Services\TaskDependencyService;
use App\Support\UserDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'project_id',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'due_time',
        'reminder_preset',
        'remind_at',
        'custom_reminder_times',
        'start_date',
        'estimated_duration',
        'actual_duration',
        'notes',
        'next_action',
        'sort_order',
        'is_recurring',
        'recurrence_rule',
        'recurrence_interval',
        'recurrence_end_type',
        'recurrence_end_date',
        'recurrence_max_occurrences',
        'recurrence_occurrences_count',
        'recurrence_parent_id',
        'recurrence_generated',
        'completed_at',
    ];

    protected $appends = [
        'formatted_due_time',
        'formatted_due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'remind_at' => 'datetime',
            'custom_reminder_times' => 'array',
            'start_date' => 'date:Y-m-d',
            'estimated_duration' => 'integer',
            'actual_duration' => 'integer',
            'sort_order' => 'integer',
            'is_recurring' => 'boolean',
            'recurrence_interval' => 'integer',
            'recurrence_end_date' => 'date:Y-m-d',
            'recurrence_max_occurrences' => 'integer',
            'recurrence_occurrences_count' => 'integer',
            'recurrence_generated' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Task $task): void {
            app(ReminderService::class)->syncTaskReminders($task);
        });

        static::deleted(function (Task $task): void {
            app(ReminderService::class)->cancelPendingRemindersFor($task);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag');
    }

    public function blockedByTasks(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withTimestamps();
    }

    public function blockingTasks(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withTimestamps();
    }

    public function dailyPlanTasks(): HasMany
    {
        return $this->hasMany(DailyPlanTask::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function focusSessions(): HasMany
    {
        return $this->hasMany(FocusSession::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function recurrenceChildren(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'completed');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Centralized priority ranking: High (1) -> Medium (2) -> Low (3).
     */
    public function scopeOrderByPriority(Builder $query, string $direction = 'asc'): Builder
    {
        $sqlDir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw(
            "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END {$sqlDir}"
        );
    }

    public function scopeDueToday(Builder $query, ?User $user = null): Builder
    {
        $today = UserDateTime::todayDateString($user);

        return $query->where('due_date', $today);
    }

    public function scopeOverdue(Builder $query, ?User $user = null): Builder
    {
        $today = UserDateTime::todayDateString($user);

        return $query->where('due_date', '<', $today)
            ->where('status', '!=', 'completed');
    }

    public function scopeUpcoming(Builder $query, int $days = 7, ?User $user = null): Builder
    {
        $localToday = UserDateTime::todayForUser($user);
        $today = $localToday->toDateString();
        $future = $localToday->copy()->addDays($days)->toDateString();

        return $query->whereBetween('due_date', [$today, $future])
            ->where('status', '!=', 'completed');
    }

    /**
     * Complete the task and, if recurring, spawn its next occurrence.
     */
    public function markAsCompleted(): ?self
    {
        return DB::transaction(function (): ?self {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            app(TaskDependencyService::class)->handleTaskCompleted($this);

            if ($this->is_recurring) {
                return app(RecurringTaskService::class)->generateNextOccurrence($this);
            }

            return null;
        });
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
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
     * Helper method to get the task due time with AM/PM.
     */
    public function dueTimeAmPm(): ?string
    {
        return UserDateTime::formatTimeAmPm($this->due_time);
    }

    /**
     * Get the formatted due date with day of week (e.g., Tue, 2026-11-10).
     */
    protected function formattedDueDate(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?string => UserDateTime::formatDateWithDay($attributes['due_date'] ?? null),
        );
    }

    /**
     * Helper method to get the task due date with day of week.
     */
    public function dueDateWithDay(): ?string
    {
        return UserDateTime::formatDateWithDay($this->due_date?->toDateString());
    }
}
