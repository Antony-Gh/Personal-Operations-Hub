<?php

use App\Models\BadHabit;
use App\Models\BadHabitCheckIn;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\FocusSession;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Http\UploadedFile;

it('exports data as json stream', function () {
    $user = User::factory()->create();
    Category::create(['user_id' => $user->id, 'name' => 'University']);

    $response = $this->actingAs($user)->get(route('settings.export.json'));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/json');
});

it('exports tasks as csv spreadsheet', function () {
    $user = User::factory()->create();
    Task::create(['user_id' => $user->id, 'title' => 'Exportable Task', 'priority' => 'medium', 'status' => 'not_started']);

    $response = $this->actingAs($user)->get(route('settings.export.tasks-csv'));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('previews and validates import backup json file', function () {
    $user = User::factory()->create();

    $payload = [
        'version' => '2.0',
        'exported_at' => now()->toIso8601String(),
        'categories' => [['id' => 1, 'name' => 'Imported Area']],
        'projects' => [['id' => 2, 'name' => 'Imported Project']],
        'tasks' => [['id' => 10, 'title' => 'Imported Task', 'priority' => 'high']],
        'habits' => [['id' => 20, 'name' => 'Daily Meditation']],
        'calendar_events' => [['id' => 30, 'title' => 'Dentist', 'start_time' => '2026-11-20T10:00:00Z']],
        'daily_plans' => [['id' => 40, 'date' => '2026-11-20']],
        'weekly_reviews' => [['id' => 50, 'week_start_date' => '2026-11-16']],
    ];

    $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($payload));

    $response = $this->actingAs($user)->post(route('settings.import.preview'), [
        'backup_file' => $file,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'valid' => true,
        'summary' => [
            'version' => '2.0',
            'categories_count' => 1,
            'projects_count' => 1,
            'tasks_count' => 1,
            'habits_count' => 1,
            'calendar_events_count' => 1,
            'daily_plans_count' => 1,
            'weekly_reviews_count' => 1,
        ],
    ]);
});

it('restores all collections and nested relationships in replace mode with full data equivalence', function () {
    $user1 = User::factory()->create();
    $user1->ensureSetting()->update(['display_name' => 'Source User', 'timezone' => 'America/New_York']);

    // Setup full domain dataset for user1
    $cat = Category::create(['user_id' => $user1->id, 'name' => 'Academics', 'color' => '#3b82f6', 'icon' => 'Book']);
    $tag = Tag::create(['user_id' => $user1->id, 'name' => 'Exam', 'color' => '#ef4444']);
    $project = Project::create(['user_id' => $user1->id, 'category_id' => $cat->id, 'name' => 'Thesis Research', 'status' => 'in_progress']);
    $milestone = ProjectMilestone::create(['project_id' => $project->id, 'title' => 'Chapter 1 Draft', 'due_date' => '2026-12-01', 'sort_order' => 1]);

    $task = Task::create([
        'user_id' => $user1->id,
        'category_id' => $cat->id,
        'project_id' => $project->id,
        'title' => 'Write Literature Review',
        'priority' => 'high',
        'status' => 'in_progress',
        'due_date' => '2026-11-25',
    ]);
    $task->tags()->attach($tag->id);
    TaskChecklist::create(['task_id' => $task->id, 'title' => 'Outline sections', 'is_completed' => true, 'sort_order' => 1]);

    $habit = Habit::create(['user_id' => $user1->id, 'name' => 'Daily Reading', 'category' => 'Intellectual', 'frequency' => 'daily', 'target_days_per_week' => 7]);
    HabitCheckIn::create(['user_id' => $user1->id, 'habit_id' => $habit->id, 'date' => '2026-11-14', 'status' => 'completed', 'notes' => 'Read 30 pages']);

    $badHabit = BadHabit::create(['user_id' => $user1->id, 'name' => 'Late Night Doomscrolling', 'category' => 'Digital Discipline', 'replacement_action' => 'Read offline book']);
    BadHabitCheckIn::create(['user_id' => $user1->id, 'bad_habit_id' => $badHabit->id, 'date' => '2026-11-14', 'status' => 'clean']);

    CalendarEvent::create(['user_id' => $user1->id, 'category_id' => $cat->id, 'title' => 'Advisor Meeting', 'start_time' => '2026-11-18 14:00:00', 'end_time' => '2026-11-18 15:00:00']);

    $dailyPlan = DailyPlan::create(['user_id' => $user1->id, 'date' => '2026-11-14', 'fallback_plan' => 'Focus only on chapter 1']);
    DailyPlanTask::create(['daily_plan_id' => $dailyPlan->id, 'task_id' => $task->id, 'is_priority' => true, 'sort_order' => 1]);

    WeeklyReview::create(['user_id' => $user1->id, 'week_start_date' => '2026-11-09', 'week_end_date' => '2026-11-15', 'responses' => ['Big Win' => 'Submitted abstract'], 'is_completed' => true]);

    PhoneUsageLog::create(['user_id' => $user1->id, 'date' => '2026-11-14', 'minutes' => 45, 'notes' => 'Clean usage']);

    FocusSession::create(['user_id' => $user1->id, 'task_id' => $task->id, 'mode' => 'focus', 'status' => 'completed', 'started_at' => '2026-11-14 10:00:00', 'elapsed_seconds' => 1500, 'planned_minutes' => 25]);

    // Export payload stream
    $exportResponse = $this->actingAs($user1)->get(route('settings.export.json'));
    $exportContent = $exportResponse->streamedContent();
    $payload = json_decode($exportContent, true);

    expect($payload['version'])->toBe('2.0')
        ->and($payload['categories'])->toHaveCount(1)
        ->and($payload['habits'])->toHaveCount(1)
        ->and($payload['daily_plans'])->toHaveCount(1)
        ->and($payload['weekly_reviews'])->toHaveCount(1);

    // Target user: User 2 starts with pre-existing records that will be replaced
    $user2 = User::factory()->create();
    Category::create(['user_id' => $user2->id, 'name' => 'Old Obsolete Area']);
    Habit::create(['user_id' => $user2->id, 'name' => 'Old Habit']);

    $importResponse = $this->actingAs($user2)->post(route('settings.import.process'), [
        'payload' => $payload,
        'mode' => 'replace',
    ]);

    $importResponse->assertSessionHas('success');

    // User2 obsolete records purged
    expect(Category::where('user_id', $user2->id)->where('name', 'Old Obsolete Area')->exists())->toBeFalse()
        ->and(Habit::where('user_id', $user2->id)->where('name', 'Old Habit')->exists())->toBeFalse();

    // User2 received full restored dataset
    expect(Category::where('user_id', $user2->id)->where('name', 'Academics')->exists())->toBeTrue()
        ->and(Tag::where('user_id', $user2->id)->where('name', 'Exam')->exists())->toBeTrue()
        ->and(Project::where('user_id', $user2->id)->where('name', 'Thesis Research')->exists())->toBeTrue();

    $restoredProject = Project::where('user_id', $user2->id)->where('name', 'Thesis Research')->first();
    expect($restoredProject->milestones)->toHaveCount(1)
        ->and($restoredProject->milestones->first()->title)->toBe('Chapter 1 Draft')
        ->and($restoredProject->category->name)->toBe('Academics');

    $restoredTask = Task::where('user_id', $user2->id)->where('title', 'Write Literature Review')->first();
    expect($restoredTask)->not->toBeNull()
        ->and($restoredTask->project_id)->toBe($restoredProject->id)
        ->and($restoredTask->category_id)->toBe($restoredProject->category_id)
        ->and($restoredTask->checklists)->toHaveCount(1)
        ->and($restoredTask->checklists->first()->title)->toBe('Outline sections')
        ->and($restoredTask->tags->pluck('name')->all())->toContain('Exam');

    $restoredHabit = Habit::where('user_id', $user2->id)->where('name', 'Daily Reading')->first();
    expect($restoredHabit)->not->toBeNull()
        ->and($restoredHabit->checkIns)->toHaveCount(1)
        ->and($restoredHabit->checkIns->first()->status)->toBe('completed')
        ->and($restoredHabit->checkIns->first()->notes)->toBe('Read 30 pages');

    $restoredBadHabit = BadHabit::where('user_id', $user2->id)->where('name', 'Late Night Doomscrolling')->first();
    expect($restoredBadHabit)->not->toBeNull()
        ->and($restoredBadHabit->checkIns)->toHaveCount(1)
        ->and($restoredBadHabit->checkIns->first()->status)->toBe('clean');

    expect(CalendarEvent::where('user_id', $user2->id)->where('title', 'Advisor Meeting')->exists())->toBeTrue();

    $restoredDailyPlan = DailyPlan::where('user_id', $user2->id)->where('date', '2026-11-14')->first();
    expect($restoredDailyPlan)->not->toBeNull()
        ->and($restoredDailyPlan->fallback_plan)->toBe('Focus only on chapter 1')
        ->and($restoredDailyPlan->planTasks)->toHaveCount(1)
        ->and($restoredDailyPlan->planTasks->first()->task_id)->toBe($restoredTask->id)
        ->and($restoredDailyPlan->planTasks->first()->is_priority)->toBeTrue();

    $restoredReview = WeeklyReview::where('user_id', $user2->id)->whereDate('week_start_date', '2026-11-09')->first();
    expect($restoredReview)->not->toBeNull()
        ->and($restoredReview->responses)->toBe(['Big Win' => 'Submitted abstract'])
        ->and($restoredReview->is_completed)->toBeTrue();

    expect(PhoneUsageLog::where('user_id', $user2->id)->where('date', '2026-11-14')->first()?->minutes)->toBe(45);

    $restoredFocus = FocusSession::where('user_id', $user2->id)->first();
    expect($restoredFocus)->not->toBeNull()
        ->and($restoredFocus->task_id)->toBe($restoredTask->id)
        ->and($restoredFocus->elapsed_seconds)->toBe(1500);
});

it('merges new collections without overwriting unconflicted records or duplicating existing items', function () {
    $user = User::factory()->create();

    // Pre-existing user items
    Category::create(['user_id' => $user->id, 'name' => 'Existing Area']);
    $existingTask = Task::create(['user_id' => $user->id, 'title' => 'Existing Task', 'priority' => 'medium', 'status' => 'not_started']);
    $existingHabit = Habit::create(['user_id' => $user->id, 'name' => 'Existing Habit', 'category' => 'Health', 'frequency' => 'daily', 'target_days_per_week' => 5]);

    $payload = [
        'version' => '2.0',
        'categories' => [
            ['name' => 'Existing Area'], // Duplicate: should not create a 2nd
            ['name' => 'Brand New Area'], // New: should create
        ],
        'tasks' => [
            ['title' => 'Existing Task', 'priority' => 'medium'], // Duplicate: should not duplicate
            ['title' => 'Brand New Task', 'priority' => 'high'], // New: should create
        ],
        'habits' => [
            ['name' => 'Existing Habit', 'category' => 'Health', 'frequency' => 'daily', 'target_days_per_week' => 5], // Duplicate
            ['name' => 'Brand New Habit', 'category' => 'Focus', 'frequency' => 'daily', 'target_days_per_week' => 7], // New
        ],
        'calendar_events' => [
            ['title' => 'New Calendar Event', 'start_time' => '2026-11-20 09:00:00'],
        ],
        'weekly_reviews' => [
            ['week_start_date' => '2026-11-02', 'week_end_date' => '2026-11-08', 'responses' => ['Reflection' => 'Good progress']],
        ],
    ];

    $response = $this->actingAs($user)->post(route('settings.import.process'), [
        'payload' => $payload,
        'mode' => 'merge',
    ]);

    $response->assertSessionHas('success');

    // Duplicate check: exactly 1 instance each
    expect(Category::where('user_id', $user->id)->where('name', 'Existing Area')->count())->toBe(1)
        ->and(Category::where('user_id', $user->id)->where('name', 'Brand New Area')->count())->toBe(1)
        ->and(Task::where('user_id', $user->id)->where('title', 'Existing Task')->count())->toBe(1)
        ->and(Task::where('user_id', $user->id)->where('title', 'Brand New Task')->count())->toBe(1)
        ->and(Habit::where('user_id', $user->id)->where('name', 'Existing Habit')->count())->toBe(1)
        ->and(Habit::where('user_id', $user->id)->where('name', 'Brand New Habit')->count())->toBe(1)
        ->and(CalendarEvent::where('user_id', $user->id)->where('title', 'New Calendar Event')->count())->toBe(1)
        ->and(WeeklyReview::where('user_id', $user->id)->whereDate('week_start_date', '2026-11-02')->count())->toBe(1);
});

it('rejects malformed payloads lacking version or having non-array collections safely', function () {
    $user = User::factory()->create();

    // Missing version
    $res1 = $this->actingAs($user)->post(route('settings.import.process'), [
        'payload' => ['categories' => []],
        'mode' => 'merge',
    ]);
    $res1->assertSessionHasErrors('payload');

    // Non-array collection
    $res2 = $this->actingAs($user)->post(route('settings.import.process'), [
        'payload' => ['version' => '2.0', 'tasks' => 'string_instead_of_array'],
        'mode' => 'merge',
    ]);
    $res2->assertSessionHasErrors('payload');
});

it('safely handles missing or invalid relationship foreign keys without failure', function () {
    $user = User::factory()->create();

    $payload = [
        'version' => '2.0',
        'tasks' => [
            [
                'title' => 'Task with invalid category',
                'category_id' => 999999, // Non-existent foreign key
                'project_id' => 888888,  // Non-existent foreign key
            ],
        ],
        'daily_plans' => [
            [
                'date' => '2026-11-14',
                'plan_tasks' => [
                    ['task_id' => 777777], // Non-existent task ID
                ],
            ],
        ],
    ];

    $response = $this->actingAs($user)->post(route('settings.import.process'), [
        'payload' => $payload,
        'mode' => 'merge',
    ]);

    $response->assertSessionHas('success');

    $task = Task::where('user_id', $user->id)->where('title', 'Task with invalid category')->first();
    expect($task)->not->toBeNull()
        ->and($task->category_id)->toBeNull()
        ->and($task->project_id)->toBeNull();

    $dailyPlan = DailyPlan::where('user_id', $user->id)->where('date', '2026-11-14')->first();
    expect($dailyPlan)->not->toBeNull()
        ->and($dailyPlan->planTasks)->toHaveCount(0);
});

it('enforces cross-user isolation so imported records can never belong to another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $catA = Category::create(['user_id' => $userA->id, 'name' => 'User A Secret Area']);

    // User B attempts to import payload asserting ownership of user A's category ID
    $payload = [
        'version' => '2.0',
        'categories' => [
            ['id' => $catA->id, 'name' => 'User B Injected Area'],
        ],
        'tasks' => [
            ['title' => 'User B Task', 'category_id' => $catA->id],
        ],
    ];

    $this->actingAs($userB)->post(route('settings.import.process'), [
        'payload' => $payload,
        'mode' => 'merge',
    ]);

    // User A's category is unchanged
    expect($catA->fresh()->name)->toBe('User A Secret Area')
        ->and($catA->fresh()->user_id)->toBe($userA->id);

    // User B's task belongs strictly to User B and maps to User B's category
    $userBTask = Task::where('user_id', $userB->id)->where('title', 'User B Task')->first();
    expect($userBTask)->not->toBeNull()
        ->and($userBTask->category_id)->not->toBe($catA->id);
});

it('rolls back completely if a fatal error occurs during replace mode import', function () {
    $user = User::factory()->create();
    Category::create(['user_id' => $user->id, 'name' => 'Original Preserved Area']);
    Task::create(['user_id' => $user->id, 'title' => 'Original Preserved Task', 'priority' => 'medium', 'status' => 'not_started']);

    // Construct a payload that triggers an exception during transaction
    // Passing invalid type where string is required in DB schema
    $payload = [
        'version' => '2.0',
        'categories' => [
            ['name' => 'New Area'],
        ],
        'tasks' => [
            ['title' => null], // Violates NOT NULL constraint on tasks.title
        ],
    ];

    $response = $this->actingAs($user)->post(route('settings.import.process'), [
        'payload' => $payload,
        'mode' => 'replace',
    ]);

    $response->assertSessionHas('error');

    // Original records were preserved by transaction rollback
    expect(Category::where('user_id', $user->id)->where('name', 'Original Preserved Area')->exists())->toBeTrue()
        ->and(Task::where('user_id', $user->id)->where('title', 'Original Preserved Task')->exists())->toBeTrue()
        ->and(Category::where('user_id', $user->id)->where('name', 'New Area')->exists())->toBeFalse();
});

it('imports legacy version 1.0 payloads without errors', function () {
    $user = User::factory()->create();

    $legacyPayload = [
        'version' => '1.0',
        'exported_at' => '2026-01-01T00:00:00Z',
        'categories' => [['name' => 'Legacy Area']],
        'tasks' => [['title' => 'Legacy Task', 'priority' => 'low']],
        // Omits habits, calendar_events, reviews, daily_plans
    ];

    $response = $this->actingAs($user)->post(route('settings.import.process'), [
        'payload' => $legacyPayload,
        'mode' => 'merge',
    ]);

    $response->assertSessionHas('success');
    expect(Category::where('user_id', $user->id)->where('name', 'Legacy Area')->exists())->toBeTrue()
        ->and(Task::where('user_id', $user->id)->where('title', 'Legacy Task')->exists())->toBeTrue();
});
