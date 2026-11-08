<?php

use App\Models\BadHabit;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Models\FocusSession;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Services\BriefingService;
use App\Services\DatabaseBackupService;
use App\Services\WindowsToastService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('browserless windows toast channel dispatches when enabled and via test endpoint', function (): void {
    WindowsToastService::fake();

    $user = User::factory()->create();
    $setting = $user->ensureSetting();
    $setting->update([
        'notifications_enabled' => true,
        'windows_toast_enabled' => true,
        'quiet_hours_enabled' => false,
    ]);

    $user->notify(new OperationalNotification(
        category: 'task_reminders',
        title: 'Task Due: Kernel Threading Lab',
        message: 'Due in 15 minutes.',
        actionUrl: '/tasks',
    ));

    $dispatched = WindowsToastService::dispatched();
    expect($dispatched)->toHaveCount(1)
        ->and($dispatched[0]['title'])->toBe('Task Due: Kernel Threading Lab')
        ->and($dispatched[0]['category'])->toBe('task_reminders');

    $this->actingAs($user)
        ->post(route('settings.test-windows-toast'))
        ->assertRedirect();

    expect(WindowsToastService::dispatched())->toHaveCount(2);

    WindowsToastService::resetFake();
});

test('cli status and quick add commands work for the windows system tray companion', function (): void {
    $user = User::factory()->create();
    $user->ensureSetting()->update(['timezone' => 'UTC']);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'University',
    ]);

    $exitCode = Artisan::call('ops:quick-add', [
        'input' => 'Submit OS assignment tomorrow 17:00 !high #University ~90m',
        '--user' => $user->id,
    ]);

    expect($exitCode)->toBe(0);

    $task = Task::where('user_id', $user->id)->firstOrFail();
    expect($task->title)->toBe('Submit OS assignment')
        ->and($task->priority)->toBe('high')
        ->and($task->category_id)->toBe($category->id)
        ->and($task->estimated_duration)->toBe(90)
        ->and($task->due_time)->toBe('17:00')
        ->and($task->due_date->toDateString())->toBe(Carbon::now('UTC')->addDay()->toDateString());

    $statusExit = Artisan::call('ops:status', [
        '--json' => true,
        '--user' => $user->id,
    ]);

    expect($statusExit)->toBe(0);
    $payload = json_decode(Artisan::output(), true);
    expect($payload)->toBeArray()
        ->and($payload['ok'])->toBeTrue()
        ->and($payload)->toHaveKeys(['due_today_count', 'overdue_count', 'unread_notifications_count']);
});

test('secondary backup folder mirrors encrypted sqlite snapshots and decrypts with full integrity', function (): void {
    $user = User::factory()->create();
    $tempMirrorDir = storage_path('app/test-secondary-mirror-'.uniqid());
    $tempRestoredFile = $tempMirrorDir.DIRECTORY_SEPARATOR.'restored-verify.sqlite';

    try {
        $this->actingAs($user)
            ->put(route('settings.backups.secondary'), [
                'secondary_backup_enabled' => true,
                'secondary_backup_path' => $tempMirrorDir,
                'backup_encryption_enabled' => true,
                'backup_encryption_password' => 'SuperSecretLocalPassphrase!2026',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $setting = $user->fresh()->setting;
        expect($setting->secondary_backup_enabled)->toBeTrue()
            ->and($setting->backup_encryption_enabled)->toBeTrue()
            ->and($setting->has_backup_encryption_password)->toBeTrue();

        $backupService = app(DatabaseBackupService::class);
        $sourceDummySqlite = $tempMirrorDir.DIRECTORY_SEPARATOR.'source.sqlite';
        File::makeDirectory($tempMirrorDir, 0755, true);
        $sqlite = new SQLite3($sourceDummySqlite);
        $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT); INSERT INTO test (name) VALUES ("ops");');
        $sqlite->close();

        $encDest = $tempMirrorDir.DIRECTORY_SEPARATOR.'backup.sqlite.enc';
        $encInfo = $backupService->encryptBackupFile($sourceDummySqlite, $encDest, 'SuperSecretLocalPassphrase!2026');

        expect(File::exists($encInfo['path']))->toBeTrue()
            ->and(substr((string) File::get($encInfo['path']), 0, 10))->toBe('OPSHUBENC1');

        // Wrong passphrase must fail HMAC verification
        expect(fn () => $backupService->decryptBackupFile($encDest, $tempRestoredFile, 'WrongPassphrase'))
            ->toThrow(RuntimeException::class);

        // Correct passphrase via CLI command restores exact bytes
        $cliCode = Artisan::call('ops:decrypt-backup', [
            'source' => $encDest,
            'destination' => $tempRestoredFile,
            '--password' => 'SuperSecretLocalPassphrase!2026',
        ]);

        expect($cliCode)->toBe(0)
            ->and(File::get($tempRestoredFile))->toBe(File::get($sourceDummySqlite));
    } finally {
        if (File::isDirectory($tempMirrorDir)) {
            File::deleteDirectory($tempMirrorDir);
        }
    }
});

test('bad habits support linked good habit replacement 1-click check-in reclaimed metrics and trigger analytics', function (): void {
    $user = User::factory()->create();
    $user->ensureSetting()->update(['timezone' => 'UTC']);
    $today = Carbon::now('UTC')->toDateString();
    $yesterday = Carbon::now('UTC')->subDay()->toDateString();

    $goodHabit = Habit::factory()->create([
        'user_id' => $user->id,
        'name' => 'Read 10 Pages Offline',
        'category' => 'Mindfulness',
    ]);

    $this->actingAs($user)->post(route('bad-habits.store'), [
        'name' => 'Late-Night Doomscrolling',
        'category' => 'Digital Discipline',
        'trigger_cue' => 'Phone on nightstand',
        'replacement_action' => 'Read 10 Pages Offline',
        'linked_habit_id' => $goodHabit->id,
        'minutes_saved_per_day' => 45,
        'money_saved_per_day' => 6.50,
        'has_daily_limit' => false,
        'target_clean_days_per_week' => 7,
    ])->assertRedirect();

    $badHabit = BadHabit::where('user_id', $user->id)->firstOrFail();
    expect($badHabit->linked_habit_id)->toBe($goodHabit->id)
        ->and($badHabit->minutes_saved_per_day)->toBe(45)
        ->and($badHabit->money_saved_per_day)->toBe(6.5);

    // Log a slip yesterday with a trigger note
    $this->actingAs($user)->post(route('bad-habits.check-in', $badHabit), [
        'date' => $yesterday,
        'status' => 'slipped',
        'trigger_note' => 'Late-Night Fatigue',
        'notes' => 'Kept phone in bed',
    ])->assertRedirect();

    // Today: Stay Clean + Log Replacement simultaneously!
    $this->actingAs($user)->post(route('bad-habits.check-in', $badHabit), [
        'date' => $today,
        'status' => 'clean',
        'log_replacement' => true,
    ])->assertRedirect();

    $goodCheckIn = HabitCheckIn::where('habit_id', $goodHabit->id)->whereDate('date', $today)->first();
    expect($goodCheckIn)->not->toBeNull()
        ->and($goodCheckIn->status)->toBe('completed');

    $this->actingAs($user)
        ->get(route('bad-habits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BadHabits/Index')
            ->where('badHabits.0.minutes_reclaimed', 45)
            ->where('badHabits.0.money_reclaimed', 6.5)
            ->where('badHabits.0.linked_habit.id', $goodHabit->id)
            ->where('badHabits.0.linked_habit.today_status', 'completed')
            ->where('summary.total_minutes_reclaimed', 45)
            ->where('summary.total_money_reclaimed', 6.5)
            ->where('triggerAnalytics.total_logged_slips', 1)
            ->where('triggerAnalytics.top_triggers.0.trigger', 'Late-Night Fatigue')
        );
});

test('evening habit check-in nudge dispatches only when active habits remain unlogged after configured time', function (): void {
    $user = User::factory()->create();
    $setting = $user->ensureSetting();
    $setting->update([
        'timezone' => 'UTC',
        'notifications_enabled' => true,
        'quiet_hours_enabled' => false,
        'daily_briefing_enabled' => false,
        'weekly_digest_enabled' => false,
        'evening_checkin_nudge_enabled' => true,
        'evening_checkin_nudge_time' => '20:30',
    ]);

    $habit = Habit::factory()->create([
        'user_id' => $user->id,
        'name' => 'Evening Reflection',
        'is_archived' => false,
    ]);

    $briefingService = app(BriefingService::class);
    $eveningUtc = Carbon::create(2026, 11, 7, 21, 0, 0, 'UTC');

    $counts = $briefingService->dispatchScheduledBriefings($eveningUtc);
    expect($counts['nudge_sent'])->toBe(1);

    $notification = $user->notifications()->latest()->first();
    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Evening Check-In Nudge');

    // Second run on same day should be deduplicated
    $secondCounts = $briefingService->dispatchScheduledBriefings($eveningUtc);
    expect($secondCounts['nudge_sent'])->toBe(0);
});

test('natural language quick capture parses priority duration category project date and time tokens', function (): void {
    $user = User::factory()->create();
    $user->ensureSetting()->update(['timezone' => 'UTC']);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'University',
    ]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'name' => 'Kernel',
    ]);

    $this->actingAs($user)
        ->post(route('tasks.quick-capture'), [
            'input' => 'Finish OS assignment tomorrow 17:00 !high #University +Kernel ~90m',
        ])
        ->assertRedirect();

    $task = Task::where('user_id', $user->id)->firstOrFail();
    expect($task->title)->toBe('Finish OS assignment')
        ->and($task->priority)->toBe('high')
        ->and($task->category_id)->toBe($category->id)
        ->and($task->project_id)->toBe($project->id)
        ->and($task->estimated_duration)->toBe(90)
        ->and($task->due_time)->toBe('17:00')
        ->and($task->due_date->toDateString())->toBe(Carbon::now('UTC')->addDay()->toDateString());
});

test('my day supports visual time-blocking and computes overbooked capacity warnings', function (): void {
    $user = User::factory()->create();
    $user->ensureSetting()->update(['timezone' => 'UTC']);
    $todayStr = Carbon::now('UTC')->toDateString();

    $dailyPlan = DailyPlan::create([
        'user_id' => $user->id,
        'date' => $todayStr,
    ]);

    // 4 hours (240m) of fixed calendar commitments today -> leaves 480m free in 720m workday
    CalendarEvent::create([
        'user_id' => $user->id,
        'title' => 'Systems Engineering Lecture',
        'start_time' => Carbon::parse("{$todayStr} 09:00:00", 'UTC'),
        'end_time' => Carbon::parse("{$todayStr} 13:00:00", 'UTC'),
        'all_day' => false,
    ]);

    // Planned task of 540m -> exceeds 480m free capacity by 60m!
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Deep Architecture Sprint',
        'status' => 'in_progress',
        'estimated_duration' => 540,
    ]);

    DailyPlanTask::create([
        'daily_plan_id' => $dailyPlan->id,
        'task_id' => $task->id,
        'sort_order' => 1,
        'is_priority' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('my-day.time-block', [$dailyPlan->id, $task->id]), [
            'scheduled_start_time' => '14:00',
            'scheduled_end_time' => '16:30',
        ])
        ->assertRedirect();

    $pivot = DailyPlanTask::where('daily_plan_id', $dailyPlan->id)->where('task_id', $task->id)->firstOrFail();
    expect($pivot->scheduled_start_time)->toBe('14:00')
        ->and($pivot->scheduled_end_time)->toBe('16:30');

    $this->actingAs($user)
        ->get(route('my-day.index', ['date' => $todayStr]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyDay/Index')
            ->where('plannedTasks.0.scheduled_start_time', '14:00')
            ->where('plannedTasks.0.scheduled_end_time', '16:30')
            ->where('capacity.committed_event_minutes', 240)
            ->where('capacity.available_free_minutes', 480)
            ->where('capacity.planned_task_minutes', 540)
            ->where('capacity.is_overbooked', true)
            ->where('capacity.overbooked_by_minutes', 60)
        );
});

test('task dependencies prevent circular cycles auto-wait blocked tasks and auto-promote when blockers complete', function (): void {
    $user = User::factory()->create();
    $user->ensureSetting()->update([
        'notifications_enabled' => true,
        'quiet_hours_enabled' => false,
    ]);

    $prereq = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Assemble PCB Power Stage',
        'status' => 'in_progress',
    ]);

    $dependent = Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Run Thruster Load Test',
        'status' => 'not_started',
    ]);

    // Link dependent -> blocked by prereq
    $this->actingAs($user)
        ->put(route('tasks.update', $dependent), [
            'title' => $dependent->title,
            'priority' => $dependent->priority,
            'status' => 'not_started',
            'blocked_by_ids' => [$prereq->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Dependent task is automatically placed into 'waiting' while prereq is incomplete
    expect($dependent->fresh()->status)->toBe('waiting');

    // Attempting circular dependency (prereq blocked by dependent) must fail validation
    $this->actingAs($user)
        ->put(route('tasks.update', $prereq), [
            'title' => $prereq->title,
            'priority' => $prereq->priority,
            'status' => 'in_progress',
            'blocked_by_ids' => [$dependent->id],
        ])
        ->assertSessionHasErrors('blocked_by_ids');

    // Completing prereq automatically unblocks dependent -> 'not_started' + sends notification!
    $this->actingAs($user)
        ->post(route('tasks.toggle', $prereq))
        ->assertRedirect();

    expect($prereq->fresh()->status)->toBe('completed')
        ->and($dependent->fresh()->status)->toBe('not_started');

    $unblockNotification = $user->notifications()
        ->get()
        ->first(fn ($n) => str_contains($n->data['title'] ?? '', 'Task Unblocked: Run Thruster Load Test'));

    expect($unblockNotification)->not->toBeNull();
});

test('personal analytics page computes estimation accuracy deep work by area and screen time correlations', function (): void {
    $user = User::factory()->create();
    $user->ensureSetting()->update([
        'timezone' => 'UTC',
        'phone_usage_target_minutes' => 60,
    ]);

    $todayStr = Carbon::now('UTC')->toDateString();

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Robotics Lab',
        'color' => '#10b981',
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Calibrate IMU Sensor',
        'status' => 'completed',
        'estimated_duration' => 60,
        'actual_duration' => 60,
        'completed_at' => now(),
    ]);

    FocusSession::create([
        'user_id' => $user->id,
        'task_id' => $task->id,
        'mode' => 'focus',
        'planned_minutes' => 60,
        'elapsed_seconds' => 3600,
        'logged_minutes' => 60,
        'status' => 'completed',
        'started_at' => now()->subHour(),
        'ended_at' => now(),
    ]);

    PhoneUsageLog::create([
        'user_id' => $user->id,
        'date' => $todayStr,
        'minutes' => 35,
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['range' => 30]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Index')
            ->where('analytics.range_days', 30)
            ->where('analytics.estimation.evaluated_tasks_count', 1)
            ->where('analytics.estimation.average_accuracy_percent', 100)
            ->where('analytics.estimation.on_target_count', 1)
            ->where('analytics.deep_work.total_minutes', 60)
            ->where('analytics.deep_work.by_category.0.name', 'Robotics Lab')
            ->where('analytics.correlations.low_screen_avg_focus_minutes', 60)
        );
});
