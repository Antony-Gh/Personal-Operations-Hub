<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'fallback_plan',
        'personal_recovery_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function planTasks(): HasMany
    {
        return $this->hasMany(DailyPlanTask::class)->orderBy('sort_order');
    }
}

