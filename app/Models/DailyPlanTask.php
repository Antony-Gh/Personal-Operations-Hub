<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_id',
        'task_id',
        'is_priority',
        'sort_order',
        'scheduled_start_time',
        'scheduled_end_time',
    ];

    protected function casts(): array
    {
        return [
            'is_priority' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
