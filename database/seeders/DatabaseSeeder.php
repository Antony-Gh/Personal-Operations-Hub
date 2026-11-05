<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\User;
use App\Models\UserSetting;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Guard: Never seed default demo credentials or mock dataset in production
        if (app()->environment('production')) {
            $this->command?->warn('DatabaseSeeder: Skipping demo user and mock data in production environment.');
            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'user@operations-hub.local'],
            [
                'name' => 'Anthony Gaius',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        UserSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => 'Anthony',
                'theme' => 'light',
                'language' => 'en',
                'week_start' => 1,
                'date_format' => 'YYYY-MM-DD',
                'time_format' => '24h',
                'default_priority' => 'medium',
                'default_task_duration' => 45,
                'phone_usage_target_minutes' => 60,
                'weekly_review_prompts' => UserSetting::defaultPrompts(),
            ]
        );

        $initialCategories = [
            ['name' => 'University', 'color' => '#3b82f6', 'icon' => 'GraduationCap', 'sort_order' => 1],
            ['name' => 'Team - Software', 'color' => '#8b5cf6', 'icon' => 'Code', 'sort_order' => 2],
            ['name' => 'Team - Academic Electrical', 'color' => '#06b6d4', 'icon' => 'Zap', 'sort_order' => 3],
            ['name' => 'Project - ROV', 'color' => '#10b981', 'icon' => 'Compass', 'sort_order' => 4],
            ['name' => 'Project - Minesweeper', 'color' => '#f59e0b', 'icon' => 'Crosshair', 'sort_order' => 5],
            ['name' => 'Project - UGV', 'color' => '#ec4899', 'icon' => 'Bot', 'sort_order' => 6],
            ['name' => 'Company - Full-Stack', 'color' => '#6366f1', 'icon' => 'Briefcase', 'sort_order' => 7],
            ['name' => 'Spiritual Life', 'color' => '#84cc16', 'icon' => 'Heart', 'sort_order' => 8],
            ['name' => 'Personal / Admin', 'color' => '#64748b', 'icon' => 'User', 'sort_order' => 9],
        ];

        $categoryMap = [];
        foreach ($initialCategories as $catData) {
            $cat = Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $catData['name']],
                $catData
            );
            $categoryMap[$catData['name']] = $cat;
        }

        // Tags
        $tagsData = [
            ['name' => 'Urgent', 'color' => '#ef4444'],
            ['name' => 'Research', 'color' => '#3b82f6'],
            ['name' => 'Engineering', 'color' => '#10b981'],
            ['name' => 'Leadership', 'color' => '#8b5cf6'],
            ['name' => 'Deep Work', 'color' => '#f59e0b'],
        ];

        $tagMap = [];
        foreach ($tagsData as $tData) {
            $tag = Tag::firstOrCreate(
                ['user_id' => $user->id, 'name' => $tData['name']],
                $tData
            );
            $tagMap[$tData['name']] = $tag;
        }

        // Projects
        $rovProject = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'ROV'],
            [
                'category_id' => $categoryMap['Project - ROV']->id,
                'description' => 'Underwater remotely operated vehicle telemetry and camera stabilization system.',
                'status' => 'in_progress',
                'start_date' => Carbon::today()->subMonths(2),
                'target_date' => Carbon::today()->addMonths(2),
                'notes' => 'Ensure waterproof testing protocols are verified with the hardware sub-team.',
            ]
        );

        ProjectMilestone::firstOrCreate(
            ['project_id' => $rovProject->id, 'title' => 'Chassis & Thruster Assembly'],
            ['due_date' => Carbon::today()->subDays(10), 'is_completed' => true, 'completed_at' => now()->subDays(10), 'sort_order' => 1]
        );
        ProjectMilestone::firstOrCreate(
            ['project_id' => $rovProject->id, 'title' => 'Sensory Telemetry Feed via Serial/CAN'],
            ['due_date' => Carbon::today()->addDays(12), 'is_completed' => false, 'sort_order' => 2]
        );
        ProjectMilestone::firstOrCreate(
            ['project_id' => $rovProject->id, 'title' => 'Pool Validation & Pilot Controls Test'],
            ['due_date' => Carbon::today()->addDays(35), 'is_completed' => false, 'sort_order' => 3]
        );

        $minesweeperProject = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Minesweeper Robot'],
            [
                'category_id' => $categoryMap['Project - Minesweeper']->id,
                'description' => 'Autonomous detection and surface mapping robot for outdoor field safety.',
                'status' => 'in_progress',
                'start_date' => Carbon::today()->subMonths(1),
                'target_date' => Carbon::today()->addMonths(3),
                'notes' => 'Focusing on sensor fusion and GPS coordinate accuracy.',
            ]
        );

        ProjectMilestone::firstOrCreate(
            ['project_id' => $minesweeperProject->id, 'title' => 'Metal Detector Integration'],
            ['due_date' => Carbon::today()->addDays(15), 'is_completed' => false, 'sort_order' => 1]
        );

        $ugvProject = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'UGV Autonomous Navigation'],
            [
                'category_id' => $categoryMap['Project - UGV']->id,
                'description' => 'Unmanned Ground Vehicle path-planning with ROS2 and LIDAR obstacle avoidance.',
                'status' => 'planned',
                'start_date' => Carbon::today()->addDays(5),
                'target_date' => Carbon::today()->addMonths(4),
                'notes' => 'Awaiting NVIDIA Jetson board delivery.',
            ]
        );

        // Tasks
        $today = Carbon::today();

        $tasks = [
            [
                'title' => 'Finalize ROV depth sensor calibration',
                'description' => 'Calibrate the analog pressure sensor against freshwater reference table.',
                'category_id' => $categoryMap['Project - ROV']->id,
                'project_id' => $rovProject->id,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => $today->toDateString(),
                'estimated_duration' => 60,
                'next_action' => 'Check zero-offset in firmware',
                'tags' => ['Urgent', 'Engineering'],
                'checklists' => [
                    ['title' => 'Connect oscilloscope to SPI lines', 'is_completed' => true],
                    ['title' => 'Measure atmospheric reference voltage', 'is_completed' => true],
                    ['title' => 'Write calibration constant to EEPROM', 'is_completed' => false],
                ],
            ],
            [
                'title' => 'Review software team pull requests',
                'description' => 'Review the WebSockets gateway and telemetry parser PRs.',
                'category_id' => $categoryMap['Team - Software']->id,
                'priority' => 'high',
                'status' => 'not_started',
                'due_date' => $today->toDateString(),
                'estimated_duration' => 45,
                'next_action' => 'Open GitHub repository reviews',
                'tags' => ['Leadership', 'Deep Work'],
                'checklists' => [
                    ['title' => 'Check test coverage on telemetry router', 'is_completed' => false],
                    ['title' => 'Provide constructive feedback on architectural choices', 'is_completed' => false],
                ],
            ],
            [
                'title' => 'Submit University Signals & Systems assignment',
                'description' => 'Complete problems 4 to 7 on Laplace transforms and transfer functions.',
                'category_id' => $categoryMap['University']->id,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => $today->toDateString(),
                'estimated_duration' => 90,
                'next_action' => 'Plot Bode diagram in Python/MATLAB',
                'tags' => ['Urgent'],
            ],
            [
                'title' => 'Prepare electrical schematic for Minesweeper power board',
                'description' => 'Design the 24V to 5V step-down buck converter circuit with heatsink.',
                'category_id' => $categoryMap['Project - Minesweeper']->id,
                'project_id' => $minesweeperProject->id,
                'priority' => 'medium',
                'status' => 'not_started',
                'due_date' => $today->copy()->addDays(2)->toDateString(),
                'estimated_duration' => 120,
                'next_action' => 'Open KiCad power regulation sheet',
                'tags' => ['Engineering'],
            ],
            [
                'title' => 'Company: Refactor authentication middleware session checks',
                'description' => 'Ensure single sign-on tokens invalidate correctly upon timeout.',
                'category_id' => $categoryMap['Company - Full-Stack']->id,
                'priority' => 'medium',
                'status' => 'in_progress',
                'due_date' => $today->copy()->addDays(3)->toDateString(),
                'estimated_duration' => 90,
                'next_action' => 'Write test case for expired cookie',
                'tags' => ['Deep Work'],
            ],
            [
                'title' => 'Review electrical team lab schedule',
                'description' => 'Coordinate bench access for solder fume extractors with team leads.',
                'category_id' => $categoryMap['Team - Academic Electrical']->id,
                'priority' => 'low',
                'status' => 'not_started',
                'due_date' => $today->copy()->addDays(5)->toDateString(),
                'estimated_duration' => 30,
                'tags' => ['Leadership'],
            ],
            [
                'title' => 'Weekly apartment groceries & admin receipt filing',
                'description' => 'Stock up on healthy meal prep ingredients and record weekly expenses.',
                'category_id' => $categoryMap['Personal / Admin']->id,
                'priority' => 'low',
                'status' => 'completed',
                'due_date' => $today->copy()->subDay()->toDateString(),
                'completed_at' => now()->subDay(),
                'estimated_duration' => 40,
                'actual_duration' => 35,
            ],
        ];

        foreach ($tasks as $taskData) {
            $checklists = $taskData['checklists'] ?? [];
            $tagNames = $taskData['tags'] ?? [];
            unset($taskData['checklists'], $taskData['tags']);

            $task = Task::create(array_merge(['user_id' => $user->id], $taskData));

            foreach ($checklists as $idx => $cl) {
                TaskChecklist::create([
                    'task_id' => $task->id,
                    'title' => $cl['title'],
                    'is_completed' => $cl['is_completed'] ?? false,
                    'sort_order' => $idx + 1,
                ]);
            }

            if (!empty($tagNames)) {
                $tagIds = collect($tagNames)->map(fn($tn) => $tagMap[$tn]->id ?? null)->filter();
                $task->tags()->sync($tagIds);
            }
        }

        // Habits
        $habits = [
            ['name' => 'Prayer', 'description' => 'Morning and evening prayer for gratitude and guidance.', 'category' => 'Spiritual Life', 'frequency' => 'daily', 'target_days_per_week' => 7, 'sort_order' => 1],
            ['name' => 'Quiet Time', 'description' => 'Silent reflection, meditation, and mental decompression.', 'category' => 'Spiritual Life', 'frequency' => 'daily', 'target_days_per_week' => 7, 'sort_order' => 2],
            ['name' => 'Bible Reading', 'description' => 'Reading scripture chapters and meditating on key verses.', 'category' => 'Spiritual Life', 'frequency' => 'daily', 'target_days_per_week' => 7, 'sort_order' => 3],
            ['name' => 'Physical Exercise / Walk', 'description' => '30 minutes outdoors or gym workout for recovery.', 'category' => 'Health', 'frequency' => 'daily', 'target_days_per_week' => 5, 'sort_order' => 4],
        ];

        foreach ($habits as $hData) {
            $habit = Habit::firstOrCreate(
                ['user_id' => $user->id, 'name' => $hData['name']],
                $hData
            );

            // Record check-in for today
            HabitCheckIn::updateOrCreate(
                ['habit_id' => $habit->id, 'user_id' => $user->id, 'date' => $today->toDateString()],
                [
                    'status' => $hData['name'] === 'Prayer' ? 'completed' : ($hData['name'] === 'Quiet Time' ? 'completed' : 'partial'),
                    'notes' => $hData['name'] === 'Bible Reading' ? 'Read 2 chapters of Proverbs' : null,
                ]
            );
        }

        // Calendar Events
        CalendarEvent::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Software Team Sync'],
            [
                'category_id' => $categoryMap['Team - Software']->id,
                'description' => 'Sprint standup and blocker resolution across sub-teams.',
                'start_time' => $today->copy()->setTime(14, 0),
                'end_time' => $today->copy()->setTime(15, 0),
                'location' => 'Lab Room 302 & Google Meet',
                'color' => '#8b5cf6',
            ]
        );

        CalendarEvent::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Signals & Systems Lecture'],
            [
                'category_id' => $categoryMap['University']->id,
                'start_time' => $today->copy()->setTime(10, 0),
                'end_time' => $today->copy()->setTime(12, 0),
                'location' => 'Hall 4',
                'color' => '#3b82f6',
            ]
        );

        // Phone Usage Log
        PhoneUsageLog::updateOrCreate(
            ['user_id' => $user->id, 'date' => $today->toDateString()],
            ['minutes' => 45, 'notes' => 'Evening messaging and news browsing.']
        );
    }
}
