<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'project_id',
        'title',
        'description',
        'priority', // high, medium, low
        'status', // not_started, in_progress, waiting, completed, deferred
        'due_date',
        'start_date',
        'estimated_duration',
        'actual_duration',
        'notes',
        'next_action',
        'sort_order',
        'is_recurring',
        'recurrence_rule',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'start_date' => 'date',
            'estimated_duration' => 'integer',
            'actual_duration' => 'integer',
            'sort_order' => 'integer',
            'is_recurring' => 'boolean',
            'completed_at' => 'datetime',
        ];
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

    public function dailyPlanTasks(): HasMany
    {
        return $this->hasMany(DailyPlanTask::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'completed');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->where('due_date', Carbon::today()->toDateString());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', Carbon::today()->toDateString())
            ->where('status', '!=', 'completed');
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        $today = Carbon::today()->toDateString();
        $future = Carbon::today()->addDays($days)->toDateString();

        return $query->whereBetween('due_date', [$today, $future])
            ->where('status', '!=', 'completed');
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
    }
}
