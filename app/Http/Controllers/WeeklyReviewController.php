<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\UserSetting;
use App\Models\WeeklyReview;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Target week
        $weekDate = $request->input('date', Carbon::today()->toDateString());
        $carbon = Carbon::parse($weekDate);

        // Compute start of week based on user setting (0 = Sun, 1 = Mon)
        $weekStartDay = $user->setting?->week_start ?? 1;
        $startOfWeek = $weekStartDay === 0
            ? $carbon->copy()->startOfWeek(Carbon::SUNDAY)
            : $carbon->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $startOfWeek->copy()->addDays(6);

        $startDateStr = $startOfWeek->toDateString();
        $endDateStr = $endOfWeek->toDateString();

        // Fetch or prepare current review
        $review = WeeklyReview::firstOrCreate(
            [
                'user_id' => $user->id,
                'week_start_date' => $startDateStr,
            ],
            [
                'week_end_date' => $endDateStr,
                'responses' => [],
                'notes' => '',
                'is_completed' => false,
            ]
        );

        // Prompts from user settings
        $prompts = $user->setting?->weekly_review_prompts ?? UserSetting::defaultPrompts();

        // Weekly Summary Metrics:
        // Tasks completed this week
        $completedTasks = Task::with('category')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfWeek->startOfDay(), $endOfWeek->endOfDay()])
            ->get();

        // Tasks currently overdue
        $overdueTasks = Task::with('category')
            ->where('user_id', $user->id)
            ->where('due_date', '<', Carbon::today()->toDateString())
            ->where('status', '!=', 'completed')
            ->get();

        // Upcoming deadlines next week
        $nextWeekEnd = $endOfWeek->copy()->addDays(7)->toDateString();
        $upcomingTasks = Task::with('category')
            ->where('user_id', $user->id)
            ->whereBetween('due_date', [$endDateStr, $nextWeekEnd])
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->get();

        // Past reviews list
        $pastReviews = WeeklyReview::where('user_id', $user->id)
            ->orderBy('week_start_date', 'desc')
            ->take(12)
            ->get();

        return Inertia::render('WeeklyReview/Index', [
            'review' => $review,
            'weekStart' => $startDateStr,
            'weekEnd' => $endDateStr,
            'formattedRange' => $startOfWeek->format('M d') . ' - ' . $endOfWeek->format('M d, Y'),
            'prompts' => $prompts,
            'completedTasks' => $completedTasks,
            'overdueTasks' => $overdueTasks,
            'upcomingTasks' => $upcomingTasks,
            'pastReviews' => $pastReviews,
        ]);
    }

    public function update(Request $request, WeeklyReview $weeklyReview): RedirectResponse
    {
        if ($weeklyReview->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'responses' => 'required|array',
            'notes' => 'nullable|string',
            'is_completed' => 'nullable|boolean',
        ]);

        $weeklyReview->update([
            'responses' => $validated['responses'],
            'notes' => $validated['notes'] ?? null,
            'is_completed' => $validated['is_completed'] ?? false,
        ]);

        return back()->with('success', 'Weekly review saved.');
    }
}

