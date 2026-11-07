<?php

namespace App\Models;

use App\Services\ReminderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'description',
        'status',
        'start_date',
        'target_date',
        'reminders_enabled',
        'reminder_days',
        'overdue_reminders_enabled',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'target_date' => 'date:Y-m-d',
            'reminders_enabled' => 'boolean',
            'reminder_days' => 'array',
            'overdue_reminders_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Project $project): void {
            app(ReminderService::class)->syncProjectReminders($project);
        });

        static::deleted(function (Project $project): void {
            app(ReminderService::class)->cancelPendingRemindersFor($project);
            foreach ($project->milestones as $milestone) {
                app(ReminderService::class)->cancelPendingRemindersFor($milestone);
            }
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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'archived']);
    }
}
