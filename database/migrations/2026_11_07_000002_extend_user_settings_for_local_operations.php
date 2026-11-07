<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC')->after('language');
            $table->boolean('quiet_hours_enabled')->default(false)->after('timezone');
            $table->string('quiet_hours_start', 5)->default('22:00')->after('quiet_hours_enabled');
            $table->string('quiet_hours_end', 5)->default('07:00')->after('quiet_hours_start');
            $table->boolean('allow_critical_in_quiet_hours')->default(true)->after('quiet_hours_end');
            $table->boolean('notifications_enabled')->default(true)->after('allow_critical_in_quiet_hours');
            $table->boolean('browser_notifications_enabled')->default(false)->after('notifications_enabled');
            $table->boolean('notification_sound_enabled')->default(true)->after('browser_notifications_enabled');
            $table->string('notification_sound', 30)->default('chime')->after('notification_sound_enabled');
            $table->unsignedTinyInteger('notification_volume')->default(70)->after('notification_sound');
            $table->json('notification_preferences')->nullable()->after('notification_volume');
            $table->boolean('daily_briefing_enabled')->default(true)->after('notification_preferences');
            $table->string('daily_briefing_time', 5)->default('07:30')->after('daily_briefing_enabled');
            $table->timestamp('last_daily_briefing_at')->nullable()->after('daily_briefing_time');
            $table->boolean('weekly_digest_enabled')->default(true)->after('last_daily_briefing_at');
            $table->unsignedTinyInteger('weekly_digest_day')->default(0)->after('weekly_digest_enabled');
            $table->string('weekly_digest_time', 5)->default('18:00')->after('weekly_digest_day');
            $table->timestamp('last_weekly_digest_at')->nullable()->after('weekly_digest_time');
            $table->string('default_task_reminder_preset', 30)->default('none')->after('last_weekly_digest_at');
            $table->json('project_reminder_days')->nullable()->after('default_task_reminder_preset');
            $table->boolean('backup_enabled')->default(true)->after('project_reminder_days');
            $table->unsignedInteger('backup_retention_days')->default(14)->after('backup_enabled');
            $table->unsignedInteger('focus_work_minutes')->default(25)->after('backup_retention_days');
            $table->unsignedInteger('focus_short_break_minutes')->default(5)->after('focus_work_minutes');
            $table->unsignedInteger('focus_long_break_minutes')->default(15)->after('focus_short_break_minutes');
            $table->boolean('focus_auto_start_breaks')->default(false)->after('focus_long_break_minutes');
            $table->boolean('calendar_feed_enabled')->default(true)->after('focus_auto_start_breaks');
            $table->string('calendar_feed_token_hash', 64)->nullable()->index()->after('calendar_feed_enabled');
            $table->text('calendar_feed_token_encrypted')->nullable()->after('calendar_feed_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropIndex(['calendar_feed_token_hash']);
            $table->dropColumn([
                'timezone',
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
            ]);
        });
    }
};
