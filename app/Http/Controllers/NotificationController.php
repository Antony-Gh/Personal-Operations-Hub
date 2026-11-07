<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Task;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(5, min(50, (int) $request->input('per_page', 20)));

        $query = $user->notifications()->latest();

        if ($category = $request->input('category')) {
            $query->where('data->category', $category);
        }

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate($perPage);
        $latestUnread = $user->unreadNotifications()->latest()->first();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'latest_unread_id' => $latestUnread?->id,
            'items' => collect($paginator->items())->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'has_more' => $paginator->hasMorePages(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'Notification removed.');
    }

    public function clearAll(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->notifications()->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'Notification history cleared.');
    }

    public function snooze(Request $request, Reminder $reminder, ReminderService $reminderService): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $reminder);

        $validated = $request->validate([
            'preset' => 'required|in:15m,30m,1h,tomorrow,custom',
            'custom_minutes' => 'nullable|integer|min:1|max:10080',
            'notification_id' => 'nullable|string',
        ]);

        $updated = $reminderService->snoozeReminder(
            $reminder,
            $validated['preset'],
            $validated['custom_minutes'] ?? null
        );

        if (! empty($validated['notification_id'])) {
            $request->user()->notifications()->whereKey($validated['notification_id'])->update(['read_at' => now()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'reminder' => $updated,
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'Reminder snoozed.');
    }

    public function dismiss(Request $request, Reminder $reminder, ReminderService $reminderService): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $reminder);

        $updated = $reminderService->dismissReminder($reminder);

        if ($notificationId = $request->input('notification_id')) {
            $request->user()->notifications()->whereKey($notificationId)->update(['read_at' => now()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'reminder' => $updated,
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'Reminder dismissed.');
    }

    public function completeTask(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $data = $notification->data;

        if (($data['entity_type'] ?? null) !== 'task' || empty($data['entity_id'])) {
            abort(422, 'This notification is not linked to a task.');
        }

        $task = Task::where('user_id', $user->id)->whereKey((int) $data['entity_id'])->firstOrFail();

        if ($task->status !== 'completed') {
            $task->markAsCompleted();
        }

        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'task_id' => $task->id,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'Task marked as completed.');
    }
}
