<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bad_habits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('Digital Discipline');
            $table->string('trigger_cue')->nullable();
            $table->string('replacement_action')->nullable();
            $table->boolean('has_daily_limit')->default(false);
            $table->unsignedInteger('daily_limit_value')->nullable();
            $table->string('daily_limit_unit', 20)->default('times');
            $table->unsignedTinyInteger('target_clean_days_per_week')->default(7);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_archived', 'sort_order']);
        });

        Schema::create('bad_habit_check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bad_habit_id')->constrained('bad_habits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 30); // clean, minor_slip, slipped
            $table->unsignedInteger('observed_value')->nullable();
            $table->string('trigger_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bad_habit_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bad_habit_check_ins');
        Schema::dropIfExists('bad_habits');
    }
};

