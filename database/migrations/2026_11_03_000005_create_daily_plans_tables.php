<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('fallback_plan')->nullable();
            $table->text('personal_recovery_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('daily_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_priority')->default(false); // up to 3 main priorities
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['daily_plan_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_tasks');
        Schema::dropIfExists('daily_plans');
    }
};

