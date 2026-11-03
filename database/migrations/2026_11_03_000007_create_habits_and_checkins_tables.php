<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('Spiritual Life');
            $table->string('frequency')->default('daily'); // daily, weekly
            $table->unsignedTinyInteger('target_days_per_week')->default(7);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_archived', 'sort_order']);
        });

        Schema::create('habit_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status'); // completed, partial, not_completed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['habit_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_check_ins');
        Schema::dropIfExists('habits');
    }
};

