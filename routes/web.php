<?php

use App\Http\Controllers\BadHabitController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommandPaletteController;
use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataExportImportController;
use App\Http\Controllers\FocusSessionController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PhoneUsageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WeeklyReviewController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => config('auth.allow_registration', false),
    ]);
})->name('welcome');

// Token-authenticated private .ics calendar subscription feed (no session cookie required)
Route::get('/calendar/feed/{token}.ics', [CalendarFeedController::class, 'feed'])
    ->where('token', '[A-Za-z0-9_-]+')
    ->name('calendar.feed');

Route::middleware(['auth', 'verified'])->group(function (): void {
    // 1. Dashboard & Briefings
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/briefings', [BriefingController::class, 'index'])->name('briefings.index');
    Route::post('/briefings/send-daily', [BriefingController::class, 'sendDaily'])->name('briefings.send-daily');
    Route::post('/briefings/send-weekly', [BriefingController::class, 'sendWeekly'])->name('briefings.send-weekly');

    // 2. My Day (Daily Planning)
    Route::get('/my-day', [DailyPlanController::class, 'index'])->name('my-day.index');
    Route::put('/my-day/{dailyPlan}', [DailyPlanController::class, 'updatePlan'])->name('my-day.update');
    Route::post('/my-day/{dailyPlan}/tasks', [DailyPlanController::class, 'addTask'])->name('my-day.add-task');
    Route::post('/my-day/{dailyPlan}/tasks/{task}/priority', [DailyPlanController::class, 'togglePriority'])->name('my-day.toggle-priority');
    Route::delete('/my-day/{dailyPlan}/tasks/{task}', [DailyPlanController::class, 'removeTask'])->name('my-day.remove-task');
    Route::post('/my-day/{dailyPlan}/reorder', [DailyPlanController::class, 'reorder'])->name('my-day.reorder');
    Route::post('/tasks/{task}/reschedule', [DailyPlanController::class, 'rescheduleTask'])->name('tasks.reschedule');

    // 3. Tasks & Kanban
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.update-status');
    Route::post('/tasks/{task}/toggle', [TaskController::class, 'toggleStatus'])->name('tasks.toggle');
    Route::post('/tasks/{task}/duplicate', [TaskController::class, 'duplicate'])->name('tasks.duplicate');
    Route::post('/tasks/bulk', [TaskController::class, 'bulkUpdate'])->name('tasks.bulk');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // 4. Categories & Areas
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('/categories/{category}/archive', [CategoryController::class, 'toggleArchive'])->name('categories.archive');
    Route::post('/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // 5. Projects
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/projects/{project}/milestones', [ProjectController::class, 'storeMilestone'])->name('projects.milestones.store');
    Route::post('/milestones/{milestone}/toggle', [ProjectController::class, 'toggleMilestone'])->name('milestones.toggle');
    Route::delete('/milestones/{milestone}', [ProjectController::class, 'destroyMilestone'])->name('milestones.destroy');

    // 6. Calendar & .ics Export / Feed Tokens
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/export.ics', [CalendarFeedController::class, 'export'])->name('calendar.export-ics');
    Route::post('/calendar/feed/token', [CalendarFeedController::class, 'generateToken'])->name('calendar.feed.generate');
    Route::delete('/calendar/feed/token', [CalendarFeedController::class, 'revokeToken'])->name('calendar.feed.revoke');
    Route::post('/calendar/events', [CalendarController::class, 'storeEvent'])->name('calendar.events.store');
    Route::put('/calendar/events/{event}', [CalendarController::class, 'updateEvent'])->name('calendar.events.update');
    Route::delete('/calendar/events/{event}', [CalendarController::class, 'destroyEvent'])->name('calendar.events.destroy');
    Route::post('/calendar/tasks/{task}/move', [CalendarController::class, 'moveTask'])->name('calendar.tasks.move');

    // 7. Habits & Bad Habits
    Route::get('/habits', [HabitController::class, 'index'])->name('habits.index');
    Route::post('/habits', [HabitController::class, 'store'])->name('habits.store');
    Route::put('/habits/{habit}', [HabitController::class, 'update'])->name('habits.update');
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy'])->name('habits.destroy');
    Route::post('/habits/{habit}/check-in', [HabitController::class, 'checkIn'])->name('habits.check-in');

    Route::get('/bad-habits', [BadHabitController::class, 'index'])->name('bad-habits.index');
    Route::post('/bad-habits', [BadHabitController::class, 'store'])->name('bad-habits.store');
    Route::put('/bad-habits/{badHabit}', [BadHabitController::class, 'update'])->name('bad-habits.update');
    Route::post('/bad-habits/{badHabit}/check-in', [BadHabitController::class, 'checkIn'])->name('bad-habits.check-in');
    Route::post('/bad-habits/{badHabit}/restore', [BadHabitController::class, 'restore'])->name('bad-habits.restore');
    Route::delete('/bad-habits/{badHabit}', [BadHabitController::class, 'destroy'])->name('bad-habits.destroy');

    // 8. Weekly Review
    Route::get('/weekly-review', [WeeklyReviewController::class, 'index'])->name('weekly-review.index');
    Route::put('/weekly-review/{weeklyReview}', [WeeklyReviewController::class, 'update'])->name('weekly-review.update');

    // 9. Phone Usage Tracker
    Route::post('/phone-usage', [PhoneUsageController::class, 'store'])->name('phone-usage.store');

    // 10. Notifications & Reminder Actions
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/{id}/complete-task', [NotificationController::class, 'completeTask'])->name('notifications.complete-task');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/reminders/{reminder}/snooze', [NotificationController::class, 'snooze'])->name('reminders.snooze');
    Route::post('/reminders/{reminder}/dismiss', [NotificationController::class, 'dismiss'])->name('reminders.dismiss');

    // 11. Focus / Pomodoro Timer
    Route::get('/focus-sessions/status', [FocusSessionController::class, 'status'])->name('focus.status');
    Route::post('/focus-sessions/start', [FocusSessionController::class, 'start'])->name('focus.start');
    Route::post('/focus-sessions/{focusSession}/pause', [FocusSessionController::class, 'pause'])->name('focus.pause');
    Route::post('/focus-sessions/{focusSession}/resume', [FocusSessionController::class, 'resume'])->name('focus.resume');
    Route::post('/focus-sessions/{focusSession}/reset', [FocusSessionController::class, 'reset'])->name('focus.reset');
    Route::post('/focus-sessions/{focusSession}/complete', [FocusSessionController::class, 'complete'])->name('focus.complete');
    Route::post('/focus-sessions/{focusSession}/stop', [FocusSessionController::class, 'stop'])->name('focus.stop');
    Route::patch('/tasks/{task}/actual-duration', [FocusSessionController::class, 'adjustTaskDuration'])->name('tasks.adjust-duration');

    // 12. Global Command Palette Search
    Route::get('/command-palette/search', [CommandPaletteController::class, 'search'])->name('command-palette.search');

    // 13. Settings & Data Management / Automated Backups
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/data', [DataExportImportController::class, 'index'])->name('settings.data');
    Route::get('/settings/export/json', [DataExportImportController::class, 'exportJson'])->name('settings.export.json');
    Route::get('/settings/export/tasks-csv', [DataExportImportController::class, 'exportTasksCsv'])->name('settings.export.tasks-csv');
    Route::get('/settings/backup/database', [DataExportImportController::class, 'downloadDatabaseBackup'])->name('settings.backup.database');
    Route::post('/settings/backups/create', [DataExportImportController::class, 'createManualBackup'])->name('settings.backups.create');
    Route::get('/settings/backups/{filename}/download', [DataExportImportController::class, 'downloadManagedBackup'])
        ->where('filename', '[A-Za-z0-9._-]+')
        ->name('settings.backups.download');
    Route::delete('/settings/backups/{filename}', [DataExportImportController::class, 'deleteManagedBackup'])
        ->where('filename', '[A-Za-z0-9._-]+')
        ->name('settings.backups.delete');
    Route::post('/settings/backups/restore', [DataExportImportController::class, 'restoreManagedBackup'])->name('settings.backups.restore');
    Route::post('/settings/import/preview', [DataExportImportController::class, 'previewImport'])->name('settings.import.preview');
    Route::post('/settings/import/process', [DataExportImportController::class, 'processImport'])->name('settings.import.process');

    // Standard profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
