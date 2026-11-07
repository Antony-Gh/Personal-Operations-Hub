<?php

namespace App\Models;

use Database\Factories\BadHabitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadHabit extends Model
{
    /** @use HasFactory<BadHabitFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'trigger_cue',
        'replacement_action',
        'has_daily_limit',
        'daily_limit_value',
        'daily_limit_unit',
        'target_clean_days_per_week',
        'sort_order',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'has_daily_limit' => 'boolean',
            'daily_limit_value' => 'integer',
            'target_clean_days_per_week' => 'integer',
            'sort_order' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(BadHabitCheckIn::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }
}

