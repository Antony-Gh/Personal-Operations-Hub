<?php

namespace App\Http\Controllers;

use App\Models\BadHabit;
use App\Models\BadHabitCheckIn;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Services\BadHabitAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BadHabitController extends Controller
{
    public function index(Request $request, BadHabitAnalyticsService $analytics): Response
    {
        $user = $request->user();
        $today = $user->localToday();
        $todayStr = $today->toDateString();

        $badHabits = BadHabit::with(['checkIns', 'linkedHabit:id,name,category'])
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        $availableHabits = Habit::where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'category']);

        $todayLinkedHabitCheckIns = HabitCheckIn::where('user_id', $user->id)
            ->where('date', $todayStr)
            ->get()
            ->keyBy('habit_id');

        $archivedBadHabits = BadHabit::where('user_id', $user->id)
            ->where('is_archived', true)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'name', 'category', 'replacement_action']);

        $pastDates = collect();
        for ($i = 13; $i >= 0; $i--) {
            $pastDates->push($today->copy()->subDays($i)->toDateString());
        }

        $badHabitsData = $badHabits->map(function (BadHabit $badHabit) use ($pastDates, $todayStr, $analytics, $user, $todayLinkedHabitCheckIns): array {
            $byDate = $badHabit->checkIns->keyBy(fn (BadHabitCheckIn $ci): string => Carbon::parse($ci->date)->toDateString());

            $history = $pastDates->map(function (string $d) use ($byDate): array {
                $ci = $byDate->get($d);

                return [
                    'date' => $d,
                    'status' => $ci ? $ci->status : 'none',
                    'observed_value' => $ci?->observed_value,
                    'trigger_note' => $ci?->trigger_note,
                    'notes' => $ci?->notes,
                ];
            });

            $metrics = $analytics->calculateMetrics($badHabit, $user, $badHabit->checkIns);
            $todayCI = $byDate->get($todayStr);
            $linkedHabitStatus = $badHabit->linked_habit_id
                ? ($todayLinkedHabitCheckIns->get($badHabit->linked_habit_id)?->status ?? 'pending')
                : null;

            return [
                'id' => $badHabit->id,
                'name' => $badHabit->name,
                'description' => $badHabit->description,
                'category' => $badHabit->category,
                'trigger_cue' => $badHabit->trigger_cue,
                'replacement_action' => $badHabit->replacement_action,
                'linked_habit_id' => $badHabit->linked_habit_id,
                'linked_habit' => $badHabit->linkedHabit ? [
                    'id' => $badHabit->linkedHabit->id,
                    'name' => $badHabit->linkedHabit->name,
                    'category' => $badHabit->linkedHabit->category,
                    'today_status' => $linkedHabitStatus,
                ] : null,
                'minutes_saved_per_day' => $badHabit->minutes_saved_per_day,
                'money_saved_per_day' => $badHabit->money_saved_per_day,
                'minutes_reclaimed' => $metrics['minutes_reclaimed'],
                'money_reclaimed' => $metrics['money_reclaimed'],
                'has_daily_limit' => $badHabit->has_daily_limit,
                'daily_limit_value' => $badHabit->daily_limit_value,
                'daily_limit_unit' => $badHabit->daily_limit_unit,
                'target_clean_days_per_week' => $badHabit->target_clean_days_per_week,
                'today_status' => $todayCI ? $todayCI->status : 'none',
                'today_observed_value' => $todayCI?->observed_value,
                'today_trigger_note' => $todayCI?->trigger_note,
                'today_notes' => $todayCI?->notes,
                'current_clean_streak' => $metrics['current_clean_streak'],
                'best_clean_streak' => $metrics['best_clean_streak'],
                'total_clean_days' => $metrics['total_clean_days'],
                'minor_slips_count' => $metrics['minor_slips_count'],
                'slips_count' => $metrics['slips_count'],
                'clean_in_7_days' => $metrics['clean_in_7_days'],
                'weekly_clean_rate' => $metrics['weekly_clean_rate'],
                'monthly_clean_rate' => $metrics['monthly_clean_rate'],
                'overall_clean_percentage' => $metrics['overall_clean_percentage'],
                'recent_reflections' => $metrics['recent_reflections'],
                'history' => $history->values(),
            ];
        });

        $heatmap = $analytics->buildMonthlyHeatmap($user, $request->input('heatmap_month'));
        $triggerAnalytics = $analytics->buildTriggerAnalytics($user);

        $totalMinutesReclaimed = (int) $badHabitsData->sum('minutes_reclaimed');
        $totalMoneyReclaimed = round((float) $badHabitsData->sum('money_reclaimed'), 2);

        $summary = [
            'top_clean_streak' => (int) ($badHabitsData->max('current_clean_streak') ?? 0),
            'all_time_best_clean_streak' => (int) ($badHabitsData->max('best_clean_streak') ?? 0),
            'average_clean_percentage' => $badHabitsData->isNotEmpty()
                ? (int) round($badHabitsData->avg('overall_clean_percentage'))
                : 0,
            'average_weekly_clean_rate' => $badHabitsData->isNotEmpty()
                ? (int) round($badHabitsData->avg('weekly_clean_rate'))
                : 0,
            'stayed_clean_today_count' => $badHabitsData->where('today_status', 'clean')->count(),
            'slipped_today_count' => $badHabitsData->whereIn('today_status', ['minor_slip', 'slipped'])->count(),
            'total_active_bad_habits' => $badHabitsData->count(),
            'total_minutes_reclaimed' => $totalMinutesReclaimed,
            'total_hours_reclaimed' => round($totalMinutesReclaimed / 60, 1),
            'total_money_reclaimed' => $totalMoneyReclaimed,
        ];

        return Inertia::render('BadHabits/Index', [
            'badHabits' => $badHabitsData,
            'availableHabits' => $availableHabits,
            'archivedBadHabits' => $archivedBadHabits,
            'summary' => $summary,
            'heatmap' => $heatmap,
            'triggerAnalytics' => $triggerAnalytics,
            'todayDate' => $todayStr,
            'pastDates' => $pastDates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBadHabit($request);
        $user = $request->user();
        $maxOrder = BadHabit::where('user_id', $user->id)->max('sort_order') ?? 0;

        $hasLimit = (bool) ($validated['has_daily_limit'] ?? false);

        BadHabit::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?: 'Digital Discipline',
            'trigger_cue' => $validated['trigger_cue'] ?? null,
            'replacement_action' => $validated['replacement_action'] ?? null,
            'linked_habit_id' => $validated['linked_habit_id'] ?? null,
            'minutes_saved_per_day' => (int) ($validated['minutes_saved_per_day'] ?? 0),
            'money_saved_per_day' => round((float) ($validated['money_saved_per_day'] ?? 0), 2),
            'has_daily_limit' => $hasLimit,
            'daily_limit_value' => $hasLimit ? (int) ($validated['daily_limit_value'] ?? 0) : null,
            'daily_limit_unit' => $hasLimit ? ($validated['daily_limit_unit'] ?? 'times') : 'times',
            'target_clean_days_per_week' => (int) ($validated['target_clean_days_per_week'] ?? 7),
            'sort_order' => $maxOrder + 1,
            'is_archived' => false,
        ]);

        return back()->with('success', 'Bad habit added to your break list.');
    }

    public function update(Request $request, BadHabit $badHabit): RedirectResponse
    {
        $this->authorize('update', $badHabit);
        $validated = $this->validateBadHabit($request);
        $hasLimit = (bool) ($validated['has_daily_limit'] ?? false);

        $badHabit->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?: 'Digital Discipline',
            'trigger_cue' => $validated['trigger_cue'] ?? null,
            'replacement_action' => $validated['replacement_action'] ?? null,
            'linked_habit_id' => $validated['linked_habit_id'] ?? null,
            'minutes_saved_per_day' => (int) ($validated['minutes_saved_per_day'] ?? 0),
            'money_saved_per_day' => round((float) ($validated['money_saved_per_day'] ?? 0), 2),
            'has_daily_limit' => $hasLimit,
            'daily_limit_value' => $hasLimit ? (int) ($validated['daily_limit_value'] ?? 0) : null,
            'daily_limit_unit' => $hasLimit ? ($validated['daily_limit_unit'] ?? 'times') : 'times',
            'target_clean_days_per_week' => (int) ($validated['target_clean_days_per_week'] ?? 7),
        ]);

        return back()->with('success', 'Bad habit updated.');
    }

    public function checkIn(Request $request, BadHabit $badHabit): RedirectResponse
    {
        $this->authorize('update', $badHabit);

        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'nullable|in:clean,minor_slip,slipped',
            'observed_value' => 'nullable|integer|min:0|max:100000',
            'trigger_note' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'log_replacement' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $dateStr = Carbon::parse($validated['date'])->toDateString();
        $existing = BadHabitCheckIn::where('bad_habit_id', $badHabit->id)
            ->where('date', $dateStr)
            ->first();

        $observedValue = array_key_exists('observed_value', $validated)
            ? $validated['observed_value']
            : $existing?->observed_value;

        $resolvedStatus = $validated['status'] ?? null;

        if ($resolvedStatus === null) {
            if ($badHabit->has_daily_limit && $observedValue !== null && $badHabit->daily_limit_value !== null) {
                $resolvedStatus = ((int) $observedValue <= (int) $badHabit->daily_limit_value)
                    ? 'clean'
                    : 'slipped';
            } else {
                $resolvedStatus = 'clean';
            }
        }

        $triggerNote = array_key_exists('trigger_note', $validated)
            ? $validated['trigger_note']
            : $existing?->trigger_note;

        $notes = array_key_exists('notes', $validated)
            ? $validated['notes']
            : $existing?->notes;

        BadHabitCheckIn::updateOrCreate(
            [
                'user_id' => $user->id,
                'bad_habit_id' => $badHabit->id,
                'date' => $dateStr,
            ],
            [
                'status' => $resolvedStatus,
                'observed_value' => $observedValue,
                'trigger_note' => $triggerNote,
                'notes' => $notes,
            ]
        );

        if ($resolvedStatus === 'clean' && ! empty($validated['log_replacement']) && $badHabit->linked_habit_id !== null) {
            $linkedHabit = Habit::where('id', $badHabit->linked_habit_id)
                ->where('user_id', $user->id)
                ->first();

            if ($linkedHabit) {
                HabitCheckIn::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'habit_id' => $linkedHabit->id,
                        'date' => $dateStr,
                    ],
                    [
                        'status' => 'completed',
                        'notes' => "Completed via healthy replacement for \"{$badHabit->name}\".",
                    ]
                );
            }
        }

        return back()->with('success', 'Check-in recorded.');
    }

    public function destroy(BadHabit $badHabit): RedirectResponse
    {
        $this->authorize('delete', $badHabit);

        $badHabit->update(['is_archived' => true]);

        return back()->with('success', 'Bad habit archived.');
    }

    public function restore(BadHabit $badHabit): RedirectResponse
    {
        $this->authorize('update', $badHabit);

        $badHabit->update(['is_archived' => false]);

        return back()->with('success', 'Bad habit restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBadHabit(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100',
            'trigger_cue' => 'nullable|string|max:255',
            'replacement_action' => 'nullable|string|max:255',
            'linked_habit_id' => [
                'nullable',
                'integer',
                Rule::exists('habits', 'id')->where('user_id', $user->id),
            ],
            'minutes_saved_per_day' => 'nullable|integer|min:0|max:1440',
            'money_saved_per_day' => 'nullable|numeric|min:0|max:100000',
            'has_daily_limit' => 'nullable|boolean',
            'daily_limit_value' => 'nullable|required_if:has_daily_limit,true|integer|min:0|max:10000',
            'daily_limit_unit' => 'nullable|in:times,minutes',
            'target_clean_days_per_week' => 'required|integer|min:1|max:7',
        ]);
    }
}
