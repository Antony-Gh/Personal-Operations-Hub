<?php

namespace App\Models;

use App\Support\UserDateTime;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function setting(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function ensureSetting(): UserSetting
    {
        return UserSetting::firstOrCreate(
            ['user_id' => $this->id],
            UserSetting::defaultAttributes($this->name)
        );
    }

    public function timezone(): string
    {
        return UserDateTime::resolveTimezone($this);
    }

    public function localNow(): Carbon
    {
        return UserDateTime::nowForUser($this);
    }

    public function localToday(): Carbon
    {
        return UserDateTime::todayForUser($this);
    }

    public function localTodayDate(): string
    {
        return UserDateTime::todayDateString($this);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function dailyPlans(): HasMany
    {
        return $this->hasMany(DailyPlan::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    public function badHabits(): HasMany
    {
        return $this->hasMany(BadHabit::class);
    }

    public function weeklyReviews(): HasMany
    {
        return $this->hasMany(WeeklyReview::class);
    }

    public function phoneUsageLogs(): HasMany
    {
        return $this->hasMany(PhoneUsageLog::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function focusSessions(): HasMany
    {
        return $this->hasMany(FocusSession::class);
    }
}
