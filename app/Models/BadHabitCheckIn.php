<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadHabitCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'bad_habit_id',
        'user_id',
        'date',
        'status',
        'observed_value',
        'trigger_note',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'observed_value' => 'integer',
        ];
    }

    public function badHabit(): BelongsTo
    {
        return $this->belongsTo(BadHabit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

