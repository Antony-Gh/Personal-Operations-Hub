<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitCheckIn;
use App\Models\User;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HabitAnalyticsService
{
    /**
     * Compute comprehensive streak and consistency statistics for a single Habit.
     *
     * @param  Collection<int, HabitCheckIn>|null  $preloadedCheckIns
     * @return array{
     *     current_streak: int,
     *     best_streak: int,
     *     total_completed_days: int,
     *     weekly_consistency: int,
     *     monthly_consistency: int,
     *     completion_percentage: int,
     *     completed_in_7_days: int
     * }
     */
    public function calculateHabitMetrics(Habit $habit, ?User $user = null, ?Collection $preloadedCheckIns = null): array
    {
        $owner = $user ?? $habit->user()->with('setting')->first();
        $tz = UserDateTime::resolveTimezone($owner);
        $localToday = UserDateTime::todayForUser($owner);
        $weekStartDay = ($owner?->setting?->week_start ?? 1) === 0 ? Carbon::SUNDAY : Carbon::MONDAY;

        $checkIns = $preloadedCheckIns ?? $habit->checkIns()->get();
        $byDate = $checkIns->keyBy(fn (HabitCheckIn $ci): string => Carbon::parse($ci->date)->toDateString());

        $totalCompletedDays = $checkIns
            ->filter(fn (HabitCheckIn $ci): bool => in_array($ci->status, ['completed', 'partial'], true))
            ->count();

        $createdLocal = $habit->created_at
            ? $habit->created_at->copy()->setTimezone($tz)->startOfDay()
            : $localToday->copy();

        $earliestCheckInStr = $byDate->keys()->sort()->first();
        if (is_string($earliestCheckInStr)) {
            $earliestCheckIn = Carbon::createFromFormat('Y-m-d', $earliestCheckInStr, $tz)->startOfDay();
            if ($earliestCheckIn->lessThan($createdLocal)) {
                $createdLocal = $earliestCheckIn;
            }
        }

        $evalEnd = $localToday->copy();
        if ($habit->is_archived) {
            $archivedAnchor = $habit->updated_at
                ? $habit->updated_at->copy()->setTimezone($tz)->startOfDay()
                : $localToday->copy();
            $latestCheckInStr = $byDate->keys()->sort()->last();
            if (is_string($latestCheckInStr)) {
                $latestCheckIn = Carbon::createFromFormat('Y-m-d', $latestCheckInStr, $tz)->startOfDay();
                $evalEnd = $latestCheckIn->greaterThan($archivedAnchor) ? $latestCheckIn : $archivedAnchor;
            } else {
                $evalEnd = $archivedAnchor;
            }
            if ($evalEnd->greaterThan($localToday)) {
                $evalEnd = $localToday->copy();
            }
        }

        $targetPerWeek = $habit->frequency === 'weekly'
            ? max(1, min(7, (int) ($habit->target_days_per_week ?: 1)))
            : max(1, min(7, (int) ($habit->target_days_per_week ?: 7)));

        [$currentStreak, $bestStreak] = $this->computeStreaks(
            $byDate,
            $createdLocal,
            $evalEnd,
            $localToday,
            $targetPerWeek,
            $weekStartDay,
            (bool) $habit->is_archived
        );

        $completedIn7Days = 0;
        for ($i = 0; $i < 7; $i++) {
            $dStr = $localToday->copy()->subDays($i)->toDateString();
            $ci = $byDate->get($dStr);
            if ($ci && in_array($ci->status, ['completed', 'partial'], true)) {
                $completedIn7Days++;
            }
        }

        $currentWeekStart = $localToday->copy()->startOfWeek($weekStartDay);
        $weekCompleted = 0;
        $cursor = $currentWeekStart->copy();
        while ($cursor->lessThanOrEqualTo($localToday)) {
            $ci = $byDate->get($cursor->toDateString());
            if ($ci && in_array($ci->status, ['completed', 'partial'], true)) {
                $weekCompleted++;
            }
            $cursor->addDay();
        }
        $weeklyConsistency = min(100, (int) round(($weekCompleted / max(1, $targetPerWeek)) * 100));

        $monthStart = $localToday->copy()->startOfMonth();
        $effectiveMonthStart = $createdLocal->greaterThan($monthStart) ? $createdLocal->copy() : $monthStart;
        $monthDaysElapsed = max(1, (int) $effectiveMonthStart->diffInDays($localToday) + 1);
        $monthCompleted = 0;
        $mCursor = $effectiveMonthStart->copy();
        while ($mCursor->lessThanOrEqualTo($localToday)) {
            $ci = $byDate->get($mCursor->toDateString());
            if ($ci && in_array($ci->status, ['completed', 'partial'], true)) {
                $monthCompleted++;
            }
            $mCursor->addDay();
        }
        $expectedMonthTarget = max(1.0, ($monthDaysElapsed * $targetPerWeek) / 7.0);
        $monthlyConsistency = min(100, (int) round(($monthCompleted / $expectedMonthTarget) * 100));

        $totalDaysSinceCreation = max(1, (int) $createdLocal->diffInDays($evalEnd) + 1);
        $expectedOverallTarget = max(1.0, ($totalDaysSinceCreation * $targetPerWeek) / 7.0);
        $completionPercentage = min(100, (int) round(($totalCompletedDays / $expectedOverallTarget) * 100));

        return [
            'current_streak' => $currentStreak,
            'best_streak' => $bestStreak,
            'total_completed_days' => $totalCompletedDays,
            'weekly_consistency' => $weeklyConsistency,
            'monthly_consistency' => $monthlyConsistency,
            'completion_percentage' => $completionPercentage,
            'completed_in_7_days' => $completedIn7Days,
        ];
    }

    /**
     * Build a monthly calendar heatmap and 6-month historical consistency overview for the user.
     *
     * @return array{
     *     month: string,
     *     month_label: string,
     *     prev_month: string,
     *     next_month: string,
     *     days: array<int, array<string, mixed>>,
     *     monthly_summary: array<int, array<string, mixed>>
     * }
     */
    public function buildMonthlyHeatmap(User $user, ?string $monthStr = null): array
    {
        $tz = UserDateTime::resolveTimezone($user);
        $localToday = UserDateTime::todayForUser($user);
        $weekStartDay = ($user->setting?->week_start ?? 1) === 0 ? Carbon::SUNDAY : Carbon::MONDAY;

        $targetMonth = ($monthStr !== null && preg_match('/^\d{4}-\d{2}$/', $monthStr))
            ? $monthStr
            : $localToday->format('Y-m');

        $firstOfMonth = Carbon::createFromFormat('Y-m-d', "{$targetMonth}-01", $tz)->startOfDay();
        $lastOfMonth = $firstOfMonth->copy()->endOfMonth()->startOfDay();

        $activeHabits = Habit::where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        $sixMonthsAgo = $firstOfMonth->copy()->subMonthsNoOverflow(5)->startOfMonth();
        $rangeEnd = $lastOfMonth->greaterThan($localToday) ? $lastOfMonth : $localToday->copy()->endOfMonth()->startOfDay();

        $allCheckIns = HabitCheckIn::where('user_id', $user->id)
            ->whereIn('habit_id', $activeHabits->pluck('id'))
            ->whereBetween('date', [$sixMonthsAgo->toDateString(), $rangeEnd->toDateString()])
            ->get()
            ->groupBy(fn (HabitCheckIn $ci): string => Carbon::parse($ci->date)->toDateString());

        $gridStart = $firstOfMonth->copy()->startOfWeek($weekStartDay);
        $gridEnd = $lastOfMonth->copy()->endOfWeek($weekStartDay === Carbon::SUNDAY ? Carbon::SATURDAY : Carbon::SUNDAY)->startOfDay();

        $days = [];
        $cursor = $gridStart->copy();
        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $dStr = $cursor->toDateString();
            $dayCheckIns = $allCheckIns->get($dStr, collect());
            $eligibleHabitsCount = $activeHabits->filter(function (Habit $h) use ($cursor, $tz): bool {
                if (! $h->created_at) {
                    return true;
                }

                return $h->created_at->copy()->setTimezone($tz)->startOfDay()->lessThanOrEqualTo($cursor);
            })->count();

            $completedCount = $dayCheckIns->where('status', 'completed')->count();
            $partialCount = $dayCheckIns->where('status', 'partial')->count();
            $weightedDone = $completedCount + ($partialCount * 0.5);

            $ratio = $eligibleHabitsCount > 0 ? min(1.0, $weightedDone / $eligibleHabitsCount) : 0.0;
            $level = 0;
            if ($weightedDone > 0) {
                if ($ratio >= 0.999) {
                    $level = 4;
                } elseif ($ratio >= 0.67) {
                    $level = 3;
                } elseif ($ratio >= 0.34) {
                    $level = 2;
                } else {
                    $level = 1;
                }
            }

            $days[] = [
                'date' => $dStr,
                'day_number' => $cursor->day,
                'is_current_month' => $cursor->format('Y-m') === $targetMonth,
                'is_today' => $dStr === $localToday->toDateString(),
                'is_future' => $cursor->greaterThan($localToday),
                'completed_count' => $completedCount,
                'partial_count' => $partialCount,
                'total_habits' => $eligibleHabitsCount,
                'ratio' => round($ratio * 100),
                'level' => $level,
            ];

            $cursor->addDay();
        }

        $monthlySummary = [];
        for ($m = 5; $m >= 0; $m--) {
            $mStart = $firstOfMonth->copy()->subMonthsNoOverflow($m)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth()->startOfDay();
            if ($mEnd->greaterThan($localToday)) {
                $mEnd = $localToday->copy();
            }

            $activeDays = 0;
            $completedCheckInsCount = 0;
            if ($mStart->lessThanOrEqualTo($localToday)) {
                $mc = $mStart->copy();
                while ($mc->lessThanOrEqualTo($mEnd)) {
                    $cList = $allCheckIns->get($mc->toDateString(), collect());
                    $doneOnDay = $cList->whereIn('status', ['completed', 'partial'])->count();
                    if ($doneOnDay > 0) {
                        $activeDays++;
                        $completedCheckInsCount += $doneOnDay;
                    }
                    $mc->addDay();
                }
            }

            $daysInSpan = $mStart->lessThanOrEqualTo($mEnd) ? ((int) $mStart->diffInDays($mEnd) + 1) : 0;
            $monthlySummary[] = [
                'month' => $mStart->format('Y-m'),
                'label' => $mStart->format('M Y'),
                'active_days' => $activeDays,
                'days_in_span' => $daysInSpan,
                'completed_checkins' => $completedCheckInsCount,
                'consistency_percent' => ($daysInSpan > 0 && $activeHabits->count() > 0)
                    ? min(100, (int) round(($completedCheckInsCount / ($daysInSpan * $activeHabits->count())) * 100))
                    : 0,
            ];
        }

        return [
            'month' => $targetMonth,
            'month_label' => $firstOfMonth->format('F Y'),
            'prev_month' => $firstOfMonth->copy()->subMonthNoOverflow()->format('Y-m'),
            'next_month' => $firstOfMonth->copy()->addMonthNoOverflow()->format('Y-m'),
            'week_start' => $weekStartDay === Carbon::SUNDAY ? 0 : 1,
            'days' => $days,
            'monthly_summary' => $monthlySummary,
        ];
    }

    /**
     * @param  Collection<string, HabitCheckIn>  $byDate
     * @return array{0: int, 1: int}
     */
    private function computeStreaks(
        Collection $byDate,
        Carbon $createdLocal,
        Carbon $evalEnd,
        Carbon $localToday,
        int $targetPerWeek,
        int $weekStartDay,
        bool $isArchived
    ): array {
        if ($createdLocal->greaterThan($evalEnd)) {
            return [0, 0];
        }

        $runningStreak = 0;
        $bestStreak = 0;

        if ($targetPerWeek >= 7) {
            $cursor = $createdLocal->copy();
            while ($cursor->lessThanOrEqualTo($evalEnd)) {
                $dStr = $cursor->toDateString();
                $ci = $byDate->get($dStr);
                $isDone = $ci !== null && in_array($ci->status, ['completed', 'partial'], true);
                $isTodayPending = (! $isArchived) && $dStr === $localToday->toDateString() && $ci === null;

                if ($isDone) {
                    $runningStreak++;
                    $bestStreak = max($bestStreak, $runningStreak);
                } elseif (! $isTodayPending) {
                    $runningStreak = 0;
                }

                $cursor->addDay();
            }

            return [$runningStreak, $bestStreak];
        }

        $weekCursor = $createdLocal->copy()->startOfWeek($weekStartDay);
        while ($weekCursor->lessThanOrEqualTo($evalEnd)) {
            $weekEnd = $weekCursor->copy()->addDays(6)->startOfDay();
            $effectiveStart = $createdLocal->greaterThan($weekCursor) ? $createdLocal->copy() : $weekCursor->copy();
            $effectiveEnd = $evalEnd->lessThan($weekEnd) ? $evalEnd->copy() : $weekEnd->copy();

            $daysInWindow = (int) $effectiveStart->diffInDays($effectiveEnd) + 1;
            $isOngoingWeek = (! $isArchived) && $weekEnd->greaterThanOrEqualTo($localToday);

            $totalScheduledDaysInThisWeek = $weekCursor->lessThan($createdLocal)
                ? (int) $createdLocal->diffInDays($weekEnd) + 1
                : 7;
            $effectiveTarget = min($targetPerWeek, max(1, $totalScheduledDaysInThisWeek));
            $allowedMisses = max(0, $totalScheduledDaysInThisWeek - $effectiveTarget);

            $missesInWeek = 0;
            $dayCursor = $effectiveStart->copy();

            while ($dayCursor->lessThanOrEqualTo($effectiveEnd)) {
                $dStr = $dayCursor->toDateString();
                $ci = $byDate->get($dStr);
                $isDone = $ci !== null && in_array($ci->status, ['completed', 'partial'], true);
                $isTodayPending = (! $isArchived) && $dStr === $localToday->toDateString() && $ci === null;

                if ($isDone) {
                    $runningStreak++;
                    $bestStreak = max($bestStreak, $runningStreak);
                } elseif (! $isTodayPending) {
                    $missesInWeek++;
                    if ($missesInWeek > $allowedMisses) {
                        $runningStreak = 0;
                    }
                }

                $dayCursor->addDay();
            }

            if (! $isOngoingWeek && $daysInWindow > 0) {
                $completedInWeek = 0;
                $checkDay = $effectiveStart->copy();
                while ($checkDay->lessThanOrEqualTo($effectiveEnd)) {
                    $ci = $byDate->get($checkDay->toDateString());
                    if ($ci !== null && in_array($ci->status, ['completed', 'partial'], true)) {
                        $completedInWeek++;
                    }
                    $checkDay->addDay();
                }

                if ($completedInWeek < $effectiveTarget) {
                    $runningStreak = 0;
                }
            }

            $weekCursor->addDays(7);
        }

        return [$runningStreak, $bestStreak];
    }
}
