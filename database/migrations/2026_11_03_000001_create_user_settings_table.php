<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('display_name')->nullable();
            $table->string('theme')->default('light'); // light, dark, system
            $table->string('language')->default('en');
            $table->unsignedTinyInteger('week_start')->default(1); // 0=Sunday, 1=Monday
            $table->string('date_format')->default('YYYY-MM-DD');
            $table->string('time_format')->default('24h'); // 12h, 24h
            $table->string('default_priority')->default('medium'); // high, medium, low
            $table->unsignedInteger('default_task_duration')->default(30); // in minutes
            $table->unsignedInteger('phone_usage_target_minutes')->default(60);
            $table->json('weekly_review_prompts')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};

