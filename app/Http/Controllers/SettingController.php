<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Habit;
use App\Models\UserSetting;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $setting = $user->ensureSetting();

        $categories = Category::where('user_id', $user->id)->ordered()->get();
        $habits = Habit::where('user_id', $user->id)->orderBy('sort_order')->get();

        $rawFeedToken = $setting->calendar_feed_enabled ? $setting->decryptedCalendarFeedToken() : null;
        $calendarFeedUrl = $rawFeedToken ? route('calendar.feed', ['token' => $rawFeedToken]) : null;

        return Inertia::render('Settings/Index', [
            'setting' => $setting,
            'categories' => $categories,
            'habits' => $habits,
            'timezones' => timezone_identifiers_list(),
            'calendarFeedUrl' => $calendarFeedUrl,
        ]);
    }

    public function update(Request $request, ReminderService $reminderService): RedirectResponse
    {
        $user = $request->user();
        $setting = $user->ensureSetting();

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:100',
            'theme' => 'required|in:light,dark,system',
            'language' => 'required|string|max:10',
            'timezone' => 'sometimes|required|timezone:all',
            'week_start' => 'required|integer|in:0,1',
            'date_format' => 'required|string|max:20',
            'time_format' => 'required|in:12h,24h',
            'default_priority' => 'required|in:high,medium,low',
            'default_task_duration' => 'required|integer|min:5|max:480',
            'phone_usage_target_minutes' => 'required|integer|min:0|max:1440',
            'weekly_review_prompts' => 'required|array',
            'weekly_review_prompts.*' => 'required|string|max:500',
            'quiet_hours_enabled' => 'sometimes|boolean',
            'quiet_hours_start' => ['sometimes', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'quiet_hours_end' => ['sometimes', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'allow_critical_in_quiet_hours' => 'sometimes|boolean',
            'notifications_enabled' => 'sometimes|boolean',
            'browser_notifications_enabled' => 'sometimes|boolean',
            'notification_sound_enabled' => 'sometimes|boolean',
            'notification_sound' => 'sometimes|in:chime,bell,soft_ping,digital_alarm',
            'notification_volume' => 'sometimes|integer|min:0|max:100',
            'notification_preferences' => 'sometimes|array',
            'daily_briefing_enabled' => 'sometimes|boolean',
            'daily_briefing_time' => ['sometimes', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'weekly_digest_enabled' => 'sometimes|boolean',
            'weekly_digest_day' => 'sometimes|integer|min:0|max:6',
            'weekly_digest_time' => ['sometimes', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'default_task_reminder_preset' => 'sometimes|in:none,at_due,15m,30m,1h,1d',
            'project_reminder_days' => 'sometimes|array',
            'project_reminder_days.*' => 'integer|min:0|max:365',
            'backup_enabled' => 'sometimes|boolean',
            'backup_retention_days' => 'sometimes|integer|min:1|max:365',
            'focus_work_minutes' => 'sometimes|integer|min:1|max:180',
            'focus_short_break_minutes' => 'sometimes|integer|min:1|max:60',
            'focus_long_break_minutes' => 'sometimes|integer|min:1|max:90',
            'focus_auto_start_breaks' => 'sometimes|boolean',
        ]);

        foreach (['quiet_hours_start', 'quiet_hours_end', 'daily_briefing_time', 'weekly_digest_time'] as $timeField) {
            if (! empty($validated[$timeField])) {
                $validated[$timeField] = substr((string) $validated[$timeField], 0, 5);
            }
        }

        if (isset($validated['notification_preferences']) && is_array($validated['notification_preferences'])) {
            $mergedPrefs = UserSetting::defaultNotificationPreferences();
            foreach ($mergedPrefs as $key => $defaultBool) {
                if (array_key_exists($key, $validated['notification_preferences'])) {
                    $mergedPrefs[$key] = (bool) $validated['notification_preferences'][$key];
                }
            }
            $validated['notification_preferences'] = $mergedPrefs;
        }

        $oldTimezone = $setting->timezone;
        $oldProjectDays = $setting->project_reminder_days;

        $setting->update($validated);

        if (! empty($validated['display_name']) && $validated['display_name'] !== $user->name) {
            $user->update(['name' => $validated['display_name']]);
        }

        if (
            (isset($validated['timezone']) && $validated['timezone'] !== $oldTimezone)
            || (isset($validated['project_reminder_days']) && $validated['project_reminder_days'] !== $oldProjectDays)
        ) {
            $user->refresh();
            $reminderService->resyncAllForUser($user);
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
