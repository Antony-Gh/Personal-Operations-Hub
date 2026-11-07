<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('due_time', 5)->nullable()->after('due_date');
            $table->string('reminder_preset', 30)->nullable()->after('due_time');
            $table->dateTime('remind_at')->nullable()->after('reminder_preset');
            $table->json('custom_reminder_times')->nullable()->after('remind_at');
            $table->unsignedInteger('recurrence_interval')->default(1)->after('recurrence_rule');
            $table->string('recurrence_end_type', 20)->default('never')->after('recurrence_interval');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_end_type');
            $table->unsignedInteger('recurrence_max_occurrences')->nullable()->after('recurrence_end_date');
            $table->unsignedInteger('recurrence_occurrences_count')->default(0)->after('recurrence_max_occurrences');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_occurrences_count')->constrained('tasks')->nullOnDelete();
            $table->boolean('recurrence_generated')->default(false)->after('recurrence_parent_id');

            $table->index(['user_id', 'is_recurring', 'status']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->boolean('reminders_enabled')->default(true)->after('target_date');
            $table->json('reminder_days')->nullable()->after('reminders_enabled');
            $table->boolean('overdue_reminders_enabled')->default(true)->after('reminder_days');

            $table->index(['user_id', 'target_date']);
        });

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->string('due_time', 5)->nullable()->after('due_date');
            $table->boolean('reminders_enabled')->default(true)->after('due_time');
            $table->json('reminder_offsets')->nullable()->after('reminders_enabled');

            $table->index(['project_id', 'due_date']);
        });

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->unsignedInteger('reminder_minutes')->nullable()->default(15)->after('color');
        });

        Schema::create('reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable');
            $table->string('reminder_key', 100);
            $table->string('category', 40);
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('offset_label', 60)->nullable();
            $table->boolean('is_critical')->default(false);
            $table->dateTime('scheduled_at');
            $table->dateTime('snoozed_until')->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('dismissed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->boolean('suppressed_by_quiet_hours')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['remindable_type', 'remindable_id', 'reminder_key']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['status', 'snoozed_until']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('focus_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20)->default('focus');
            $table->unsignedInteger('planned_minutes')->default(25);
            $table->unsignedInteger('elapsed_seconds')->default(0);
            $table->unsignedInteger('logged_minutes')->default(0);
            $table->string('status', 20)->default('running');
            $table->dateTime('started_at');
            $table->dateTime('last_resumed_at')->nullable();
            $table->dateTime('paused_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_sessions');
        Schema::dropIfExists('reminders');

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn('reminder_minutes');
        });

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'due_date']);
            $table->dropColumn(['due_time', 'reminders_enabled', 'reminder_offsets']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'target_date']);
            $table->dropColumn(['reminders_enabled', 'reminder_days', 'overdue_reminders_enabled']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'is_recurring', 'status']);
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn([
                'due_time',
                'reminder_preset',
                'remind_at',
                'custom_reminder_times',
                'recurrence_interval',
                'recurrence_end_type',
                'recurrence_end_date',
                'recurrence_max_occurrences',
                'recurrence_occurrences_count',
                'recurrence_generated',
            ]);
        });
    }
};
