<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\NaturalLanguageTaskParser;
use Illuminate\Console\Command;

class OpsQuickAddCommand extends Command
{
    protected $signature = 'ops:quick-add {input : Natural-language task shorthand} {--user= : Optional user ID}';

    protected $description = 'Capture a new task from the CLI or Windows System Tray using natural-language shorthand';

    public function handle(NaturalLanguageTaskParser $parser): int
    {
        $userId = $this->option('user');
        $user = $userId
            ? User::with('setting')->find($userId)
            : User::with('setting')->first();

        if (! $user) {
            $this->error('No local user found to assign the task.');

            return self::FAILURE;
        }

        $rawInput = trim((string) $this->argument('input'));
        if ($rawInput === '') {
            $this->error('Task text cannot be empty.');

            return self::FAILURE;
        }

        $parsed = $parser->parse($rawInput, $user);
        $setting = $user->ensureSetting();
        $defaultPreset = $setting->default_task_reminder_preset ?: 'none';

        $task = Task::create([
            'user_id' => $user->id,
            'title' => $parsed['title'],
            'priority' => $parsed['priority'] ?? ($setting->default_priority ?: 'medium'),
            'status' => 'not_started',
            'category_id' => $parsed['category_id'],
            'project_id' => $parsed['project_id'],
            'due_date' => $parsed['due_date'] ?? $user->localTodayDate(),
            'due_time' => $parsed['due_time'],
            'estimated_duration' => $parsed['estimated_duration'] ?? ($setting->default_task_duration ?: 30),
            'reminder_preset' => $defaultPreset,
        ]);

        $this->info("Created task #{$task->id}: {$task->title} [{$task->priority}]");

        return self::SUCCESS;
    }
}
