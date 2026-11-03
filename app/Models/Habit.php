<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'frequency',
        'target_days_per_week',
        'sort_order',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'target_days_per_week' => 'integer',
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
        return $this->hasMany(HabitCheckIn::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }
}

