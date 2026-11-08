<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->boolean('windows_toast_enabled')->default(false);
            $table->boolean('evening_checkin_nudge_enabled')->default(true);
            $table->string('evening_checkin_nudge_time', 10)->default('21:00');
            $table->timestamp('last_evening_nudge_at')->nullable();
            $table->boolean('secondary_backup_enabled')->default(false);
            $table->string('secondary_backup_path', 500)->nullable();
            $table->boolean('backup_encryption_enabled')->default(false);
            $table->text('backup_encryption_password_encrypted')->nullable();
        });

        Schema::table('bad_habits', function (Blueprint $table): void {
            $table->foreignId('linked_habit_id')
                ->nullable()
                ->constrained('habits')
                ->nullOnDelete();
            $table->unsignedInteger('minutes_saved_per_day')->default(0);
            $table->decimal('money_saved_per_day', 10, 2)->default(0);
        });

        Schema::table('daily_plan_tasks', function (Blueprint $table): void {
            $table->string('scheduled_start_time', 8)->nullable();
            $table->string('scheduled_end_time', 8)->nullable();
        });

        Schema::create('task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
            $table->index('depends_on_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');

        Schema::table('daily_plan_tasks', function (Blueprint $table): void {
            $table->dropColumn(['scheduled_start_time', 'scheduled_end_time']);
        });

        Schema::table('bad_habits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('linked_habit_id');
            $table->dropColumn(['minutes_saved_per_day', 'money_saved_per_day']);
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'windows_toast_enabled',
                'evening_checkin_nudge_enabled',
                'evening_checkin_nudge_time',
                'last_evening_nudge_at',
                'secondary_backup_enabled',
                'secondary_backup_path',
                'backup_encryption_enabled',
                'backup_encryption_password_encrypted',
            ]);
        });
    }
};
