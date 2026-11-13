<?php

namespace App\Http\Controllers;

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
use App\Models\WeeklyReview;
use App\Services\DatabaseBackupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DataExportImportController extends Controller
{
    public function index(Request $request, DatabaseBackupService $backupService): Response
    {
        $user = $request->user();
        $setting = $user->ensureSetting();
        $backups = $backupService->listBackups();

        return Inertia::render('Settings/DataManagement', [
            'backups' => $backups,
            'lastBackup' => $backups[0] ?? null,
            'backupConfig' => [
                'backup_enabled' => $setting->backup_enabled,
                'backup_retention_days' => $setting->backup_retention_days,
                'directory' => $backupService->backupDirectory(),
                'secondary_backup_enabled' => $setting->secondary_backup_enabled,
                'secondary_backup_path' => $setting->secondary_backup_path,
                'backup_encryption_enabled' => $setting->backup_encryption_enabled,
                'has_backup_encryption_password' => $setting->has_backup_encryption_password,
            ],
        ]);
    }

    public function updateSecondaryBackupConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'secondary_backup_enabled' => 'required|boolean',
            'secondary_backup_path' => 'nullable|required_if:secondary_backup_enabled,true|string|max:500',
            'backup_encryption_enabled' => 'required|boolean',
            'backup_encryption_password' => 'nullable|string|min:6|max:255',
        ]);

        $setting = $request->user()->ensureSetting();

        if ($validated['backup_encryption_enabled'] && empty($validated['backup_encryption_password']) && ! $setting->has_backup_encryption_password) {
            return back()->withErrors([
                'backup_encryption_password' => 'Provide an encryption passphrase (min 6 characters) when enabling AES-256 backup encryption.',
            ]);
        }

        $updates = [
            'secondary_backup_enabled' => (bool) $validated['secondary_backup_enabled'],
            'secondary_backup_path' => $validated['secondary_backup_path'] ?? null,
            'backup_encryption_enabled' => (bool) $validated['backup_encryption_enabled'],
        ];

        if (! empty($validated['backup_encryption_password'])) {
            $updates['backup_encryption_password_encrypted'] = Crypt::encryptString($validated['backup_encryption_password']);
        }

        $setting->update($updates);

        return back()->with('success', 'Secondary backup & AES-256 encryption settings saved.');
    }

    public function exportJson(Request $request): StreamedResponse
    {
        $user = $request->user();

        $data = [
            'version' => '2.0',
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'settings' => $user->setting,
            'categories' => $user->categories()->withTrashed()->get(),
            'projects' => $user->projects()->with('milestones')->withTrashed()->get(),
            'tags' => $user->tags,
            'tasks' => $user->tasks()->with(['checklists', 'tags'])->withTrashed()->get(),
            'daily_plans' => $user->dailyPlans()->with('planTasks')->get(),
            'calendar_events' => $user->calendarEvents,
            'habits' => $user->habits()->with('checkIns')->get(),
            'bad_habits' => $user->badHabits()->with('checkIns')->get(),
            'weekly_reviews' => $user->weeklyReviews,
            'phone_usage_logs' => $user->phoneUsageLogs,
            'focus_sessions' => $user->focusSessions()->latest()->limit(500)->get(),
        ];

        $filename = 'personal-operations-hub-backup-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function exportTasksCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $tasks = Task::with(['category', 'project', 'tags'])
            ->where('user_id', $user->id)
            ->orderByPriority()
            ->get();

        $filename = 'tasks-export-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($tasks): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Title',
                'Description',
                'Category',
                'Project',
                'Priority',
                'Status',
                'Due Date',
                'Due Time',
                'Start Date',
                'Est. Duration (min)',
                'Actual Duration (min)',
                'Next Action',
                'Tags',
                'Created At',
                'Completed At',
            ]);

            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task->id,
                    $task->title,
                    $task->description,
                    $task->category?->name ?? 'None',
                    $task->project?->name ?? 'None',
                    $task->priority,
                    $task->status,
                    $task->due_date?->toDateString(),
                    $task->due_time,
                    $task->start_date?->toDateString(),
                    $task->estimated_duration,
                    $task->actual_duration,
                    $task->next_action,
                    $task->tags->pluck('name')->join(', '),
                    $task->created_at->toDateTimeString(),
                    $task->completed_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function downloadDatabaseBackup(): BinaryFileResponse|RedirectResponse
    {
        $dbPath = database_path('database.sqlite');

        if (! file_exists($dbPath)) {
            return back()->with('error', 'Database file not found.');
        }

        $filename = 'database-backup-'.now()->format('Y-m-d-His').'.sqlite';

        return response()->download($dbPath, $filename, [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    public function createManualBackup(Request $request, DatabaseBackupService $backupService): RedirectResponse
    {
        try {
            $meta = $backupService->createBackup('manual', $request->user());
            $retentionDays = $request->user()->ensureSetting()->backup_retention_days;
            $backupService->pruneOldBackups($retentionDays);

            return back()->with('success', "Verified SQLite backup created ({$meta['filename']}).");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function downloadManagedBackup(string $filename, DatabaseBackupService $backupService): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $backupService->resolveBackupPath($filename);

            return response()->download($path, basename($path), [
                'Content-Type' => 'application/x-sqlite3',
            ]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function deleteManagedBackup(string $filename, DatabaseBackupService $backupService): RedirectResponse
    {
        try {
            $backupService->deleteBackup($filename);

            return back()->with('success', 'Backup removed.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function restoreManagedBackup(Request $request, DatabaseBackupService $backupService): RedirectResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'confirmation' => 'required|string|in:RESTORE',
        ]);

        try {
            $result = $backupService->restoreFromBackup($validated['filename']);

            return back()->with(
                'success',
                "Database restored from {$result['restored_from']}. Pre-restore safety snapshot saved as {$result['pre_restore_snapshot']}."
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function previewImport(Request $request): JsonResponse
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:json,txt',
        ]);

        $content = file_get_contents($request->file('backup_file')->getRealPath());
        $data = json_decode((string) $content, true);

        if (! $data || ! is_array($data) || ! isset($data['version'])) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid backup file structure.',
            ], 422);
        }

        $summary = [
            'version' => $data['version'],
            'exported_at' => $data['exported_at'] ?? 'Unknown',
            'categories_count' => count($data['categories'] ?? []),
            'projects_count' => count($data['projects'] ?? []),
            'tasks_count' => count($data['tasks'] ?? []),
            'tags_count' => count($data['tags'] ?? []),
            'habits_count' => count($data['habits'] ?? []),
            'bad_habits_count' => count($data['bad_habits'] ?? []),
            'calendar_events_count' => count($data['calendar_events'] ?? []),
            'daily_plans_count' => count($data['daily_plans'] ?? []),
            'weekly_reviews_count' => count($data['weekly_reviews'] ?? []),
            'phone_usage_logs_count' => count($data['phone_usage_logs'] ?? []),
            'focus_sessions_count' => count($data['focus_sessions'] ?? []),
        ];

        return response()->json([
            'valid' => true,
            'summary' => $summary,
            'raw' => $data,
        ]);
    }

    public function processImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payload' => 'required|array',
            'mode' => 'required|in:merge,replace',
        ]);

        $user = $request->user();
        $payload = $validated['payload'];
        $mode = $validated['mode'];

        if (! isset($payload['version'])) {
            throw ValidationException::withMessages([
                'payload' => 'Invalid backup payload: missing version identifier.',
            ]);
        }

        foreach ([
            'categories', 'projects', 'tags', 'tasks', 'habits', 'bad_habits',
            'calendar_events', 'daily_plans', 'weekly_reviews', 'phone_usage_logs', 'focus_sessions',
        ] as $key) {
            if (isset($payload[$key]) && ! is_array($payload[$key])) {
                throw ValidationException::withMessages([
                    'payload' => "Invalid format for collection: {$key}.",
                ]);
            }
        }

        try {
            DB::transaction(function () use ($user, $payload, $mode): void {
                if ($mode === 'replace') {
                    $user->focusSessions()->delete();
                    $user->dailyPlans()->delete();
                    $user->tasks()->forceDelete();
                    $user->projects()->forceDelete();
                    $user->calendarEvents()->delete();
                    $user->categories()->forceDelete();
                    $user->tags()->delete();
                    $user->badHabits()->delete();
                    $user->habits()->delete();
                    $user->weeklyReviews()->delete();
                    $user->phoneUsageLogs()->delete();

                    if (! empty($payload['settings']) && is_array($payload['settings'])) {
                        $s = $payload['settings'];
                        $setting = $user->ensureSetting();
                        $fillableKeys = [
                            'display_name', 'theme', 'language', 'timezone', 'week_start',
                            'date_format', 'time_format', 'default_priority', 'default_task_duration',
                            'phone_usage_target_minutes', 'weekly_review_prompts', 'quiet_hours_enabled',
                            'quiet_hours_start', 'quiet_hours_end', 'allow_critical_in_quiet_hours',
                            'notifications_enabled', 'daily_briefing_enabled', 'daily_briefing_time',
                            'weekly_digest_enabled', 'weekly_digest_day', 'weekly_digest_time',
                            'focus_work_minutes', 'focus_short_break_minutes', 'focus_long_break_minutes',
                        ];
                        $settingUpdates = [];
                        foreach ($fillableKeys as $k) {
                            if (array_key_exists($k, $s) && $s[$k] !== null) {
                                $settingUpdates[$k] = $s[$k];
                            }
                        }
                        if (! empty($settingUpdates)) {
                            $setting->update($settingUpdates);
                        }
                    }
                }

                // 1. Categories
                $categoryMap = [];
                foreach ($payload['categories'] ?? [] as $catData) {
                    if (! is_array($catData) || empty($catData['name'])) {
                        throw new \RuntimeException('Category record missing required name field.');
                    }

                    $category = Category::firstOrCreate(
                        ['user_id' => $user->id, 'name' => (string) $catData['name']],
                        [
                            'color' => $catData['color'] ?? '#6366f1',
                            'icon' => $catData['icon'] ?? 'Folder',
                            'sort_order' => $catData['sort_order'] ?? 0,
                            'is_archived' => (bool) ($catData['is_archived'] ?? false),
                        ]
                    );
                    if (isset($catData['id'])) {
                        $categoryMap[$catData['id']] = $category->id;
                    }
                }

                // 2. Tags
                $tagMap = [];
                foreach ($payload['tags'] ?? [] as $tagData) {
                    if (! is_array($tagData) || empty($tagData['name'])) {
                        throw new \RuntimeException('Tag record missing required name field.');
                    }

                    $tag = Tag::firstOrCreate(
                        ['user_id' => $user->id, 'name' => (string) $tagData['name']],
                        [
                            'color' => $tagData['color'] ?? '#6366f1',
                        ]
                    );
                    if (isset($tagData['id'])) {
                        $tagMap[$tagData['id']] = $tag->id;
                    }
                }

                // 3. Projects & Milestones
                $projectMap = [];
                foreach ($payload['projects'] ?? [] as $projData) {
                    if (! is_array($projData) || empty($projData['name'])) {
                        throw new \RuntimeException('Project record missing required name field.');
                    }

                    $newCatId = isset($projData['category_id']) ? ($categoryMap[$projData['category_id']] ?? null) : null;
                    $project = Project::firstOrCreate(
                        ['user_id' => $user->id, 'name' => (string) $projData['name']],
                        [
                            'category_id' => $newCatId,
                            'description' => $projData['description'] ?? null,
                            'status' => $projData['status'] ?? 'in_progress',
                            'start_date' => $projData['start_date'] ?? null,
                            'target_date' => $projData['target_date'] ?? null,
                            'reminders_enabled' => (bool) ($projData['reminders_enabled'] ?? true),
                            'reminder_days' => $projData['reminder_days'] ?? null,
                            'overdue_reminders_enabled' => (bool) ($projData['overdue_reminders_enabled'] ?? true),
                            'notes' => $projData['notes'] ?? null,
                        ]
                    );
                    if (isset($projData['id'])) {
                        $projectMap[$projData['id']] = $project->id;
                    }

                    $milestones = $projData['milestones'] ?? [];
                    if (is_array($milestones)) {
                        foreach ($milestones as $idx => $m) {
                            if (! is_array($m) || empty($m['title'])) {
                                continue;
                            }
                            ProjectMilestone::firstOrCreate(
                                ['project_id' => $project->id, 'title' => (string) $m['title']],
                                [
                                    'due_date' => $m['due_date'] ?? null,
                                    'due_time' => ! empty($m['due_time']) ? substr((string) $m['due_time'], 0, 5) : null,
                                    'is_completed' => (bool) ($m['is_completed'] ?? false),
                                    'reminders_enabled' => (bool) ($m['reminders_enabled'] ?? true),
                                    'reminder_offsets' => $m['reminder_offsets'] ?? null,
                                    'sort_order' => $m['sort_order'] ?? ($idx + 1),
                                ]
                            );
                        }
                    }
                }

                // 4. Tasks, Checklists & Tags
                $taskMap = [];
                foreach ($payload['tasks'] ?? [] as $tData) {
                    if (! is_array($tData) || empty($tData['title'])) {
                        throw new \RuntimeException('Task record missing required title field.');
                    }

                    $newCatId = isset($tData['category_id']) ? ($categoryMap[$tData['category_id']] ?? null) : null;
                    $newProjId = isset($tData['project_id']) ? ($projectMap[$tData['project_id']] ?? null) : null;

                    $task = Task::firstOrCreate(
                        ['user_id' => $user->id, 'title' => (string) $tData['title']],
                        [
                            'category_id' => $newCatId,
                            'project_id' => $newProjId,
                            'description' => $tData['description'] ?? null,
                            'priority' => $tData['priority'] ?? 'medium',
                            'status' => $tData['status'] ?? 'not_started',
                            'due_date' => $tData['due_date'] ?? null,
                            'due_time' => $tData['due_time'] ?? null,
                            'reminder_preset' => $tData['reminder_preset'] ?? 'none',
                            'remind_at' => $tData['remind_at'] ?? null,
                            'custom_reminder_times' => $tData['custom_reminder_times'] ?? null,
                            'start_date' => $tData['start_date'] ?? null,
                            'estimated_duration' => $tData['estimated_duration'] ?? null,
                            'actual_duration' => $tData['actual_duration'] ?? null,
                            'notes' => $tData['notes'] ?? null,
                            'next_action' => $tData['next_action'] ?? null,
                            'is_recurring' => (bool) ($tData['is_recurring'] ?? false),
                            'recurrence_rule' => $tData['recurrence_rule'] ?? null,
                            'recurrence_interval' => $tData['recurrence_interval'] ?? 1,
                            'recurrence_end_type' => $tData['recurrence_end_type'] ?? 'never',
                            'recurrence_end_date' => $tData['recurrence_end_date'] ?? null,
                            'recurrence_max_occurrences' => $tData['recurrence_max_occurrences'] ?? null,
                            'recurrence_occurrences_count' => $tData['recurrence_occurrences_count'] ?? 1,
                            'sort_order' => $tData['sort_order'] ?? 0,
                            'completed_at' => $tData['completed_at'] ?? null,
                        ]
                    );
                    if (isset($tData['id'])) {
                        $taskMap[$tData['id']] = $task->id;
                    }

                    $checklists = $tData['checklists'] ?? [];
                    if (is_array($checklists)) {
                        foreach ($checklists as $idx => $cl) {
                            if (! is_array($cl) || empty($cl['title'])) {
                                continue;
                            }
                            TaskChecklist::firstOrCreate(
                                ['task_id' => $task->id, 'title' => (string) $cl['title']],
                                [
                                    'is_completed' => (bool) ($cl['is_completed'] ?? false),
                                    'sort_order' => $cl['sort_order'] ?? ($idx + 1),
                                ]
                            );
                        }
                    }

                    $taskTags = $tData['tags'] ?? [];
                    if (is_array($taskTags)) {
                        $syncTagIds = [];
                        foreach ($taskTags as $tagItem) {
                            if (is_array($tagItem) && ! empty($tagItem['name'])) {
                                $resolvedTag = Tag::firstOrCreate(
                                    ['user_id' => $user->id, 'name' => (string) $tagItem['name']],
                                    ['color' => $tagItem['color'] ?? '#6366f1']
                                );
                                $syncTagIds[] = $resolvedTag->id;
                            } elseif (is_numeric($tagItem) && isset($tagMap[$tagItem])) {
                                $syncTagIds[] = $tagMap[$tagItem];
                            }
                        }
                        if (! empty($syncTagIds)) {
                            $task->tags()->syncWithoutDetaching($syncTagIds);
                        }
                    }
                }

                // 5. Habits & Habit Check-Ins
                $habitMap = [];
                foreach ($payload['habits'] ?? [] as $hData) {
                    if (! is_array($hData) || empty($hData['name'])) {
                        throw new \RuntimeException('Habit record missing required name field.');
                    }

                    $habit = Habit::firstOrCreate(
                        ['user_id' => $user->id, 'name' => (string) $hData['name']],
                        [
                            'description' => $hData['description'] ?? null,
                            'category' => $hData['category'] ?? 'Spiritual Life',
                            'frequency' => $hData['frequency'] ?? 'daily',
                            'target_days_per_week' => (int) ($hData['target_days_per_week'] ?? 7),
                            'sort_order' => $hData['sort_order'] ?? 0,
                            'is_archived' => (bool) ($hData['is_archived'] ?? false),
                        ]
                    );
                    if (isset($hData['id'])) {
                        $habitMap[$hData['id']] = $habit->id;
                    }

                    $checkIns = $hData['check_ins'] ?? $hData['checkIns'] ?? [];
                    if (is_array($checkIns)) {
                        foreach ($checkIns as $ci) {
                            if (! is_array($ci) || empty($ci['date'])) {
                                continue;
                            }
                            $ciDate = substr((string) $ci['date'], 0, 10);
                            HabitCheckIn::firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'habit_id' => $habit->id,
                                    'date' => $ciDate,
                                ],
                                [
                                    'status' => $ci['status'] ?? 'completed',
                                    'notes' => $ci['notes'] ?? null,
                                ]
                            );
                        }
                    }
                }

                // 6. Bad Habits & Bad Habit Check-Ins
                $badHabitMap = [];
                foreach ($payload['bad_habits'] ?? [] as $bhData) {
                    if (! is_array($bhData) || empty($bhData['name'])) {
                        throw new \RuntimeException('Bad habit record missing required name field.');
                    }

                    $linkedHabitId = isset($bhData['linked_habit_id']) ? ($habitMap[$bhData['linked_habit_id']] ?? null) : null;

                    $badHabit = BadHabit::firstOrCreate(
                        ['user_id' => $user->id, 'name' => (string) $bhData['name']],
                        [
                            'description' => $bhData['description'] ?? null,
                            'category' => $bhData['category'] ?? 'Digital Discipline',
                            'trigger_cue' => $bhData['trigger_cue'] ?? null,
                            'replacement_action' => $bhData['replacement_action'] ?? null,
                            'linked_habit_id' => $linkedHabitId,
                            'minutes_saved_per_day' => (int) ($bhData['minutes_saved_per_day'] ?? 0),
                            'money_saved_per_day' => (float) ($bhData['money_saved_per_day'] ?? 0),
                            'has_daily_limit' => (bool) ($bhData['has_daily_limit'] ?? false),
                            'daily_limit_value' => $bhData['daily_limit_value'] ?? null,
                            'daily_limit_unit' => $bhData['daily_limit_unit'] ?? 'times',
                            'target_clean_days_per_week' => $bhData['target_clean_days_per_week'] ?? 7,
                            'sort_order' => $bhData['sort_order'] ?? 0,
                            'is_archived' => (bool) ($bhData['is_archived'] ?? false),
                        ]
                    );
                    if (isset($bhData['id'])) {
                        $badHabitMap[$bhData['id']] = $badHabit->id;
                    }

                    $bhCheckIns = $bhData['check_ins'] ?? $bhData['checkIns'] ?? [];
                    if (is_array($bhCheckIns)) {
                        foreach ($bhCheckIns as $bci) {
                            if (! is_array($bci) || empty($bci['date'])) {
                                continue;
                            }
                            $bciDate = substr((string) $bci['date'], 0, 10);
                            BadHabitCheckIn::firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'bad_habit_id' => $badHabit->id,
                                    'date' => $bciDate,
                                ],
                                [
                                    'status' => $bci['status'] ?? 'clean',
                                    'observed_value' => $bci['observed_value'] ?? null,
                                    'trigger_note' => $bci['trigger_note'] ?? null,
                                    'notes' => $bci['notes'] ?? null,
                                ]
                            );
                        }
                    }
                }

                // 7. Calendar Events
                foreach ($payload['calendar_events'] ?? [] as $ceData) {
                    if (! is_array($ceData) || empty($ceData['title']) || empty($ceData['start_time'])) {
                        throw new \RuntimeException('Calendar event missing required title or start_time.');
                    }

                    $newCatId = isset($ceData['category_id']) ? ($categoryMap[$ceData['category_id']] ?? null) : null;
                    $endTime = ! empty($ceData['end_time'])
                        ? $ceData['end_time']
                        : Carbon::parse($ceData['start_time'])->addHour()->toDateTimeString();

                    CalendarEvent::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'title' => (string) $ceData['title'],
                            'start_time' => $ceData['start_time'],
                        ],
                        [
                            'category_id' => $newCatId,
                            'description' => $ceData['description'] ?? $ceData['notes'] ?? null,
                            'end_time' => $endTime,
                            'all_day' => (bool) ($ceData['all_day'] ?? false),
                            'location' => $ceData['location'] ?? null,
                            'reminder_minutes' => (int) ($ceData['reminder_minutes'] ?? 15),
                            'color' => $ceData['color'] ?? null,
                        ]
                    );
                }

                // 8. Daily Plans & Daily Plan Tasks
                foreach ($payload['daily_plans'] ?? [] as $dpData) {
                    if (! is_array($dpData) || empty($dpData['date'])) {
                        throw new \RuntimeException('Daily plan missing required date.');
                    }

                    $planDate = substr((string) $dpData['date'], 0, 10);

                    $dailyPlan = DailyPlan::firstOrCreate(
                        ['user_id' => $user->id, 'date' => $planDate],
                        [
                            'fallback_plan' => $dpData['fallback_plan'] ?? '',
                            'personal_recovery_notes' => $dpData['personal_recovery_notes'] ?? '',
                            'notes' => $dpData['notes'] ?? '',
                        ]
                    );

                    $planTasks = $dpData['plan_tasks'] ?? $dpData['planTasks'] ?? [];
                    if (is_array($planTasks)) {
                        foreach ($planTasks as $pt) {
                            if (! is_array($pt) || empty($pt['task_id'])) {
                                continue;
                            }

                            $mappedTaskId = $taskMap[$pt['task_id']] ?? null;
                            if (! $mappedTaskId) {
                                continue;
                            }

                            DailyPlanTask::firstOrCreate(
                                [
                                    'daily_plan_id' => $dailyPlan->id,
                                    'task_id' => $mappedTaskId,
                                ],
                                [
                                    'is_priority' => (bool) ($pt['is_priority'] ?? false),
                                    'sort_order' => $pt['sort_order'] ?? 0,
                                    'scheduled_start_time' => $pt['scheduled_start_time'] ?? null,
                                    'scheduled_end_time' => $pt['scheduled_end_time'] ?? null,
                                ]
                            );
                        }
                    }
                }

                // 9. Weekly Reviews
                foreach ($payload['weekly_reviews'] ?? [] as $wrData) {
                    if (! is_array($wrData) || empty($wrData['week_start_date'])) {
                        throw new \RuntimeException('Weekly review missing required week_start_date.');
                    }

                    $startDate = substr((string) $wrData['week_start_date'], 0, 10);
                    $endDate = isset($wrData['week_end_date'])
                        ? substr((string) $wrData['week_end_date'], 0, 10)
                        : Carbon::parse($startDate)->addDays(6)->toDateString();

                    $existingReview = WeeklyReview::where('user_id', $user->id)
                        ->whereDate('week_start_date', $startDate)
                        ->first();

                    if (! $existingReview) {
                        WeeklyReview::create([
                            'user_id' => $user->id,
                            'week_start_date' => $startDate,
                            'week_end_date' => $endDate,
                            'responses' => $wrData['responses'] ?? [],
                            'notes' => $wrData['notes'] ?? null,
                            'is_completed' => (bool) ($wrData['is_completed'] ?? false),
                        ]);
                    }
                }

                // 10. Phone Usage Logs
                foreach ($payload['phone_usage_logs'] ?? [] as $pulData) {
                    if (! is_array($pulData) || empty($pulData['date'])) {
                        throw new \RuntimeException('Phone usage log missing required date.');
                    }

                    $logDate = substr((string) $pulData['date'], 0, 10);

                    $existingLog = PhoneUsageLog::where('user_id', $user->id)
                        ->whereDate('date', $logDate)
                        ->first();

                    if (! $existingLog) {
                        PhoneUsageLog::create([
                            'user_id' => $user->id,
                            'date' => $logDate,
                            'minutes' => max(0, (int) ($pulData['minutes'] ?? 0)),
                            'notes' => $pulData['notes'] ?? null,
                        ]);
                    }
                }

                // 11. Focus Sessions
                foreach ($payload['focus_sessions'] ?? [] as $fsData) {
                    if (! is_array($fsData) || empty($fsData['started_at'])) {
                        throw new \RuntimeException('Focus session missing required started_at.');
                    }

                    $mappedTaskId = isset($fsData['task_id']) ? ($taskMap[$fsData['task_id']] ?? null) : null;

                    $existingSession = FocusSession::where('user_id', $user->id)
                        ->where('started_at', $fsData['started_at'])
                        ->first();

                    if (! $existingSession) {
                        FocusSession::create([
                            'user_id' => $user->id,
                            'task_id' => $mappedTaskId,
                            'mode' => $fsData['mode'] ?? 'focus',
                            'status' => $fsData['status'] ?? 'completed',
                            'started_at' => $fsData['started_at'],
                            'last_resumed_at' => $fsData['last_resumed_at'] ?? null,
                            'paused_at' => $fsData['paused_at'] ?? null,
                            'ended_at' => $fsData['ended_at'] ?? null,
                            'planned_minutes' => (int) ($fsData['planned_minutes'] ?? 25),
                            'elapsed_seconds' => (int) ($fsData['elapsed_seconds'] ?? $fsData['duration_seconds'] ?? 0),
                            'logged_minutes' => (int) ($fsData['logged_minutes'] ?? 0),
                            'notes' => $fsData['notes'] ?? null,
                        ]);
                    }
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }

        return back()->with('success', 'Data imported successfully.');
    }
}
