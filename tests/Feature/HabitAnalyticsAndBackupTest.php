<?php

use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\Task;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\HabitAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('habit analytics computes daily streaks flexible weekly targets heatmap intensity and excludes archived habits', function (): void {
    // Wednesday 2026-11-11
    Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $user->ensureSetting()->update(['timezone' => 'UTC', 'week_start' => 1]);

    // 1. Strict 7-day habit checked in Mon, Tue (today Wed not checked in yet -> streak preserved at 2!)
    $dailyHabit = Habit::create([
        'user_id' => $user->id,
        'name' => 'Morning Prayer',
        'category' => 'Spiritual',
        'frequency' => 'daily',
        'target_days_per_week' => 7,
        'is_archived' => false,
    ]);
    $dailyHabit->forceFill(['created_at' => Carbon::parse('2026-11-09 06:00:00', 'UTC')])->saveQuietly();

    HabitCheckIn::create(['habit_id' => $dailyHabit->id, 'user_id' => $user->id, 'date' => '2026-11-09', 'status' => 'completed']);
    HabitCheckIn::create(['habit_id' => $dailyHabit->id, 'user_id' => $user->id, 'date' => '2026-11-10', 'status' => 'partial']);

    $analytics = app(HabitAnalyticsService::class);
    $dailyMetrics = $analytics->calculateHabitMetrics($dailyHabit, $user);

    expect($dailyMetrics['current_streak'])->toBe(2)
        ->and($dailyMetrics['best_streak'])->toBe(2)
        ->and($dailyMetrics['total_completed_days'])->toBe(2);

    // 2. Flexible 5-days/week habit: checked in Mon-Fri last week (Nov 2 - Nov 6), skipped Sat/Sun (Nov 7-8), checked in Mon-Tue (Nov 9-10) -> 7 streak!
    $weekdayHabit = Habit::create([
        'user_id' => $user->id,
        'name' => 'Deep Technical Study',
        'category' => 'Engineering',
        'frequency' => 'daily',
        'target_days_per_week' => 5,
        'is_archived' => false,
    ]);
    $weekdayHabit->forceFill(['created_at' => Carbon::parse('2026-11-02 06:00:00', 'UTC')])->saveQuietly();

    foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-09', '2026-11-10'] as $d) {
        HabitCheckIn::create(['habit_id' => $weekdayHabit->id, 'user_id' => $user->id, 'date' => $d, 'status' => 'completed']);
    }

    $flexMetrics = $analytics->calculateHabitMetrics($weekdayHabit, $user);
    expect($flexMetrics['current_streak'])->toBe(7)
        ->and($flexMetrics['best_streak'])->toBe(7);

    // 3. Archived habit is excluded from active summary on /habits
    $archivedHabit = Habit::create([
        'user_id' => $user->id,
        'name' => 'Old Archived Habit',
        'category' => 'General',
        'frequency' => 'daily',
        'target_days_per_week' => 7,
        'is_archived' => true,
    ]);
    HabitCheckIn::create(['habit_id' => $archivedHabit->id, 'user_id' => $user->id, 'date' => '2026-11-10', 'status' => 'completed']);

    $heatmap = $analytics->buildMonthlyHeatmap($user, '2026-11');
    expect($heatmap['month'])->toBe('2026-11')
        ->and($heatmap['monthly_summary'])->toHaveCount(6);

    $this->actingAs($user)
        ->get('/habits')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Habits/Index')
            ->where('summary.total_active_habits', 2)
        );
});

test('sqlite backup service creates verified snapshots prunes expired files and safely restores with pre-restore backup', function (): void {
    $tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'poh-backup-suite-'.uniqid();
    $backupDir = $tempRoot.DIRECTORY_SEPARATOR.'backups';
    $sourceDbPath = $tempRoot.DIRECTORY_SEPARATOR.'sandbox.sqlite';

    File::makeDirectory($backupDir, 0755, true);

    try {
        // Initialize a standalone real SQLite 3 database file so tests never touch database/database.sqlite
        $sqlite = new SQLite3($sourceDbPath);
        $sqlite->exec('CREATE TABLE sample_records (id INTEGER PRIMARY KEY, label TEXT);');
        $sqlite->exec("INSERT INTO sample_records (label) VALUES ('Original State');");
        $sqlite->close();

        config([
            'operations.backups.directory' => $backupDir,
            'operations.backups.source_database' => $sourceDbPath,
            'operations.backups.default_retention_days' => 7,
        ]);

        $user = User::factory()->create();
        $service = app(DatabaseBackupService::class);

        // 1. Create manual backup via HTTP endpoint
        $this->actingAs($user)
            ->post('/settings/backups/create')
            ->assertRedirect();

        $backups = $service->listBackups();
        expect($backups)->toHaveCount(1)
            ->and($backups[0]['integrity_status'])->toBe('ok')
            ->and($backups[0]['size_bytes'])->toBeGreaterThan(0);

        $firstBackupFilename = $backups[0]['filename'];

        // 2. Download backup works and blocks path traversal
        $this->actingAs($user)
            ->get("/settings/backups/{$firstBackupFilename}/download")
            ->assertOk();

        expect(fn () => $service->resolveBackupPath('../evil.sqlite'))
            ->toThrow(RuntimeException::class);

        // 3. Mutate the live SQLite file, then restore from the first snapshot
        $mutated = new SQLite3($sourceDbPath);
        $mutated->exec("UPDATE sample_records SET label = 'Mutated Later State';");
        $mutated->close();

        // Restore requires exact confirmation = RESTORE
        $this->actingAs($user)
            ->from('/settings/data')
            ->post('/settings/backups/restore', [
                'filename' => $firstBackupFilename,
                'confirmation' => 'wrong',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($user)
            ->post('/settings/backups/restore', [
                'filename' => $firstBackupFilename,
                'confirmation' => 'RESTORE',
            ])
            ->assertSessionHasNoErrors();

        // Verify restored SQLite content AND automatic pre_restore snapshot creation
        $restoredCheck = new SQLite3($sourceDbPath, SQLITE3_OPEN_READONLY);
        $restoredLabel = $restoredCheck->querySingle('SELECT label FROM sample_records WHERE id = 1;');
        $restoredCheck->close();

        expect($restoredLabel)->toBe('Original State');

        $afterRestoreBackups = $service->listBackups();
        $triggers = collect($afterRestoreBackups)->pluck('trigger')->all();
        expect($triggers)->toContain('pre_restore');

        // 4. Retention pruning deletes expired snapshots while keeping recent ones
        $expiredPath = $backupDir.DIRECTORY_SEPARATOR.'ops-hub-backup-2025-01-01-000000-scheduled-aaaa.sqlite';
        File::copy($sourceDbPath, $expiredPath);
        File::put($expiredPath.'.json', json_encode([
            'filename' => basename($expiredPath),
            'created_at' => Carbon::now()->subDays(30)->toIso8601String(),
            'trigger' => 'scheduled',
        ]));

        $pruned = $service->pruneOldBackups(7);
        expect($pruned)->toBe(1)
            ->and(File::exists($expiredPath))->toBeFalse();
    } finally {
        File::deleteDirectory($tempRoot);
    }
});

test('daily briefing and weekly digest render and scheduled ops:send-briefings deduplicates per day and week', function (): void {
    // Sunday 2026-11-15 18:05 UTC
    Carbon::setTestNow(Carbon::parse('2026-11-15 18:05:00', 'UTC'));

    $user = User::factory()->create();
    $user->ensureSetting()->update([
        'timezone' => 'UTC',
        'quiet_hours_enabled' => false,
        'daily_briefing_enabled' => true,
        'daily_briefing_time' => '07:30',
        'weekly_digest_enabled' => true,
        'weekly_digest_day' => 0, // Sunday
        'weekly_digest_time' => '18:00',
    ]);

    Task::factory()->create([
        'user_id' => $user->id,
        'title' => 'Prepare Monday Lecture Notes',
        'due_date' => '2026-11-15',
        'priority' => 'high',
        'status' => 'not_started',
    ]);

    $this->actingAs($user)
        ->get('/briefings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Briefings/Index')
            ->has('dailyBriefing')
            ->has('weeklyDigest')
        );

    $this->artisan('ops:send-briefings')->assertExitCode(0);

    expect($user->notifications()->where('data->category', 'daily_briefing')->count())->toBe(1)
        ->and($user->notifications()->where('data->category', 'weekly_digest')->count())->toBe(1);

    // Running again on the same day/week sends 0 duplicate briefings
    $this->artisan('ops:send-briefings')->assertExitCode(0);

    expect($user->notifications()->where('data->category', 'daily_briefing')->count())->toBe(1)
        ->and($user->notifications()->where('data->category', 'weekly_digest')->count())->toBe(1);
});
