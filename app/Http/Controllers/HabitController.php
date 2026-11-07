<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Services\HabitAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HabitController extends Controller
{
    public function index(Request $request, HabitAnalyticsService $habitAnalytics): Response
    {
        $user = $request->user();
        $today = $user->localToday();

        // Active habits with all check-ins for streak accuracy
        $habits = Habit::with('checkIns')
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        // Past 14 days dates in user's local timezone
        $pastDates = collect();
        for ($i = 13; $i >= 0; $i--) {
            $pastDates->push($today->copy()->subDays($i)->toDateString());
        }

        $habitsData = $habits->map(function (Habit $habit) use ($pastDates, $today, $habitAnalytics, $user): array {
            $byDate = $habit->checkIns->keyBy(fn (HabitCheckIn $ci): string => Carbon::parse($ci->date)->toDateString());

            $history = $pastDates->map(function (string $d) use ($byDate): array {
                $ci = $byDate->get($d);

                return [
                    'date' => $d,
                    'status' => $ci ? $ci->status : 'none',
                    'notes' => $ci ? $ci->notes : null,
                ];
            });

            $metrics = $habitAnalytics->calculateHabitMetrics($habit, $user, $habit->checkIns);
            $todayCI = $byDate->get($today->toDateString());

            return [
                'id' => $habit->id,
                'name' => $habit->name,
                'description' => $habit->description,
                'category' => $habit->category,
                'frequency' => $habit->frequency,
                'target_days_per_week' => $habit->target_days_per_week,
                'today_status' => $todayCI ? $todayCI->status : 'none',
                'today_notes' => $todayCI ? $todayCI->notes : null,
                'completed_in_7_days' => $metrics['completed_in_7_days'],
                'current_streak' => $metrics['current_streak'],
                'best_streak' => $metrics['best_streak'],
                'total_completed_days' => $metrics['total_completed_days'],
                'weekly_consistency' => $metrics['weekly_consistency'],
                'monthly_consistency' => $metrics['monthly_consistency'],
                'completion_percentage' => $metrics['completion_percentage'],
                'history' => $history->values(),
            ];
        });

        $heatmap = $habitAnalytics->buildMonthlyHeatmap($user, $request->input('heatmap_month'));

        $summary = [
            'top_current_streak' => (int) ($habitsData->max('current_streak') ?? 0),
            'all_time_best_streak' => (int) ($habitsData->max('best_streak') ?? 0),
            'average_completion_percentage' => $habitsData->isNotEmpty()
                ? (int) round($habitsData->avg('completion_percentage'))
                : 0,
            'average_weekly_consistency' => $habitsData->isNotEmpty()
                ? (int) round($habitsData->avg('weekly_consistency'))
                : 0,
            'completed_today_count' => $habitsData->whereIn('today_status', ['completed', 'partial'])->count(),
            'total_active_habits' => $habitsData->count(),
        ];

        return Inertia::render('Habits/Index', [
            'habits' => $habitsData,
            'summary' => $summary,
            'heatmap' => $heatmap,
            'todayDate' => $today->toDateString(),
            'pastDates' => $pastDates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'frequency' => 'required|in:daily,weekly',
            'target_days_per_week' => 'required|integer|min:1|max:7',
        ]);

        $user = $request->user();
        $maxOrder = Habit::where('user_id', $user->id)->max('sort_order') ?? 0;

        Habit::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? 'Spiritual Life',
            'frequency' => $validated['frequency'],
            'target_days_per_week' => $validated['target_days_per_week'],
            'sort_order' => $maxOrder + 1,
            'is_archived' => false,
        ]);

        return back()->with('success', 'Habit created successfully.');
    }

    public function update(Request $request, Habit $habit): RedirectResponse
    {
        $this->authorize('update', $habit);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'frequency' => 'required|in:daily,weekly',
            'target_days_per_week' => 'required|integer|min:1|max:7',
        ]);

        $habit->update($validated);

        return back()->with('success', 'Habit updated.');
    }

    public function checkIn(Request $request, Habit $habit): RedirectResponse
    {
        $this->authorize('update', $habit);

        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|in:completed,partial,not_completed',
            'notes' => 'nullable|string',
        ]);

        HabitCheckIn::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'habit_id' => $habit->id,
                'date' => $validated['date'],
            ],
            [
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Check-in saved.');
    }

    public function destroy(Habit $habit): RedirectResponse
    {
        $this->authorize('delete', $habit);

        $habit->update(['is_archived' => true]);

        return back()->with('success', 'Habit archived.');
    }
}
