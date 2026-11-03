<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\DailyPlan;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\UserSetting;
use App\Models\WeeklyReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/DataManagement');
    }

    public function exportJson(Request $request): StreamedResponse
    {
        $user = $request->user();

        $data = [
            'version' => '1.0',
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
            'weekly_reviews' => $user->weeklyReviews,
            'phone_usage_logs' => $user->phoneUsageLogs,
        ];

        $filename = 'personal-operations-hub-backup-' . now()->format('Y-m-d-His') . '.json';

        return response()->streamDownload(function () use ($data) {
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
            ->get();

        $filename = 'tasks-export-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tasks) {
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

        if (!file_exists($dbPath)) {
            return back()->with('error', 'Database file not found.');
        }

        $filename = 'database-backup-' . now()->format('Y-m-d-His') . '.sqlite';

        return response()->download($dbPath, $filename, [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    public function previewImport(Request $request): JsonResponse
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:json,txt',
        ]);

        $content = file_get_contents($request->file('backup_file')->getRealPath());
        $data = json_decode($content, true);

        if (!$data || !is_array($data) || !isset($data['version'])) {
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
            'habits_count' => count($data['habits'] ?? []),
            'calendar_events_count' => count($data['calendar_events'] ?? []),
            'weekly_reviews_count' => count($data['weekly_reviews'] ?? []),
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

        DB::transaction(function () use ($user, $payload, $mode) {
            if ($mode === 'replace') {
                // Safely clean existing user records
                $user->tasks()->forceDelete();
                $user->projects()->forceDelete();
                $user->categories()->forceDelete();
                $user->habits()->delete();
                $user->calendarEvents()->delete();
                $user->weeklyReviews()->delete();
                $user->dailyPlans()->delete();
            }

            $categoryMap = [];
            foreach ($payload['categories'] ?? [] as $catData) {
                $category = Category::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $catData['name']],
                    [
                        'color' => $catData['color'] ?? '#6366f1',
                        'icon' => $catData['icon'] ?? 'Folder',
                        'sort_order' => $catData['sort_order'] ?? 0,
                    ]
                );
                $categoryMap[$catData['id']] = $category->id;
            }

            $projectMap = [];
            foreach ($payload['projects'] ?? [] as $projData) {
                $newCatId = isset($projData['category_id']) ? ($categoryMap[$projData['category_id']] ?? null) : null;
                $project = Project::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $projData['name']],
                    [
                        'category_id' => $newCatId,
                        'description' => $projData['description'] ?? null,
                        'status' => $projData['status'] ?? 'in_progress',
                        'start_date' => $projData['start_date'] ?? null,
                        'target_date' => $projData['target_date'] ?? null,
                        'notes' => $projData['notes'] ?? null,
                    ]
                );
                $projectMap[$projData['id']] = $project->id;
            }

            foreach ($payload['tasks'] ?? [] as $tData) {
                $newCatId = isset($tData['category_id']) ? ($categoryMap[$tData['category_id']] ?? null) : null;
                $newProjId = isset($tData['project_id']) ? ($projectMap[$tData['project_id']] ?? null) : null;

                Task::firstOrCreate(
                    ['user_id' => $user->id, 'title' => $tData['title']],
                    [
                        'category_id' => $newCatId,
                        'project_id' => $newProjId,
                        'description' => $tData['description'] ?? null,
                        'priority' => $tData['priority'] ?? 'medium',
                        'status' => $tData['status'] ?? 'not_started',
                        'due_date' => $tData['due_date'] ?? null,
                        'start_date' => $tData['start_date'] ?? null,
                        'estimated_duration' => $tData['estimated_duration'] ?? null,
                        'actual_duration' => $tData['actual_duration'] ?? null,
                        'notes' => $tData['notes'] ?? null,
                        'next_action' => $tData['next_action'] ?? null,
                    ]
                );
            }
        });

        return back()->with('success', 'Data imported successfully.');
    }
}

