<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('#94a3b8');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // high, medium, low
            $table->string('status')->default('not_started'); // not_started, in_progress, waiting, completed, deferred
            $table->date('due_date')->nullable();
            $table->date('start_date')->nullable();
            $table->unsignedInteger('estimated_duration')->nullable(); // in minutes
            $table->unsignedInteger('actual_duration')->nullable(); // in minutes
            $table->text('notes')->nullable();
            $table->string('next_action')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule')->nullable(); // daily, weekly, monthly
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'due_date']);
            $table->index(['user_id', 'priority']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'project_id']);
        });

        Schema::create('task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'sort_order']);
        });

        Schema::create('task_tag', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_tag');
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('tags');
    }
};
