<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitCheckIn;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HabitController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();

        // Active habits
        $habits = Habit::where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        // Get past 14 days dates
        $pastDates = collect();
        for ($i = 13; $i >= 0; $i--) {
            $pastDates->push($today->copy()->subDays($i)->toDateString());
        }

        // Fetch check-ins for the last 14 days
        $checkIns = HabitCheckIn::where('user_id', $user->id)
            ->whereIn('date', $pastDates)
            ->get()
            ->groupBy('habit_id');

        $habitsData = $habits->map(function ($habit) use ($checkIns, $pastDates, $today) {
            $habitCheckIns = $checkIns->get($habit->id, collect())->keyBy('date');

            $history = $pastDates->map(function ($d) use ($habitCheckIns) {
                $ci = $habitCheckIns->get($d);
                return [
                    'date' => $d,
                    'status' => $ci ? $ci->status : 'none', // completed, partial, not_completed, none
                    'notes' => $ci ? $ci->notes : null,
                ];
            });

            // Weekly completion count (last 7 days)
            $last7Days = $history->slice(7);
            $completedIn7Days = $last7Days->whereIn('status', ['completed', 'partial'])->count();

            $todayCI = $habitCheckIns->get($today->toDateString());

            return [
                'id' => $habit->id,
                'name' => $habit->name,
                'description' => $habit->description,
                'category' => $habit->category,
                'frequency' => $habit->frequency,
                'target_days_per_week' => $habit->target_days_per_week,
                'today_status' => $todayCI ? $todayCI->status : 'none',
                'today_notes' => $todayCI ? $todayCI->notes : null,
                'completed_in_7_days' => $completedIn7Days,
                'history' => $history->values(),
            ];
        });

        return Inertia::render('Habits/Index', [
            'habits' => $habitsData,
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

