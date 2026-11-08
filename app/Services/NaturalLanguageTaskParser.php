<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use App\Support\UserDateTime;
use Carbon\Carbon;

class NaturalLanguageTaskParser
{
    /**
     * Parse shorthand task capture strings into structured task attributes in the user's timezone.
     *
     * Supported tokens:
     * - Priority: !high / !h / !p1, !medium / !m / !p2, !low / !l / !p3
     * - Duration: ~90m, ~45min, ~1.5h, ~2h
     * - Category: #University or #"Software Team"
     * - Project: +Thesis or +"ROV Stabilization"
     * - Dates: today, tomorrow / tmr, next week, in N days, YYYY-MM-DD, (next )?monday..sunday
     * - Times: at 17:00, 17:30, 5pm, 5:30pm, 9am
     *
     * @return array{
     *     title: string,
     *     priority: string|null,
     *     due_date: string|null,
     *     due_time: string|null,
     *     estimated_duration: int|null,
     *     category_id: int|null,
     *     category_name: string|null,
     *     project_id: int|null,
     *     project_name: string|null,
     *     matched_tokens: array<int, string>
     * }
     */
    public function parse(string $rawInput, User $user): array
    {
        $working = trim($rawInput);
        $localToday = UserDateTime::todayForUser($user);
        $matchedTokens = [];

        $priority = null;
        $estimatedDuration = null;
        $categoryId = null;
        $categoryName = null;
        $projectId = null;
        $projectName = null;
        $dueDate = null;
        $dueTime = null;

        // 1. Priority (!high, !medium, !low, !h, !m, !l, !p1, !p2, !p3)
        if (preg_match('/(?:^|\s)!(high|h|p1|medium|med|m|p2|low|l|p3)(?=\s|$)/i', $working, $m)) {
            $tok = strtolower($m[1]);
            $priority = match (true) {
                in_array($tok, ['high', 'h', 'p1'], true) => 'high',
                in_array($tok, ['medium', 'med', 'm', 'p2'], true) => 'medium',
                default => 'low',
            };
            $matchedTokens[] = "!{$priority}";
            $working = (string) preg_replace('/(?:^|\s)!(?:high|h|p1|medium|med|m|p2|low|l|p3)(?=\s|$)/i', ' ', $working, 1);
        }

        // 2. Estimated duration (~90m, ~45min, ~1.5h, ~2h)
        if (preg_match('/(?:^|\s)~(\d+(?:\.\d+)?)\s*(h|hr|hrs|hour|hours|m|min|mins|minutes?)(?=\s|$)/i', $working, $m)) {
            $num = (float) $m[1];
            $unit = strtolower($m[2]);
            $minutes = str_starts_with($unit, 'h') ? (int) round($num * 60) : (int) round($num);
            if ($minutes >= 1 && $minutes <= 1440) {
                $estimatedDuration = $minutes;
                $matchedTokens[] = "~{$minutes}m";
            }
            $working = (string) preg_replace('/(?:^|\s)~\d+(?:\.\d+)?\s*(?:h|hr|hrs|hour|hours|m|min|mins|minutes?)(?=\s|$)/i', ' ', $working, 1);
        }

        // 3. Category (#"Multi Word" or #SingleWord)
        $categories = Category::where('user_id', $user->id)->active()->get(['id', 'name']);
        if (preg_match('/(?:^|\s)#(?:"([^"]+)"|([A-Za-z0-9_-]+))(?=\s|$)/', $working, $m)) {
            $needle = strtolower(trim($m[1] !== '' ? $m[1] : ($m[2] ?? '')));
            if ($needle !== '') {
                $found = $categories->first(fn (Category $c): bool => strtolower($c->name) === $needle)
                    ?? $categories->first(fn (Category $c): bool => str_starts_with(strtolower($c->name), $needle));
                if ($found) {
                    $categoryId = $found->id;
                    $categoryName = $found->name;
                    $matchedTokens[] = "#{$found->name}";
                    $working = str_replace($m[0], ' ', $working);
                }
            }
        }

        // 4. Project (+"Multi Word" or +SingleWord)
        $projects = Project::where('user_id', $user->id)->active()->get(['id', 'name']);
        if (preg_match('/(?:^|\s)\+(?:"([^"]+)"|([A-Za-z0-9_-]+))(?=\s|$)/', $working, $m)) {
            $needle = strtolower(trim($m[1] !== '' ? $m[1] : ($m[2] ?? '')));
            if ($needle !== '') {
                $found = $projects->first(fn (Project $p): bool => strtolower($p->name) === $needle)
                    ?? $projects->first(fn (Project $p): bool => str_starts_with(strtolower($p->name), $needle));
                if ($found) {
                    $projectId = $found->id;
                    $projectName = $found->name;
                    $matchedTokens[] = "+{$found->name}";
                    $working = str_replace($m[0], ' ', $working);
                }
            }
        }

        // 5. Explicit ISO date (YYYY-MM-DD)
        if (preg_match('/(?:^|\s)(\d{4}-\d{2}-\d{2})(?=\s|$)/', $working, $m)) {
            $dueDate = $m[1];
            $matchedTokens[] = $dueDate;
            $working = str_replace($m[0], ' ', $working);
        } elseif (preg_match('/(?:^|\s)in\s+(\d{1,3})\s+days?(?=\s|$)/i', $working, $m)) {
            $dueDate = $localToday->copy()->addDays((int) $m[1])->toDateString();
            $matchedTokens[] = $dueDate;
            $working = str_replace($m[0], ' ', $working);
        } elseif (preg_match('/(?:^|\s)(today|tomorrow|tmr|next\s+week)(?=\s|$)/i', $working, $m)) {
            $kw = strtolower((string) preg_replace('/\s+/', ' ', trim($m[1])));
            $dueDate = match ($kw) {
                'today' => $localToday->toDateString(),
                'tomorrow', 'tmr' => $localToday->copy()->addDay()->toDateString(),
                'next week' => $localToday->copy()->addDays(7)->toDateString(),
                default => null,
            };
            if ($dueDate !== null) {
                $matchedTokens[] = $dueDate;
                $working = str_replace($m[0], ' ', $working);
            }
        } elseif (preg_match('/(?:^|\s)(?:(next)\s+)?(monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)(?=\s|$)/i', $working, $m)) {
            $dayMap = [
                'monday' => Carbon::MONDAY, 'mon' => Carbon::MONDAY,
                'tuesday' => Carbon::TUESDAY, 'tue' => Carbon::TUESDAY,
                'wednesday' => Carbon::WEDNESDAY, 'wed' => Carbon::WEDNESDAY,
                'thursday' => Carbon::THURSDAY, 'thu' => Carbon::THURSDAY,
                'friday' => Carbon::FRIDAY, 'fri' => Carbon::FRIDAY,
                'saturday' => Carbon::SATURDAY, 'sat' => Carbon::SATURDAY,
                'sunday' => Carbon::SUNDAY, 'sun' => Carbon::SUNDAY,
            ];
            $dayKey = strtolower($m[2]);
            $targetDow = $dayMap[$dayKey] ?? null;
            if ($targetDow !== null) {
                $candidate = $localToday->copy();
                $daysAhead = ($targetDow - $candidate->dayOfWeek + 7) % 7;
                if ($daysAhead === 0 || strtolower($m[1] ?? '') === 'next') {
                    $daysAhead = $daysAhead === 0 ? 7 : $daysAhead;
                }
                $dueDate = $candidate->addDays($daysAhead)->toDateString();
                $matchedTokens[] = $dueDate;
                $working = str_replace($m[0], ' ', $working);
            }
        }

        // 6. Time (17:00, at 17:00, 5pm, 5:30pm, at 9am)
        if (preg_match('/(?:^|\s)(?:at\s+)?(\d{1,2})(?::(\d{2}))?\s*(am|pm)(?=\s|$)/i', $working, $m)) {
            $hour = (int) $m[1];
            $minute = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $meridiem = strtolower($m[3]);
            if ($hour >= 1 && $hour <= 12 && $minute >= 0 && $minute <= 59) {
                if ($meridiem === 'pm' && $hour < 12) {
                    $hour += 12;
                } elseif ($meridiem === 'am' && $hour === 12) {
                    $hour = 0;
                }
                $dueTime = sprintf('%02d:%02d', $hour, $minute);
                $matchedTokens[] = $dueTime;
                $working = str_replace($m[0], ' ', $working);
            }
        } elseif (preg_match('/(?:^|\s)(?:at\s+)?([01]?\d|2[0-3]):([0-5]\d)(?=\s|$)/i', $working, $m)) {
            $dueTime = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            $matchedTokens[] = $dueTime;
            $working = str_replace($m[0], ' ', $working);
        }

        if ($dueTime !== null && $dueDate === null) {
            $dueDate = $localToday->toDateString();
        }

        $cleanTitle = trim((string) preg_replace('/\s+/', ' ', $working));
        if ($cleanTitle === '') {
            $cleanTitle = trim($rawInput);
        }

        return [
            'title' => $cleanTitle,
            'priority' => $priority,
            'due_date' => $dueDate,
            'due_time' => $dueTime,
            'estimated_duration' => $estimatedDuration,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'project_id' => $projectId,
            'project_name' => $projectName,
            'matched_tokens' => $matchedTokens,
        ];
    }
}
