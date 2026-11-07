<?php

namespace App\Http\Controllers;

use App\Models\FocusSession;
use App\Models\Task;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Support\UserDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FocusSessionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $localToday = UserDateTime::todayForUser($user);

        /** @var FocusSession|null $active */
        $active = FocusSession::with('task:id,title,estimated_duration,actual_duration')
            ->where('user_id', $user->id)
            ->whereIn('status', ['running', 'paused'])
            ->latest('updated_at')
            ->first();

        $todaySessions = FocusSession::where('user_id', $user->id)
            ->where('mode', 'focus')
            ->whereBetween('started_at', [
                $localToday->copy()->startOfDay()->utc(),
                $localToday->copy()->endOfDay()->utc(),
            ])
            ->get();

        $todayMinutes = (int) $todaySessions->sum(
            fn (FocusSession $s): int => max((int) $s->logged_minutes, intdiv($s->currentElapsedSeconds(), 60))
        );

        return response()->json([
            'session' => $active ? $this->serializeSession($active) : null,
            'today_focus_minutes' => $todayMinutes,
            'today_completed_sessions' => $todaySessions->where('status', 'completed')->count(),
        ]);
    }

    public function start(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $setting = $user->ensureSetting();

        $validated = $request->validate([
            'mode' => 'required|in:focus,short_break,long_break',
            'planned_minutes' => 'nullable|integer|min:1|max:240',
            'task_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where('user_id', $user->id),
            ],
            'notes' => 'nullable|string|max:500',
        ]);

        $defaultMinutes = match ($validated['mode']) {
            'short_break' => $setting->focus_short_break_minutes ?: 5,
            'long_break' => $setting->focus_long_break_minutes ?: 15,
            default => $setting->focus_work_minutes ?: 25,
        };

        $plannedMinutes = (int) ($validated['planned_minutes'] ?? $defaultMinutes);

        $session = DB::transaction(function () use ($user, $validated, $plannedMinutes): FocusSession {
            $existingActive = FocusSession::where('user_id', $user->id)
                ->whereIn('status', ['running', 'paused'])
                ->lockForUpdate()
                ->get();

            foreach ($existingActive as $old) {
                $elapsed = $old->currentElapsedSeconds();
                $old->update([
                    'status' => 'stopped',
                    'elapsed_seconds' => $elapsed,
                    'last_resumed_at' => null,
                    'ended_at' => now(),
                ]);
                $this->syncTaskLoggedMinutes($old);
            }

            $now = now();

            return FocusSession::create([
                'user_id' => $user->id,
                'task_id' => $validated['task_id'] ?? null,
                'mode' => $validated['mode'],
                'planned_minutes' => $plannedMinutes,
                'elapsed_seconds' => 0,
                'logged_minutes' => 0,
                'status' => 'running',
                'started_at' => $now,
                'last_resumed_at' => $now,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $session->load('task:id,title,estimated_duration,actual_duration');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'session' => $this->serializeSession($session),
            ]);
        }

        return back()->with('success', 'Focus timer started.');
    }

    public function pause(Request $request, FocusSession $focusSession): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $focusSession);

        if ($focusSession->status === 'running') {
            $now = now();
            $elapsed = $focusSession->currentElapsedSeconds($now);

            $focusSession->update([
                'status' => 'paused',
                'elapsed_seconds' => $elapsed,
                'paused_at' => $now,
                'last_resumed_at' => null,
            ]);

            $this->syncTaskLoggedMinutes($focusSession);
        }

        $focusSession->load('task:id,title,estimated_duration,actual_duration');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'session' => $this->serializeSession($focusSession),
            ]);
        }

        return back()->with('success', 'Timer paused.');
    }

    public function resume(Request $request, FocusSession $focusSession): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $focusSession);

        if ($focusSession->status === 'paused') {
            $focusSession->update([
                'status' => 'running',
                'last_resumed_at' => now(),
                'paused_at' => null,
            ]);
        }

        $focusSession->load('task:id,title,estimated_duration,actual_duration');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'session' => $this->serializeSession($focusSession),
            ]);
        }

        return back()->with('success', 'Timer resumed.');
    }

    public function reset(Request $request, FocusSession $focusSession): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $focusSession);

        $now = now();
        $focusSession->update([
            'status' => 'paused',
            'elapsed_seconds' => 0,
            'logged_minutes' => 0,
            'started_at' => $now,
            'paused_at' => $now,
            'last_resumed_at' => null,
            'ended_at' => null,
        ]);

        $focusSession->load('task:id,title,estimated_duration,actual_duration');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'session' => $this->serializeSession($focusSession),
            ]);
        }

        return back()->with('success', 'Timer reset.');
    }

    public function complete(Request $request, FocusSession $focusSession): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $focusSession);

        $reachedTarget = $request->boolean('reached_target', true);

        DB::transaction(function () use ($focusSession, $reachedTarget): void {
            if (in_array($focusSession->status, ['completed', 'stopped'], true)) {
                return;
            }

            $now = now();
            $measured = $focusSession->currentElapsedSeconds($now);
            $finalSeconds = $reachedTarget
                ? max($measured, ((int) $focusSession->planned_minutes) * 60)
                : $measured;

            $focusSession->update([
                'status' => 'completed',
                'elapsed_seconds' => $finalSeconds,
                'last_resumed_at' => null,
                'paused_at' => null,
                'ended_at' => $now,
            ]);

            $this->syncTaskLoggedMinutes($focusSession);

            if ($focusSession->user !== null) {
                $this->notifySessionCompleted($focusSession->user, $focusSession);
            }
        });

        $focusSession->refresh()->load('task:id,title,estimated_duration,actual_duration');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'session' => $this->serializeSession($focusSession),
            ]);
        }

        return back()->with('success', 'Focus session completed.');
    }

    public function stop(Request $request, FocusSession $focusSession): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $focusSession);

        DB::transaction(function () use ($focusSession): void {
            if (in_array($focusSession->status, ['completed', 'stopped'], true)) {
                return;
            }

            $now = now();
            $elapsed = $focusSession->currentElapsedSeconds($now);

            $focusSession->update([
                'status' => 'stopped',
                'elapsed_seconds' => $elapsed,
                'last_resumed_at' => null,
                'paused_at' => null,
                'ended_at' => $now,
            ]);

            $this->syncTaskLoggedMinutes($focusSession);
        });

        $focusSession->refresh()->load('task:id,title,estimated_duration,actual_duration');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'session' => $this->serializeSession($focusSession),
            ]);
        }

        return back()->with('success', 'Focus session stopped.');
    }

    public function adjustTaskDuration(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'actual_duration' => 'required|integer|min:0|max:100000',
        ]);

        $task->update([
            'actual_duration' => $validated['actual_duration'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'task_id' => $task->id,
                'actual_duration' => $task->actual_duration,
            ]);
        }

        return back()->with('success', 'Task actual duration updated.');
    }

    private function syncTaskLoggedMinutes(FocusSession $session): void
    {
        if ($session->mode !== 'focus' || $session->task_id === null) {
            return;
        }

        $totalMinutesEarned = intdiv(max(0, (int) $session->elapsed_seconds), 60);
        $deltaMinutes = $totalMinutesEarned - (int) $session->logged_minutes;

        if ($deltaMinutes <= 0) {
            return;
        }

        $task = Task::where('user_id', $session->user_id)
            ->whereKey($session->task_id)
            ->lockForUpdate()
            ->first();

        if ($task !== null) {
            $task->update([
                'actual_duration' => ((int) ($task->actual_duration ?? 0)) + $deltaMinutes,
            ]);
        }

        $session->update([
            'logged_minutes' => $totalMinutesEarned,
        ]);
    }

    private function notifySessionCompleted(User $user, FocusSession $session): void
    {
        $setting = $user->ensureSetting();
        if (! $setting->isNotificationCategoryEnabled('focus_timer')) {
            return;
        }

        if (UserDateTime::isInQuietHours($user)) {
            return;
        }

        $dedupKey = "focus_session:{$session->id}:completed";
        if ($user->notifications()->where('data->dedup_key', $dedupKey)->exists()) {
            return;
        }

        $task = $session->task;
        $label = match ($session->mode) {
            'short_break' => 'Short Break Finished',
            'long_break' => 'Long Break Finished',
            default => 'Focus Session Complete',
        };

        $minutes = max(1, intdiv((int) $session->elapsed_seconds, 60));
        $message = $task !== null
            ? "{$minutes}m logged on \"{$task->title}\"."
            : "{$minutes}m {$session->mode} session completed.";

        $user->notify(new OperationalNotification(
            category: 'focus_timer',
            title: $label,
            message: $message,
            actionUrl: $task !== null ? '/tasks?search='.urlencode($task->title) : '/dashboard',
            entityType: $task !== null ? 'task' : 'focus_session',
            entityId: $task?->id ?? $session->id,
            dedupKey: $dedupKey,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(FocusSession $session): array
    {
        $now = now();

        return [
            'id' => $session->id,
            'task_id' => $session->task_id,
            'task' => $session->task ? [
                'id' => $session->task->id,
                'title' => $session->task->title,
                'estimated_duration' => $session->task->estimated_duration,
                'actual_duration' => $session->task->actual_duration,
            ] : null,
            'mode' => $session->mode,
            'planned_minutes' => $session->planned_minutes,
            'elapsed_seconds' => $session->currentElapsedSeconds($now),
            'remaining_seconds' => $session->remainingSeconds($now),
            'logged_minutes' => $session->logged_minutes,
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'last_resumed_at' => $session->last_resumed_at?->toIso8601String(),
            'server_now_ms' => (int) round(microtime(true) * 1000),
        ];
    }
}
