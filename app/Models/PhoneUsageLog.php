<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
