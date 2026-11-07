<?php

use App\Models\Project;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('tasks index sorts priorities strictly high then medium then low instead of alphabetical order', function (): void {
    $user = User::factory()->create();

    Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Low Task',
        'priority' => 'low',
        'status' => 'not_started',
        'due_date' => '2026-11-10',
    ]);
    Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'High Task',
        'priority' => 'high',
        'status' => 'not_started',
        'due_date' => '2026-11-10',
    ]);
    Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Medium Task',
        'priority' => 'medium',
        'status' => 'not_started',
        'due_date' => '2026-11-10',
    ]);

    $this->actingAs($user)
        ->get('/tasks?sort_by=priority&sort_dir=asc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tasks/Index')
            ->where('tasks.data.0.priority', 'high')
            ->where('tasks.data.1.priority', 'medium')
            ->where('tasks.data.2.priority', 'low')
        );

    $this->actingAs($user)
        ->get('/tasks?sort_by=priority&sort_dir=desc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tasks/Index')
            ->where('tasks.data.0.priority', 'low')
            ->where('tasks.data.1.priority', 'medium')
            ->where('tasks.data.2.priority', 'high')
        );
});

test('command palette returns active tasks ordered by semantic priority rank', function (): void {
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'title' => 'Alpha Low', 'priority' => 'low', 'status' => 'not_started']);
    Task::factory()->create(['user_id' => $user->id, 'title' => 'Alpha High', 'priority' => 'high', 'status' => 'not_started']);
    Task::factory()->create(['user_id' => $user->id, 'title' => 'Alpha Medium', 'priority' => 'medium', 'status' => 'not_started']);

    $response = $this->actingAs($user)
        ->getJson('/command-palette/search?q=Alpha')
        ->assertOk();

    $priorities = collect($response->json('tasks'))->pluck('priority')->all();

    expect($priorities)->toBe(['high', 'medium', 'low']);
});

test('user timezone governs today and overdue boundaries across UTC midnight', function (): void {
    // 2026-06-15 02:00:00 UTC -> 2026-06-15 11:00 in Tokyo (UTC+9), but 2026-06-14 19:00 in Los Angeles (UTC-7 PDT)
    Carbon::setTestNow(Carbon::parse('2026-06-15 02:00:00', 'UTC'));

    $tokyoUser = User::factory()->create();
    $tokyoUser->ensureSetting()->update(['timezone' => 'Asia/Tokyo']);

    $laUser = User::factory()->create();
    $laUser->ensureSetting()->update(['timezone' => 'America/Los_Angeles']);

    Task::factory()->create([
        'user_id' => $tokyoUser->id,
        'title' => 'June 14 Task Tokyo',
        'due_date' => '2026-06-14',
        'status' => 'not_started',
    ]);
    Task::factory()->create([
        'user_id' => $tokyoUser->id,
        'title' => 'June 15 Task Tokyo',
        'due_date' => '2026-06-15',
        'status' => 'not_started',
    ]);

    Task::factory()->create([
        'user_id' => $laUser->id,
        'title' => 'June 14 Task LA',
        'due_date' => '2026-06-14',
        'status' => 'not_started',
    ]);
    Task::factory()->create([
        'user_id' => $laUser->id,
        'title' => 'June 15 Task LA',
        'due_date' => '2026-06-15',
        'status' => 'not_started',
    ]);

    expect($tokyoUser->localTodayDate())->toBe('2026-06-15')
        ->and($laUser->localTodayDate())->toBe('2026-06-14');

    // Tokyo user sees June 14 as overdue and June 15 as due today
    $this->actingAs($tokyoUser)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.overdueCount', 1)
            ->where('metrics.dueTodayCount', 1)
        );

    // LA user sees June 14 as due today and 0 overdue
    $this->actingAs($laUser)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.overdueCount', 0)
            ->where('metrics.dueTodayCount', 1)
        );
});

test('updating user timezone validates IANA identifier and resynchronizes scheduled reminders', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-10 06:00:00', 'UTC'));

    $user = User::factory()->create();
    $setting = $user->ensureSetting();
    $setting->update(['timezone' => 'UTC']);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Standup Sync',
        'due_date' => '2026-11-12',
        'due_time' => '10:00',
        'reminder_preset' => 'at_due',
        'status' => 'not_started',
    ]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'name' => 'Local Release',
        'status' => 'in_progress',
        'target_date' => '2026-11-15',
        'reminders_enabled' => true,
        'reminder_days' => [1],
    ]);

    $utcReminder = Reminder::where('remindable_type', Task::class)
        ->where('remindable_id', $task->id)
        ->where('category', 'task_reminders')
        ->firstOrFail();

    expect($utcReminder->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-12 10:00');

    // Invalid timezone is rejected
    $this->actingAs($user)
        ->from('/settings')
        ->put('/settings', [
            'theme' => 'system',
            'language' => 'en',
            'timezone' => 'Invalid/Timezone_Name',
            'week_start' => 1,
            'date_format' => 'Y-m-d',
            'time_format' => '24h',
            'default_priority' => 'medium',
            'default_task_duration' => 30,
            'phone_usage_target_minutes' => 90,
            'weekly_review_prompts' => ['Prompt 1'],
        ])
        ->assertSessionHasErrors('timezone');

    // Valid timezone (Africa/Cairo = UTC+2 in winter) shifts 10:00 local to 08:00 UTC
    $this->actingAs($user)
        ->put('/settings', [
            'theme' => 'system',
            'language' => 'en',
            'timezone' => 'Africa/Cairo',
            'week_start' => 1,
            'date_format' => 'Y-m-d',
            'time_format' => '24h',
            'default_priority' => 'medium',
            'default_task_duration' => 30,
            'phone_usage_target_minutes' => 90,
            'weekly_review_prompts' => ['Prompt 1'],
        ])
        ->assertSessionHasNoErrors();

    $resynced = Reminder::where('remindable_type', Task::class)
        ->where('remindable_id', $task->id)
        ->where('category', 'task_reminders')
        ->where('status', 'pending')
        ->firstOrFail();

    expect($resynced->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-12 08:00')
        ->and($project->reminders()->where('status', 'pending')->exists())->toBeTrue();
});
