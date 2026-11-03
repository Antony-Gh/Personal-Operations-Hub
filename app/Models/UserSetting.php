<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'theme',
        'language',
        'week_start',
        'date_format',
        'time_format',
        'default_priority',
        'default_task_duration',
        'phone_usage_target_minutes',
        'weekly_review_prompts',
    ];

    protected function casts(): array
    {
        return [
            'weekly_review_prompts' => 'array',
            'week_start' => 'integer',
            'default_task_duration' => 'integer',
            'phone_usage_target_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultPrompts(): array
    {
        return [
            'What are the three most meaningful things I accomplished?',
            'What was delayed, and why?',
            'What distracted me the most?',
            'Was my workload realistic?',
            'What can I delegate or postpone?',
            'What are the most important upcoming deadlines?',
            'What should I protect next week?',
            'What helped me recover energy?',
        ];
    }
}
