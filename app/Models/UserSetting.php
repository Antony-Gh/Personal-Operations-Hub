<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'theme',
        'language',
        'timezone',
        'week_start',
        'date_format',
        'time_format',
        'default_priority',
        'default_task_duration',
        'phone_usage_target_minutes',
        'weekly_review_prompts',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'allow_critical_in_quiet_hours',
        'notifications_enabled',
        'browser_notifications_enabled',
        'notification_sound_enabled',
        'notification_sound',
        'notification_volume',
        'notification_preferences',
        'daily_briefing_enabled',
        'daily_briefing_time',
        'last_daily_briefing_at',
        'weekly_digest_enabled',
        'weekly_digest_day',
        'weekly_digest_time',
        'last_weekly_digest_at',
        'default_task_reminder_preset',
        'project_reminder_days',
        'backup_enabled',
        'backup_retention_days',
        'focus_work_minutes',
        'focus_short_break_minutes',
        'focus_long_break_minutes',
        'focus_auto_start_breaks',
        'calendar_feed_enabled',
        'calendar_feed_token_hash',
        'calendar_feed_token_encrypted',
        'windows_toast_enabled',
        'evening_checkin_nudge_enabled',
        'evening_checkin_nudge_time',
        'last_evening_nudge_at',
        'secondary_backup_enabled',
        'secondary_backup_path',
        'backup_encryption_enabled',
        'backup_encryption_password_encrypted',
    ];

    protected $hidden = [
        'calendar_feed_token_hash',
        'calendar_feed_token_encrypted',
        'backup_encryption_password_encrypted',
    ];

    protected $appends = [
        'has_calendar_feed_token',
        'has_backup_encryption_password',
    ];

    protected function casts(): array
    {
        return [
            'weekly_review_prompts' => 'array',
            'week_start' => 'integer',
            'default_task_duration' => 'integer',
            'phone_usage_target_minutes' => 'integer',
            'quiet_hours_enabled' => 'boolean',
            'allow_critical_in_quiet_hours' => 'boolean',
            'notifications_enabled' => 'boolean',
            'browser_notifications_enabled' => 'boolean',
            'notification_sound_enabled' => 'boolean',
            'notification_volume' => 'integer',
            'notification_preferences' => 'array',
            'daily_briefing_enabled' => 'boolean',
            'last_daily_briefing_at' => 'datetime',
            'weekly_digest_enabled' => 'boolean',
            'weekly_digest_day' => 'integer',
            'last_weekly_digest_at' => 'datetime',
            'project_reminder_days' => 'array',
            'backup_enabled' => 'boolean',
            'backup_retention_days' => 'integer',
            'focus_work_minutes' => 'integer',
            'focus_short_break_minutes' => 'integer',
            'focus_long_break_minutes' => 'integer',
            'focus_auto_start_breaks' => 'boolean',
            'calendar_feed_enabled' => 'boolean',
            'windows_toast_enabled' => 'boolean',
            'evening_checkin_nudge_enabled' => 'boolean',
            'last_evening_nudge_at' => 'datetime',
            'secondary_backup_enabled' => 'boolean',
            'backup_encryption_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getHasCalendarFeedTokenAttribute(): bool
    {
        return ! empty($this->calendar_feed_token_hash);
    }

    public function getHasBackupEncryptionPasswordAttribute(): bool
    {
        return ! empty($this->backup_encryption_password_encrypted);
    }

    public function decryptedBackupEncryptionPassword(): ?string
    {
        if (empty($this->backup_encryption_password_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->backup_encryption_password_encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
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

    /**
     * @return array<string, bool>
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'task_reminders' => true,
            'overdue_tasks' => true,
            'project_deadlines' => true,
            'milestone_deadlines' => true,
            'habit_reminders' => true,
            'focus_timer' => true,
            'daily_briefing' => true,
            'weekly_digest' => true,
            'system_notifications' => true,
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function defaultProjectReminderDays(): array
    {
        return [7, 3, 1, 0];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(?string $displayName = null): array
    {
        return [
            'display_name' => $displayName,
            'theme' => 'light',
            'language' => 'en',
            'timezone' => config('app.timezone', 'UTC'),
            'week_start' => 1,
            'date_format' => 'YYYY-MM-DD',
            'time_format' => '24h',
            'default_priority' => 'medium',
            'default_task_duration' => 30,
            'phone_usage_target_minutes' => 60,
            'weekly_review_prompts' => self::defaultPrompts(),
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
            'allow_critical_in_quiet_hours' => true,
            'notifications_enabled' => true,
            'browser_notifications_enabled' => false,
            'notification_sound_enabled' => true,
            'notification_sound' => 'chime',
            'notification_volume' => 70,
            'notification_preferences' => self::defaultNotificationPreferences(),
            'daily_briefing_enabled' => true,
            'daily_briefing_time' => '07:30',
            'weekly_digest_enabled' => true,
            'weekly_digest_day' => 0,
            'weekly_digest_time' => '18:00',
            'default_task_reminder_preset' => 'none',
            'project_reminder_days' => self::defaultProjectReminderDays(),
            'backup_enabled' => true,
            'backup_retention_days' => 14,
            'focus_work_minutes' => 25,
            'focus_short_break_minutes' => 5,
            'focus_long_break_minutes' => 15,
            'focus_auto_start_breaks' => false,
            'calendar_feed_enabled' => true,
            'windows_toast_enabled' => false,
            'evening_checkin_nudge_enabled' => true,
            'evening_checkin_nudge_time' => '21:00',
            'secondary_backup_enabled' => false,
            'secondary_backup_path' => null,
            'backup_encryption_enabled' => false,
        ];
    }

    public function isNotificationCategoryEnabled(string $category): bool
    {
        if (! $this->notifications_enabled) {
            return false;
        }

        $prefs = array_merge(
            self::defaultNotificationPreferences(),
            is_array($this->notification_preferences) ? $this->notification_preferences : []
        );

        return (bool) ($prefs[$category] ?? true);
    }

    public function decryptedCalendarFeedToken(): ?string
    {
        if (empty($this->calendar_feed_token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->calendar_feed_token_encrypted);
        } catch (DecryptException) {
            return null;
        }
    }
}
