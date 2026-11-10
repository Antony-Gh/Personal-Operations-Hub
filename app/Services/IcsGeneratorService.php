<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\UserDateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;

class IcsGeneratorService
{
    /**
     * Generate a secure random private feed token for the user and store its hash + encrypted copy.
     */
    public function generateFeedToken(User $user): string
    {
        $rawToken = bin2hex(random_bytes(24));
        $setting = $user->ensureSetting();

        $setting->update([
            'calendar_feed_enabled' => true,
            'calendar_feed_token_hash' => hash('sha256', $rawToken),
            'calendar_feed_token_encrypted' => Crypt::encryptString($rawToken),
        ]);

        return $rawToken;
    }

    /**
     * Revoke the user's private calendar feed token immediately.
     */
    public function revokeFeedToken(User $user): void
    {
        $setting = $user->ensureSetting();

        $setting->update([
            'calendar_feed_enabled' => false,
            'calendar_feed_token_hash' => null,
            'calendar_feed_token_encrypted' => null,
        ]);
    }

    /**
     * Resolve a User from a raw private subscription token in constant time.
     */
    public function resolveUserByToken(string $rawToken): ?User
    {
        $trimmed = trim($rawToken);
        if (strlen($trimmed) < 32 || ! ctype_xdigit($trimmed)) {
            return null;
        }

        $candidateHash = hash('sha256', $trimmed);

        $setting = UserSetting::with('user')
            ->where('calendar_feed_enabled', true)
            ->where('calendar_feed_token_hash', $candidateHash)
            ->first();

        if (! $setting || ! $setting->calendar_feed_token_hash) {
            return null;
        }

        if (! hash_equals($setting->calendar_feed_token_hash, $candidateHash)) {
            return null;
        }

        return $setting->user;
    }

    /**
     * Build a complete RFC 5545 .ics calendar payload for the user.
     */
    public function generateForUser(User $user, bool $includeCancelled = true): string
    {
        $tz = UserDateTime::resolveTimezone($user);
        $dtStamp = now()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Personal Operations Hub//Local Operations Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            $this->escapeProperty('X-WR-CALNAME', 'Personal Operations Hub — '.$user->name),
            'X-WR-TIMEZONE:'.$tz,
        ];

        $events = CalendarEvent::with('category')
            ->where('user_id', $user->id)
            ->orderBy('start_time')
            ->get();

        foreach ($events as $event) {
            array_push($lines, ...$this->buildCalendarEventVEvent($event, $tz, $dtStamp));
        }

        $tasksQuery = Task::with(['category', 'project'])
            ->where('user_id', $user->id)
            ->whereNotNull('due_date');

        if ($includeCancelled) {
            $tasksQuery->withTrashed();
        }

        foreach ($tasksQuery->get() as $task) {
            array_push($lines, ...$this->buildTaskVEvent($task, $user, $dtStamp));
        }

        $projectsQuery = Project::with('category')
            ->where('user_id', $user->id)
            ->whereNotNull('target_date');

        if ($includeCancelled) {
            $projectsQuery->withTrashed();
        }

        foreach ($projectsQuery->get() as $project) {
            array_push($lines, ...$this->buildProjectVEvent($project, $dtStamp));
        }

        $milestones = ProjectMilestone::with(['project' => fn ($q) => $q->withTrashed()])
            ->whereHas('project', function ($q) use ($user, $includeCancelled): void {
                if ($includeCancelled) {
                    $q->withTrashed();
                }
                $q->where('user_id', $user->id);
            })
            ->whereNotNull('due_date')
            ->get();

        foreach ($milestones as $milestone) {
            array_push($lines, ...$this->buildMilestoneVEvent($milestone, $user, $dtStamp));
        }

        $lines[] = 'END:VCALENDAR';

        $folded = array_map(fn (string $line): string => $this->foldLine($line), $lines);

        return implode("\r\n", $folded)."\r\n";
    }

    /**
     * @return array<int, string>
     */
    private function buildCalendarEventVEvent(CalendarEvent $event, string $tz, string $dtStamp): array
    {
        $uid = "event-{$event->id}@personal-operations-hub.local";
        $sequence = $this->computeSequence($event->created_at, $event->updated_at);
        $lastMod = ($event->updated_at ?? now())->copy()->utc()->format('Ymd\THis\Z');

        $block = [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "LAST-MODIFIED:{$lastMod}",
            "SEQUENCE:{$sequence}",
            $this->escapeProperty('SUMMARY', $event->title),
            'STATUS:CONFIRMED',
        ];

        if ($event->all_day) {
            $startDate = $event->start_time->copy()->setTimezone($tz)->format('Ymd');
            $endDate = $event->end_time->copy()->setTimezone($tz)->addDay()->format('Ymd');
            $block[] = "DTSTART;VALUE=DATE:{$startDate}";
            $block[] = "DTEND;VALUE=DATE:{$endDate}";
        } else {
            $dtStart = $event->start_time->copy()->utc()->format('Ymd\THis\Z');
            $dtEnd = $event->end_time->copy()->utc()->format('Ymd\THis\Z');
            $block[] = "DTSTART:{$dtStart}";
            $block[] = "DTEND:{$dtEnd}";
        }

        if (! empty($event->description)) {
            $block[] = $this->escapeProperty('DESCRIPTION', $event->description);
        }

        if (! empty($event->location)) {
            $block[] = $this->escapeProperty('LOCATION', $event->location);
        }

        if ($event->category) {
            $block[] = $this->escapeProperty('CATEGORIES', $event->category->name);
        }

        if ($event->reminder_minutes !== null) {
            $trigger = $this->minutesToTrigger((int) $event->reminder_minutes);
            array_push($block, ...$this->buildValarm($trigger, "Event: {$event->title}"));
        }

        $block[] = 'END:VEVENT';

        return $block;
    }

    /**
     * @return array<int, string>
     */
    private function buildTaskVEvent(Task $task, User $user, string $dtStamp): array
    {
        $uid = "task-{$task->id}@personal-operations-hub.local";
        $sequence = $this->computeSequence($task->created_at, $task->updated_at);
        $lastMod = ($task->updated_at ?? now())->copy()->utc()->format('Ymd\THis\Z');
        $dueDateStr = $task->due_date->toDateString();

        $status = $task->trashed()
            ? 'CANCELLED'
            : ($task->status === 'completed' ? 'COMPLETED' : 'CONFIRMED');

        $summaryPrefix = $task->status === 'completed' ? '[Done] ' : '[Task] ';
        $formattedTime = $task->formatted_due_time ?: $task->due_time;
        $descriptionParts = array_filter([
            $task->description,
            'Priority: '.strtoupper($task->priority),
            $task->project ? "Project: {$task->project->name}" : null,
            $task->next_action ? "Next Action: {$task->next_action}" : null,
            "Deadline: {$dueDateStr}".($task->due_time ? " {$formattedTime}" : ''),
        ]);

        $block = [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "LAST-MODIFIED:{$lastMod}",
            "SEQUENCE:{$sequence}",
            $this->escapeProperty('SUMMARY', $summaryPrefix.$task->title),
            "STATUS:{$status}",
            $this->escapeProperty('DESCRIPTION', implode("\n", $descriptionParts)),
        ];

        $eventStartUtc = null;

        if (! empty($task->due_time)) {
            $dueLocal = UserDateTime::parseLocalDateTime($dueDateStr, $task->due_time, $user, '09:00');
            $durationMin = max(15, (int) ($task->estimated_duration ?: 30));
            $startLocal = $dueLocal->copy()->subMinutes($durationMin);
            $eventStartUtc = $dueLocal->copy()->utc();

            $block[] = 'DTSTART:'.$startLocal->utc()->format('Ymd\THis\Z');
            $block[] = 'DTEND:'.$dueLocal->utc()->format('Ymd\THis\Z');
        } else {
            $startCompact = str_replace('-', '', $dueDateStr);
            $endCompact = Carbon::createFromFormat('Y-m-d', $dueDateStr, 'UTC')->addDay()->format('Ymd');
            $block[] = "DTSTART;VALUE=DATE:{$startCompact}";
            $block[] = "DTEND;VALUE=DATE:{$endCompact}";
        }

        if ($task->category) {
            $block[] = $this->escapeProperty('CATEGORIES', $task->category->name);
        }

        if ($status === 'CONFIRMED') {
            foreach ($this->resolveTaskAlarmTriggers($task, $eventStartUtc) as $trigger) {
                array_push($block, ...$this->buildValarm($trigger, "Task Due: {$task->title}"));
            }
        }

        $block[] = 'END:VEVENT';

        return $block;
    }

    /**
     * @return array<int, string>
     */
    private function buildProjectVEvent(Project $project, string $dtStamp): array
    {
        $uid = "project-{$project->id}@personal-operations-hub.local";
        $sequence = $this->computeSequence($project->created_at, $project->updated_at);
        $lastMod = ($project->updated_at ?? now())->copy()->utc()->format('Ymd\THis\Z');
        $targetStr = $project->target_date->toDateString();

        $status = ($project->trashed() || $project->status === 'archived')
            ? 'CANCELLED'
            : ($project->status === 'completed' ? 'COMPLETED' : 'CONFIRMED');

        $startCompact = str_replace('-', '', $targetStr);
        $endCompact = Carbon::createFromFormat('Y-m-d', $targetStr, 'UTC')->addDay()->format('Ymd');

        $desc = implode("\n", array_filter([
            $project->description,
            "Project Status: {$project->status}",
            "Target Deadline: {$targetStr}",
        ]));

        $block = [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "LAST-MODIFIED:{$lastMod}",
            "SEQUENCE:{$sequence}",
            $this->escapeProperty('SUMMARY', "[Project Deadline] {$project->name}"),
            "STATUS:{$status}",
            "DTSTART;VALUE=DATE:{$startCompact}",
            "DTEND;VALUE=DATE:{$endCompact}",
            $this->escapeProperty('DESCRIPTION', $desc),
        ];

        if ($status === 'CONFIRMED' && $project->reminders_enabled) {
            $days = is_array($project->reminder_days) && ! empty($project->reminder_days)
                ? $project->reminder_days
                : UserSetting::defaultProjectReminderDays();

            foreach (collect($days)->map(fn (mixed $d): int => max(0, (int) $d))->unique() as $daysBefore) {
                $trigger = $daysBefore === 0 ? 'PT0S' : "-P{$daysBefore}D";
                array_push($block, ...$this->buildValarm($trigger, "Project Deadline: {$project->name}"));
            }
        }

        $block[] = 'END:VEVENT';

        return $block;
    }

    /**
     * @return array<int, string>
     */
    private function buildMilestoneVEvent(ProjectMilestone $milestone, User $user, string $dtStamp): array
    {
        $project = $milestone->project;
        $uid = "milestone-{$milestone->id}@personal-operations-hub.local";
        $sequence = $this->computeSequence($milestone->created_at, $milestone->updated_at);
        $lastMod = ($milestone->updated_at ?? now())->copy()->utc()->format('Ymd\THis\Z');
        $dueStr = $milestone->due_date->toDateString();

        $status = (! $project || $project->trashed() || $project->status === 'archived')
            ? 'CANCELLED'
            : ($milestone->is_completed ? 'COMPLETED' : 'CONFIRMED');

        $projectName = $project?->name ?? 'Project';

        $block = [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "LAST-MODIFIED:{$lastMod}",
            "SEQUENCE:{$sequence}",
            $this->escapeProperty('SUMMARY', "[Milestone] {$milestone->title} ({$projectName})"),
            "STATUS:{$status}",
            $this->escapeProperty('DESCRIPTION', "Project: {$projectName}\nMilestone Deadline: {$dueStr}"),
        ];

        if (! empty($milestone->due_time)) {
            $dueLocal = UserDateTime::parseLocalDateTime($dueStr, $milestone->due_time, $user, '09:00');
            $startLocal = $dueLocal->copy()->subMinutes(30);
            $block[] = 'DTSTART:'.$startLocal->utc()->format('Ymd\THis\Z');
            $block[] = 'DTEND:'.$dueLocal->utc()->format('Ymd\THis\Z');
        } else {
            $startCompact = str_replace('-', '', $dueStr);
            $endCompact = Carbon::createFromFormat('Y-m-d', $dueStr, 'UTC')->addDay()->format('Ymd');
            $block[] = "DTSTART;VALUE=DATE:{$startCompact}";
            $block[] = "DTEND;VALUE=DATE:{$endCompact}";
        }

        if ($status === 'CONFIRMED' && $milestone->reminders_enabled) {
            $offsets = is_array($milestone->reminder_offsets) && ! empty($milestone->reminder_offsets)
                ? $milestone->reminder_offsets
                : [1, 0];
            foreach (collect($offsets)->map(fn (mixed $d): int => max(0, (int) $d))->unique() as $daysBefore) {
                $trigger = $daysBefore === 0 ? 'PT0S' : "-P{$daysBefore}D";
                array_push($block, ...$this->buildValarm($trigger, "Milestone: {$milestone->title}"));
            }
        }

        $block[] = 'END:VEVENT';

        return $block;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTaskAlarmTriggers(Task $task, ?Carbon $dueUtc): array
    {
        $triggers = [];
        $preset = $task->reminder_preset ?: 'none';

        $presetTrigger = match ($preset) {
            'at_due' => 'PT0S',
            '15m' => '-PT15M',
            '30m' => '-PT30M',
            '1h' => '-PT1H',
            '1d' => '-P1D',
            'custom' => ($task->remind_at !== null)
                ? ($dueUtc !== null
                    ? $this->minutesToTrigger(max(0, (int) $task->remind_at->copy()->utc()->diffInMinutes($dueUtc, false)))
                    : 'VALUE=DATE-TIME:'.$task->remind_at->copy()->utc()->format('Ymd\THis\Z'))
                : null,
            default => null,
        };

        if ($presetTrigger !== null) {
            $triggers[] = $presetTrigger;
        }

        if (is_array($task->custom_reminder_times)) {
            foreach ($task->custom_reminder_times as $extra) {
                if (! is_string($extra)) {
                    continue;
                }
                $mapped = match (trim($extra)) {
                    'at_due' => 'PT0S',
                    '15m' => '-PT15M',
                    '30m' => '-PT30M',
                    '1h' => '-PT1H',
                    '1d' => '-P1D',
                    default => null,
                };
                if ($mapped !== null) {
                    $triggers[] = $mapped;
                }
            }
        }

        return array_values(array_unique($triggers));
    }

    /**
     * @return array<int, string>
     */
    private function buildValarm(string $trigger, string $description): array
    {
        $triggerLine = str_starts_with($trigger, 'VALUE=DATE-TIME:')
            ? "TRIGGER;{$trigger}"
            : "TRIGGER:{$trigger}";

        return [
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            $this->escapeProperty('DESCRIPTION', $description),
            $triggerLine,
            'END:VALARM',
        ];
    }

    private function minutesToTrigger(int $minutesBefore): string
    {
        if ($minutesBefore <= 0) {
            return 'PT0S';
        }

        if ($minutesBefore % 1440 === 0) {
            $days = intdiv($minutesBefore, 1440);

            return "-P{$days}D";
        }

        if ($minutesBefore % 60 === 0) {
            $hours = intdiv($minutesBefore, 60);

            return "-PT{$hours}H";
        }

        return "-PT{$minutesBefore}M";
    }

    private function computeSequence(?Carbon $createdAt, ?Carbon $updatedAt): int
    {
        if (! $createdAt || ! $updatedAt) {
            return 0;
        }

        return max(0, (int) $createdAt->diffInSeconds($updatedAt));
    }

    private function escapeProperty(string $property, string $rawValue): string
    {
        $escaped = str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $rawValue
        );

        return "{$property}:{$escaped}";
    }

    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $remaining = $line;
        $maxBytes = 75;

        while (strlen($remaining) > $maxBytes) {
            $slice = mb_strcut($remaining, 0, $maxBytes, 'UTF-8');
            $chunks[] = $slice;
            $remaining = substr($remaining, strlen($slice));
            $maxBytes = 74;
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return implode("\r\n ", $chunks);
    }
}
