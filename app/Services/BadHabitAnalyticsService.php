<?php

namespace App\Services;

use App\Models\BadHabit;
use App\Models\BadHabitCheckIn;
use App\Models\User;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BadHabitAnalyticsService
{
    /**
     * Compute clean-day streak, slip counts, consistency rates, and recent slip reflections for a BadHabit.
     *
     * @param  Collection<int, BadHabitCheckIn>|null  $preloadedCheckIns
     * @return array{
     *     current_clean_streak: int,
     *     best_clean_streak: int,
     *     total_clean_days: int,
     *     minor_slips_count: int,
     *     slips_count: int,
     *     clean_in_7_days: int,
     *     weekly_clean_rate: int,
     *     monthly_clean_rate: int,
     *     overall_clean_percentage: int,
     *     recent_reflections: array<int, array<string, mixed>>
     * }
     */
    public function calculateMetrics(BadHabit $badHabit, ?User $user = null, ?Collection $preloadedCheckIns = null): array
    {
        $owner = $user ?? $badHabit->user()->with('setting')->first();
        $tz = UserDateTime::resolveTimezone($owner);
        $localToday = UserDateTime::todayForUser($owner);
        $weekStartDay = ($owner?->setting?->week_start ?? 1) === 0 ? Carbon::SUNDAY : Carbon::MONDAY;

        $checkIns = $preloadedCheckIns ?? $badHabit->checkIns()->get();
        $byDate = $checkIns->keyBy(fn (BadHabitCheckIn $ci): string => Carbon::parse($ci->date)->toDateString());

        $totalCleanDays = $checkIns->where('status', 'clean')->count();
        $minorSlipsCount = $checkIns->where('status', 'minor_slip')->count();
        $slipsCount = $checkIns->where('status', 'slipped')->count();

        $createdLocal = $badHabit->created_at
            ? $badHabit->created_at->copy()->setTimezone($tz)->startOfDay()
            : $localToday->copy();

        $earliestStr = $byDate->keys()->sort()->first();
        if (is_string($earliestStr)) {
            $earliest = Carbon::createFromFormat('Y-m-d', $earliestStr, $tz)->startOfDay();
            if ($earliest->lessThan($createdLocal)) {
                $createdLocal = $earliest;
            }
        }

        $evalEnd = $localToday->copy();
        if ($badHabit->is_archived) {
            $archivedAnchor = $badHabit->updated_at
                ? $badHabit->updated_at->copy()->setTimezone($tz)->startOfDay()
                : $localToday->copy();
            $latestStr = $byDate->keys()->sort()->last();
            if (is_string($latestStr)) {
                $latest = Carbon::createFromFormat('Y-m-d', $latestStr, $tz)->startOfDay();
                $evalEnd = $latest->greaterThan($archivedAnchor) ? $latest : $archivedAnchor;
            } else {
                $evalEnd = $archivedAnchor;
            }
            if ($evalEnd->greaterThan($localToday)) {
                $evalEnd = $localToday->copy();
            }
        }

        $targetPerWeek = max(1, min(7, (int) ($badHabit->target_clean_days_per_week ?: 7)));

        [$currentCleanStreak, $bestCleanStreak] = $this->computeCleanStreaks(
            $byDate,
            $createdLocal,
            $evalEnd,
            $localToday,
            $targetPerWeek,
            $weekStartDay,
            (bool) $badHabit->is_archived
        );

        $cleanIn7Days = 0;
        for ($i = 0; $i < 7; $i++) {
            $dStr = $localToday->copy()->subDays($i)->toDateString();
            $ci = $byDate->get($dStr);
            if ($ci && $ci->status === 'clean') {
                $cleanIn7Days++;
            }
        }

        $currentWeekStart = $localToday->copy()->startOfWeek($weekStartDay);
        $weekWeightedClean = 0.0;
        $cursor = $currentWeekStart->copy();
        while ($cursor->lessThanOrEqualTo($localToday)) {
            $ci = $byDate->get($cursor->toDateString());
            if ($ci) {
                if ($ci->status === 'clean') {
                    $weekWeightedClean += 1.0;
                } elseif ($ci->status === 'minor_slip') {
                    $weekWeightedClean += 0.5;
                }
            }
            $cursor->addDay();
        }
        $weeklyCleanRate = min(100, (int) round(($weekWeightedClean / max(1, $targetPerWeek)) * 100));

        $monthStart = $localToday->copy()->startOfMonth();
        $effectiveMonthStart = $createdLocal->greaterThan($monthStart) ? $createdLocal->copy() : $monthStart;
        $monthDaysElapsed = max(1, (int) $effectiveMonthStart->diffInDays($localToday) + 1);
        $monthWeightedClean = 0.0;
        $mCursor = $effectiveMonthStart->copy();
        while ($mCursor->lessThanOrEqualTo($localToday)) {
            $ci = $byDate->get($mCursor->toDateString());
            if ($ci) {
                if ($ci->status === 'clean') {
                    $monthWeightedClean += 1.0;
                } elseif ($ci->status === 'minor_slip') {
                    $monthWeightedClean += 0.5;
                }
            }
            $mCursor->addDay();
        }
        $expectedMonthTarget = max(1.0, ($monthDaysElapsed * $targetPerWeek) / 7.0);
        $monthlyCleanRate = min(100, (int) round(($monthWeightedClean / $expectedMonthTarget) * 100));

        $totalDaysSinceCreation = max(1, (int) $createdLocal->diffInDays($evalEnd) + 1);
        $expectedOverallTarget = max(1.0, ($totalDaysSinceCreation * $targetPerWeek) / 7.0);
        $overallWeighted = $totalCleanDays + ($minorSlipsCount * 0.5);
        $overallCleanPercentage = min(100, (int) round(($overallWeighted / $expectedOverallTarget) * 100));

        $recentReflections = $checkIns
            ->filter(fn (BadHabitCheckIn $ci): bool => ! empty($ci->trigger_note) || ! empty($ci->notes))
            ->sortByDesc(fn (BadHabitCheckIn $ci): string => Carbon::parse($ci->date)->toDateString())
            ->take(5)
            ->map(fn (BadHabitCheckIn $ci): array => [
                'date' => Carbon::parse($ci->date)->toDateString(),
                'status' => $ci->status,
                'observed_value' => $ci->observed_value,
                'trigger_note' => $ci->trigger_note,
                'notes' => $ci->notes,
            ])
            ->values()
            ->all();

        return [
            'current_clean_streak' => $currentCleanStreak,
            'best_clean_streak' => $bestCleanStreak,
            'total_clean_days' => $totalCleanDays,
            'minor_slips_count' => $minorSlipsCount,
            'slips_count' => $slipsCount,
            'clean_in_7_days' => $cleanIn7Days,
            'weekly_clean_rate' => $weeklyCleanRate,
            'monthly_clean_rate' => $monthlyCleanRate,
            'overall_clean_percentage' => $overallCleanPercentage,
            'recent_reflections' => $recentReflections,
        ];
    }

    /**
     * Build a monthly clean-days calendar heatmap and 6-month historical overview compatible with HabitHeatmap.vue.
     *
     * @return array<string, mixed>
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

        $activeBadHabits = BadHabit::where('user_id', $user->id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        $sixMonthsAgo = $firstOfMonth->copy()->subMonthsNoOverflow(5)->startOfMonth();
        $rangeEnd = $lastOfMonth->greaterThan($localToday) ? $lastOfMonth : $localToday->copy()->endOfMonth()->startOfDay();

        $allCheckIns = BadHabitCheckIn::where('user_id', $user->id)
            ->whereIn('bad_habit_id', $activeBadHabits->pluck('id'))
            ->whereBetween('date', [$sixMonthsAgo->toDateString(), $rangeEnd->toDateString()])
            ->get()
            ->groupBy(fn (BadHabitCheckIn $ci): string => Carbon::parse($ci->date)->toDateString());

        $gridStart = $firstOfMonth->copy()->startOfWeek($weekStartDay);
        $gridEnd = $lastOfMonth->copy()->endOfWeek($weekStartDay === Carbon::SUNDAY ? Carbon::SATURDAY : Carbon::SUNDAY)->startOfDay();

        $days = [];
        $cursor = $gridStart->copy();
        while ($cursor->lessThanOrEqualTo($gridEnd)) {
            $dStr = $cursor->toDateString();
            $dayCheckIns = $allCheckIns->get($dStr, collect());
            $eligibleCount = $activeBadHabits->filter(function (BadHabit $bh) use ($cursor, $tz): bool {
                if (! $bh->created_at) {
                    return true;
                }

                return $bh->created_at->copy()->setTimezone($tz)->startOfDay()->lessThanOrEqualTo($cursor);
            })->count();

            $cleanCount = $dayCheckIns->where('status', 'clean')->count();
            $minorCount = $dayCheckIns->where('status', 'minor_slip')->count();
            $weightedClean = $cleanCount + ($minorCount * 0.5);

            $ratio = $eligibleCount > 0 ? min(1.0, $weightedClean / $eligibleCount) : 0.0;
            $level = 0;
            if ($weightedClean > 0) {
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
                'completed_count' => $cleanCount,
                'partial_count' => $minorCount,
                'total_habits' => $eligibleCount,
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
            $cleanCheckInsCount = 0;
            if ($mStart->lessThanOrEqualTo($localToday)) {
                $mc = $mStart->copy();
                while ($mc->lessThanOrEqualTo($mEnd)) {
                    $cList = $allCheckIns->get($mc->toDateString(), collect());
                    $cleanOnDay = $cList->whereIn('status', ['clean', 'minor_slip'])->count();
                    if ($cleanOnDay > 0) {
                        $activeDays++;
                        $cleanCheckInsCount += $cleanOnDay;
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
                'completed_checkins' => $cleanCheckInsCount,
                'consistency_percent' => ($daysInSpan > 0 && $activeBadHabits->count() > 0)
                    ? min(100, (int) round(($cleanCheckInsCount / ($daysInSpan * $activeBadHabits->count())) * 100))
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
     * @param  Collection<string, BadHabitCheckIn>  $byDate
     * @return array{0: int, 1: int}
     */
    private function computeCleanStreaks(
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
                $isTodayPending = (! $isArchived) && $dStr === $localToday->toDateString() && $ci === null;

                if ($ci !== null && $ci->status === 'clean') {
                    $runningStreak++;
                    $bestStreak = max($bestStreak, $runningStreak);
                } elseif ($ci !== null && $ci->status === 'minor_slip') {
                    // Minor slip holds current streak without adding a clean day
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

            $totalScheduledDaysInWeek = $weekCursor->lessThan($createdLocal)
                ? (int) $createdLocal->diffInDays($weekEnd) + 1
                : 7;
            $effectiveTarget = min($targetPerWeek, max(1, $totalScheduledDaysInWeek));
            $allowedMisses = max(0, $totalScheduledDaysInWeek - $effectiveTarget);

            $missesInWeek = 0;
            $dayCursor = $effectiveStart->copy();

            while ($dayCursor->lessThanOrEqualTo($effectiveEnd)) {
                $dStr = $dayCursor->toDateString();
                $ci = $byDate->get($dStr);
                $isTodayPending = (! $isArchived) && $dStr === $localToday->toDateString() && $ci === null;

                if ($ci !== null && $ci->status === 'clean') {
                    $runningStreak++;
                    $bestStreak = max($bestStreak, $runningStreak);
                } elseif ($ci !== null && $ci->status === 'slipped') {
                    $runningStreak = 0;
                    $missesInWeek++;
                } elseif ($ci !== null && $ci->status === 'minor_slip') {
                    // Holds streak steady
                } elseif (! $isTodayPending) {
                    $missesInWeek++;
                    if ($missesInWeek > $allowedMisses) {
                        $runningStreak = 0;
                    }
                }

                $dayCursor->addDay();
            }

            $weekCursor->addDays(7);
        }

        return [$runningStreak, $bestStreak];
    }
}

