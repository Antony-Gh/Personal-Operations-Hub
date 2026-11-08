<?php

namespace App\Services;

use App\Models\BadHabit;
use App\Models\BadHabitCheckIn;
use App\Models\FocusSession;
use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\PhoneUsageLog;
use App\Models\Task;
use App\Models\User;
use App\Support\UserDateTime;
use Carbon\Carbon;

class PersonalAnalyticsService
{
    /**
     * Assemble timezone-aware personal analytics:
     * 1. Task duration estimation accuracy (estimated_duration vs actual_duration)
     * 2. Deep-work focus hours grouped by Category / Area
     * 3. 6-week execution velocity trend
     * 4. 14-day correlation series between Phone Screen Time, Focus Minutes, and Habit Clean/Completion rates
     *
     * @return array<string, mixed>
     */
    public function buildAnalytics(User $user, int $rangeDays = 30): array
    {
        $days = in_array($rangeDays, [7, 14, 30, 90], true) ? $rangeDays : 30;
        $tz = UserDateTime::resolveTimezone($user);
        $localToday = UserDateTime::todayForUser($user);
        $windowStartLocal = $localToday->copy()->subDays($days - 1)->startOfDay();
        $windowStartUtc = $windowStartLocal->copy()->utc();
        $windowEndUtc = $localToday->copy()->endOfDay()->utc();

        // 1. Estimation Accuracy
        $evaluatedTasks = Task::with('category:id,name,color')
            ->where('user_id', $user->id)
            ->whereNotNull('estimated_duration')
            ->where('estimated_duration', '>', 0)
            ->whereNotNull('actual_duration')
            ->where('actual_duration', '>', 0)
            ->orderByDesc('updated_at')
            ->get();

        $underestimated = 0;
        $onTarget = 0;
        $overestimated = 0;
        $accuracyScores = [];
        $biases = [];
        $totalEstimated = 0;
        $totalActual = 0;

        $evaluatedRows = $evaluatedTasks->map(function (Task $t) use (
            &$underestimated,
            &$onTarget,
            &$overestimated,
            &$accuracyScores,
            &$biases,
            &$totalEstimated,
            &$totalActual
        ): array {
            $est = max(1, (int) $t->estimated_duration);
            $act = max(0, (int) $t->actual_duration);
            $delta = $act - $est;
            $ratioDiff = abs($delta) / $est;
            $accuracy = max(0, (int) round((1.0 - min(1.0, $ratioDiff)) * 100));

            if ($delta > ($est * 0.15)) {
                $underestimated++;
                $verdict = 'underestimated';
            } elseif ($delta < -($est * 0.15)) {
                $overestimated++;
                $verdict = 'overestimated';
            } else {
                $onTarget++;
                $verdict = 'on_target';
            }

            $accuracyScores[] = $accuracy;
            $biases[] = $delta;
            $totalEstimated += $est;
            $totalActual += $act;

            return [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'category' => $t->category?->name,
                'category_color' => $t->category?->color,
                'estimated_duration' => $est,
                'actual_duration' => $act,
                'delta_minutes' => $delta,
                'accuracy_percent' => $accuracy,
                'verdict' => $verdict,
            ];
        });

        $avgAccuracy = ! empty($accuracyScores)
            ? (int) round(array_sum($accuracyScores) / count($accuracyScores))
            : 0;
        $avgBias = ! empty($biases)
            ? (int) round(array_sum($biases) / count($biases))
            : 0;

        // 2. Deep-Work Hours by Category (within rangeDays)
        $focusSessionsInWindow = FocusSession::with('task.category:id,name,color')
            ->where('user_id', $user->id)
            ->where('mode', 'focus')
            ->whereBetween('started_at', [$windowStartUtc, $windowEndUtc])
            ->get();

        $categoryBuckets = [];
        $totalFocusWindowMinutes = 0;

        foreach ($focusSessionsInWindow as $session) {
            $mins = max((int) $session->logged_minutes, intdiv((int) $session->elapsed_seconds, 60));
            if ($mins <= 0) {
                continue;
            }
            $cat = $session->task?->category;
            $key = $cat ? (string) $cat->id : 'uncategorized';
            if (! isset($categoryBuckets[$key])) {
                $categoryBuckets[$key] = [
                    'id' => $cat?->id,
                    'name' => $cat?->name ?? 'General / Uncategorized',
                    'color' => $cat?->color ?? '#6366f1',
                    'minutes' => 0,
                    'sessions_count' => 0,
                ];
            }
            $categoryBuckets[$key]['minutes'] += $mins;
            $categoryBuckets[$key]['sessions_count']++;
            $totalFocusWindowMinutes += $mins;
        }

        // Supplement with manual actual_duration on tasks updated in window that have no focus sessions if needed
        if ($totalFocusWindowMinutes === 0) {
            foreach ($evaluatedTasks as $taskItem) {
                $mins = (int) $taskItem->actual_duration;
                if ($mins <= 0) {
                    continue;
                }
                $cat = $taskItem->category;
                $key = $cat ? (string) $cat->id : 'uncategorized';
                if (! isset($categoryBuckets[$key])) {
                    $categoryBuckets[$key] = [
                        'id' => $cat?->id,
                        'name' => $cat?->name ?? 'General / Uncategorized',
                        'color' => $cat?->color ?? '#6366f1',
                        'minutes' => 0,
                        'sessions_count' => 0,
                    ];
                }
                $categoryBuckets[$key]['minutes'] += $mins;
                $categoryBuckets[$key]['sessions_count']++;
                $totalFocusWindowMinutes += $mins;
            }
        }

        $deepWorkByCategory = collect(array_values($categoryBuckets))
            ->sortByDesc('minutes')
            ->values()
            ->map(fn (array $b): array => [
                'id' => $b['id'],
                'name' => $b['name'],
                'color' => $b['color'],
                'minutes' => $b['minutes'],
                'hours' => round($b['minutes'] / 60, 1),
                'sessions_count' => $b['sessions_count'],
                'share_percent' => $totalFocusWindowMinutes > 0
                    ? (int) round(($b['minutes'] / $totalFocusWindowMinutes) * 100)
                    : 0,
            ])
            ->all();

        // 3. Weekly Execution Velocity (last 6 weeks)
        $weekStartDay = ($user->setting?->week_start ?? 1) === 0 ? Carbon::SUNDAY : Carbon::MONDAY;
        $currentWeekStart = $localToday->copy()->startOfWeek($weekStartDay);
        $weeklyVelocity = [];

        for ($w = 5; $w >= 0; $w--) {
            $wStart = $currentWeekStart->copy()->subWeeks($w)->startOfDay();
            $wEnd = $wStart->copy()->addDays(6)->endOfDay();

            $completedCount = Task::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$wStart->copy()->utc(), $wEnd->copy()->utc()])
                ->count();

            $weekFocusMins = (int) FocusSession::where('user_id', $user->id)
                ->where('mode', 'focus')
                ->whereBetween('started_at', [$wStart->copy()->utc(), $wEnd->copy()->utc()])
                ->get()
                ->sum(fn (FocusSession $s): int => max((int) $s->logged_minutes, intdiv((int) $s->elapsed_seconds, 60)));

            $weeklyVelocity[] = [
                'week_start' => $wStart->toDateString(),
                'label' => $wStart->format('M d'),
                'completed_tasks' => $completedCount,
                'focus_minutes' => $weekFocusMins,
            ];
        }

        // 4. 14-Day Screen Time vs. Focus & Habit Discipline Correlation
        $activeGoodCount = max(0, Habit::where('user_id', $user->id)->active()->count());
        $activeBadCount = max(0, BadHabit::where('user_id', $user->id)->active()->count());
        $phoneTarget = (int) ($user->setting?->phone_usage_target_minutes ?? 60);

        $start14Str = $localToday->copy()->subDays(13)->toDateString();
        $todayStr = $localToday->toDateString();

        $phoneByDate = PhoneUsageLog::where('user_id', $user->id)
            ->where(function ($q) use ($start14Str, $todayStr): void {
                $q->whereBetween('date', [$start14Str, $todayStr.' 23:59:59'])
                    ->orWhereBetween('date', [$start14Str, $todayStr]);
            })
            ->get()
            ->keyBy(fn (PhoneUsageLog $p): string => Carbon::parse($p->date)->toDateString());

        $goodCheckInsByDate = HabitCheckIn::where('user_id', $user->id)
            ->where(function ($q) use ($start14Str, $todayStr): void {
                $q->whereBetween('date', [$start14Str, $todayStr.' 23:59:59'])
                    ->orWhereBetween('date', [$start14Str, $todayStr]);
            })
            ->get()
            ->groupBy(fn (HabitCheckIn $c): string => Carbon::parse($c->date)->toDateString());

        $badCheckInsByDate = BadHabitCheckIn::where('user_id', $user->id)
            ->where(function ($q) use ($start14Str, $todayStr): void {
                $q->whereBetween('date', [$start14Str, $todayStr.' 23:59:59'])
                    ->orWhereBetween('date', [$start14Str, $todayStr]);
            })
            ->get()
            ->groupBy(fn (BadHabitCheckIn $c): string => Carbon::parse($c->date)->toDateString());

        $focus14 = FocusSession::where('user_id', $user->id)
            ->where('mode', 'focus')
            ->whereBetween('started_at', [
                $localToday->copy()->subDays(13)->startOfDay()->utc(),
                $windowEndUtc,
            ])
            ->get()
            ->groupBy(fn (FocusSession $s): string => $s->started_at
                ? $s->started_at->copy()->setTimezone($tz)->toDateString()
                : $todayStr);

        $dailySeries = [];
        $lowScreenFocus = [];
        $highScreenFocus = [];
        $lowScreenClean = [];
        $highScreenClean = [];

        for ($i = 13; $i >= 0; $i--) {
            $d = $localToday->copy()->subDays($i);
            $dStr = $d->toDateString();
            $phoneLog = $phoneByDate->get($dStr);
            $phoneMins = $phoneLog ? (int) $phoneLog->minutes : null;

            $dayFocusMins = (int) ($focus14->get($dStr, collect()))
                ->sum(fn (FocusSession $s): int => max((int) $s->logged_minutes, intdiv((int) $s->elapsed_seconds, 60)));

            $goodDone = ($goodCheckInsByDate->get($dStr, collect()))
                ->whereIn('status', ['completed', 'partial'])
                ->count();
            $goodRate = $activeGoodCount > 0 ? min(100, (int) round(($goodDone / $activeGoodCount) * 100)) : 0;

            $badClean = ($badCheckInsByDate->get($dStr, collect()))
                ->where('status', 'clean')
                ->count();
            $badCleanRate = $activeBadCount > 0 ? min(100, (int) round(($badClean / $activeBadCount) * 100)) : 0;

            if ($phoneMins !== null) {
                if ($phoneMins <= $phoneTarget) {
                    $lowScreenFocus[] = $dayFocusMins;
                    $lowScreenClean[] = $badCleanRate;
                } else {
                    $highScreenFocus[] = $dayFocusMins;
                    $highScreenClean[] = $badCleanRate;
                }
            }

            $dailySeries[] = [
                'date' => $dStr,
                'label' => $d->format('M d'),
                'phone_minutes' => $phoneMins ?? 0,
                'has_phone_log' => $phoneMins !== null,
                'focus_minutes' => $dayFocusMins,
                'good_habit_rate' => $goodRate,
                'bad_habit_clean_rate' => $badCleanRate,
            ];
        }

        $avgLowScreenFocus = ! empty($lowScreenFocus) ? (int) round(array_sum($lowScreenFocus) / count($lowScreenFocus)) : 0;
        $avgHighScreenFocus = ! empty($highScreenFocus) ? (int) round(array_sum($highScreenFocus) / count($highScreenFocus)) : 0;
        $avgLowScreenClean = ! empty($lowScreenClean) ? (int) round(array_sum($lowScreenClean) / count($lowScreenClean)) : 0;
        $avgHighScreenClean = ! empty($highScreenClean) ? (int) round(array_sum($highScreenClean) / count($highScreenClean)) : 0;

        $correlationInsight = 'Log leisure phone minutes and focus blocks across a few days to unlock personalized screen-time correlation insights.';
        if (! empty($lowScreenFocus) && ! empty($highScreenFocus)) {
            $diff = $avgLowScreenFocus - $avgHighScreenFocus;
            if ($diff > 0) {
                $correlationInsight = "On days within your {$phoneTarget}m phone target, you average +{$diff}m more deep focus ({$avgLowScreenFocus}m vs {$avgHighScreenFocus}m) and {$avgLowScreenClean}% clean-habit adherence.";
            } else {
                $correlationInsight = "Across logged days, you average {$avgLowScreenFocus}m focus within phone cap vs {$avgHighScreenFocus}m when exceeding {$phoneTarget}m.";
            }
        }

        return [
            'range_days' => $days,
            'estimation' => [
                'evaluated_tasks_count' => $evaluatedTasks->count(),
                'total_estimated_minutes' => $totalEstimated,
                'total_actual_minutes' => $totalActual,
                'average_accuracy_percent' => $avgAccuracy,
                'average_bias_minutes' => $avgBias,
                'underestimated_count' => $underestimated,
                'on_target_count' => $onTarget,
                'overestimated_count' => $overestimated,
                'tasks' => $evaluatedRows->take(15)->values()->all(),
            ],
            'deep_work' => [
                'total_minutes' => $totalFocusWindowMinutes,
                'total_hours' => round($totalFocusWindowMinutes / 60, 1),
                'by_category' => $deepWorkByCategory,
            ],
            'weekly_velocity' => $weeklyVelocity,
            'correlations' => [
                'phone_target_minutes' => $phoneTarget,
                'low_screen_avg_focus_minutes' => $avgLowScreenFocus,
                'high_screen_avg_focus_minutes' => $avgHighScreenFocus,
                'low_screen_avg_clean_rate' => $avgLowScreenClean,
                'high_screen_avg_clean_rate' => $avgHighScreenClean,
                'insight' => $correlationInsight,
                'daily_series' => $dailySeries,
            ],
        ];
    }
}
