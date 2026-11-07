<?php

namespace App\Http\Middleware;

use App\Models\FocusSession;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user !== null) {
            if (Schema::hasTable('user_settings')) {
                $user->ensureSetting();
                $user->load('setting');
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'categories' => $user ? $user->categories()->active()->ordered()->get() : [],
            'tags' => $user ? $user->tags()->orderBy('name')->get() : [],
            'notificationsSummary' => fn (): array => $this->buildNotificationsSummary($user),
            'activeFocusSession' => fn (): ?array => $this->buildActiveFocusSession($user),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * @return array{unread_count: int, items: array<int, array<string, mixed>>}
     */
    private function buildNotificationsSummary(mixed $user): array
    {
        if ($user === null || ! Schema::hasTable('notifications')) {
            return [
                'unread_count' => 0,
                'items' => [],
            ];
        }

        $unreadCount = $user->unreadNotifications()->count();
        $items = $user->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'unread_count' => $unreadCount,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildActiveFocusSession(mixed $user): ?array
    {
        if ($user === null || ! Schema::hasTable('focus_sessions')) {
            return null;
        }

        /** @var FocusSession|null $session */
        $session = FocusSession::with('task:id,title,estimated_duration,actual_duration')
            ->where('user_id', $user->id)
            ->whereIn('status', ['running', 'paused'])
            ->latest('updated_at')
            ->first();

        if ($session === null) {
            return null;
        }

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
