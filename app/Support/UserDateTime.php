<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

class UserDateTime
{
    /**
     * Resolve a valid IANA timezone identifier for the given user.
     */
    public static function resolveTimezone(?User $user): string
    {
        $tz = $user?->setting?->timezone ?: config('app.timezone', 'UTC');

        if (! is_string($tz) || ! in_array($tz, timezone_identifiers_list(), true)) {
            return 'UTC';
        }

        return $tz;
    }

    /**
     * Current moment in the user's configured timezone.
     */
    public static function nowForUser(?User $user): Carbon
    {
        return Carbon::now(self::resolveTimezone($user));
    }

    /**
     * Start of today in the user's configured timezone.
     */
    public static function todayForUser(?User $user): Carbon
    {
        return self::nowForUser($user)->startOfDay();
    }

    /**
     * Today's YYYY-MM-DD date string in the user's configured timezone.
     */
    public static function todayDateString(?User $user): string
    {
        return self::nowForUser($user)->toDateString();
    }

    /**
     * Parse a local YYYY-MM-DD date and optional HH:MM time in the user's timezone without shifting dates.
     */
    public static function parseLocalDateTime(
        string $date,
        ?string $time,
        ?User $user,
        string $defaultTime = '09:00'
    ): Carbon {
        $cleanDate = substr(trim($date), 0, 10);
        $cleanTime = ($time !== null && preg_match('/^\d{2}:\d{2}/', trim($time)))
            ? substr(trim($time), 0, 5)
            : $defaultTime;

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$cleanDate} {$cleanTime}",
            self::resolveTimezone($user)
        )->seconds(0);
    }

    /**
     * Determine whether the given moment falls inside the user's configured quiet hours.
     */
    public static function isInQuietHours(User $user, ?Carbon $moment = null): bool
    {
        $setting = $user->setting;
        if (! $setting || ! $setting->quiet_hours_enabled) {
            return false;
        }

        $tz = self::resolveTimezone($user);
        $localMoment = $moment ? $moment->copy()->setTimezone($tz) : self::nowForUser($user);
        $currentHm = $localMoment->format('H:i');

        $startHm = substr($setting->quiet_hours_start ?: '22:00', 0, 5);
        $endHm = substr($setting->quiet_hours_end ?: '07:00', 0, 5);

        if ($startHm === $endHm) {
            return false;
        }

        if ($startHm < $endHm) {
            return $currentHm >= $startHm && $currentHm < $endHm;
        }

        return $currentHm >= $startHm || $currentHm < $endHm;
    }

    /**
     * Compute the UTC timestamp when the user's active quiet-hours window ends.
     */
    public static function nextQuietHoursEndUtc(User $user, ?Carbon $moment = null): Carbon
    {
        $setting = $user->setting;
        $tz = self::resolveTimezone($user);
        $localMoment = $moment ? $moment->copy()->setTimezone($tz) : self::nowForUser($user);
        $endHm = substr($setting?->quiet_hours_end ?: '07:00', 0, 5);

        [$endHour, $endMinute] = array_map('intval', explode(':', $endHm));
        $candidate = $localMoment->copy()->setTime($endHour, $endMinute, 0);

        if ($candidate->lessThanOrEqualTo($localMoment)) {
            $candidate->addDay();
        }

        return $candidate->utc();
    }

    /**
     * Format an HH:MM(:SS) or existing time string to 12-hour format with AM/PM (e.g., 2:30 PM).
     */
    public static function formatTimeAmPm(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $clean = trim($time);

        // Already formatted with AM/PM
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(am|pm)$/i', $clean, $matches)) {
            $h = (int) $matches[1];
            $m = $matches[2];
            $p = strtoupper($matches[3]);

            return "{$h}:{$m} {$p}";
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})/', $clean, $matches)) {
            return $clean;
        }

        $hour = (int) $matches[1];
        $minute = $matches[2];
        $period = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 ?: 12;

        return "{$hour12}:{$minute} {$period}";
    }
}
