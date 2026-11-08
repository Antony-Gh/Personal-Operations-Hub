<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Category,
    Habit,
    NotificationCategory,
    NotificationSoundPreset,
    TaskReminderPreset,
    UserSetting,
} from '@/types';
import { useTheme, ThemePreference } from '@/composables/useTheme';
import { useBrowserNotifications } from '@/composables/useBrowserNotifications';
import { useNotificationSound } from '@/composables/useNotificationSound';
import {
    Settings as SettingsIcon,
    Save,
    Database,
    Globe,
    Bell,
    Volume2,
    Moon,
    Timer,
    Link2,
    Copy,
    Check,
    Trash2,
    RefreshCw,
} from 'lucide-vue-next';

const props = defineProps<{
    setting: UserSetting;
    categories: Category[];
    habits: Habit[];
    timezones?: string[];
    calendarFeedUrl?: string | null;
}>();

const { setTheme } = useTheme();
const { isSupported, permissionState, requestPermission, sendDesktopNotification } = useBrowserNotifications();
const { playPreset } = useNotificationSound();

const savedNotificationPrefs: Partial<Record<NotificationCategory, boolean>> =
    props.setting.notification_preferences ?? {};

const defaultNotificationPrefs: Record<NotificationCategory, boolean> = {
    task_reminders: savedNotificationPrefs.task_reminders ?? true,
    overdue_tasks: savedNotificationPrefs.overdue_tasks ?? true,
    project_deadlines: savedNotificationPrefs.project_deadlines ?? true,
    milestone_deadlines: savedNotificationPrefs.milestone_deadlines ?? true,
    habit_reminders: savedNotificationPrefs.habit_reminders ?? true,
    focus_timer: savedNotificationPrefs.focus_timer ?? true,
    daily_briefing: savedNotificationPrefs.daily_briefing ?? true,
    weekly_digest: savedNotificationPrefs.weekly_digest ?? true,
    system_notifications: savedNotificationPrefs.system_notifications ?? true,
};

const settingForm = useForm({
    display_name: props.setting.display_name || '',
    theme: (props.setting.theme as ThemePreference) || 'light',
    language: props.setting.language || 'en',
    timezone: props.setting.timezone || 'UTC',
    week_start: props.setting.week_start ?? 1,
    date_format: props.setting.date_format || 'YYYY-MM-DD',
    time_format: props.setting.time_format || '24h',
    default_priority: props.setting.default_priority || 'medium',
    default_task_duration: props.setting.default_task_duration || 30,
    phone_usage_target_minutes: props.setting.phone_usage_target_minutes ?? 60,
    weekly_review_prompts: [...(props.setting.weekly_review_prompts || [])],
    notifications_enabled: props.setting.notifications_enabled ?? true,
    browser_notifications_enabled: props.setting.browser_notifications_enabled ?? true,
    windows_toast_enabled: props.setting.windows_toast_enabled ?? false,
    notification_sound_enabled: props.setting.notification_sound_enabled ?? true,
    notification_sound: (props.setting.notification_sound || 'chime') as NotificationSoundPreset,
    notification_volume: props.setting.notification_volume ?? 70,
    notification_preferences: defaultNotificationPrefs,
    quiet_hours_enabled: props.setting.quiet_hours_enabled ?? false,
    quiet_hours_start: props.setting.quiet_hours_start || '22:00',
    quiet_hours_end: props.setting.quiet_hours_end || '07:00',
    allow_critical_in_quiet_hours: props.setting.allow_critical_in_quiet_hours ?? true,
    daily_briefing_enabled: props.setting.daily_briefing_enabled ?? true,
    daily_briefing_time: props.setting.daily_briefing_time || '08:00',
    evening_checkin_nudge_enabled: props.setting.evening_checkin_nudge_enabled ?? false,
    evening_checkin_nudge_time: props.setting.evening_checkin_nudge_time || '20:30',
    weekly_digest_enabled: props.setting.weekly_digest_enabled ?? true,
    weekly_digest_day: props.setting.weekly_digest_day ?? 0,
    weekly_digest_time: props.setting.weekly_digest_time || '18:00',
    default_task_reminder_preset: (props.setting.default_task_reminder_preset || 'none') as Exclude<TaskReminderPreset, 'custom'>,
    project_reminder_days: Array.isArray(props.setting.project_reminder_days)
        ? [...props.setting.project_reminder_days]
        : [7, 3, 1, 0],
    backup_enabled: props.setting.backup_enabled ?? true,
    backup_retention_days: props.setting.backup_retention_days ?? 14,
    focus_work_minutes: props.setting.focus_work_minutes ?? 25,
    focus_short_break_minutes: props.setting.focus_short_break_minutes ?? 5,
    focus_long_break_minutes: props.setting.focus_long_break_minutes ?? 15,
    focus_auto_start_breaks: props.setting.focus_auto_start_breaks ?? false,
});

watch(
    () => settingForm.theme,
    (val) => {
        if (val) {
            setTheme(val as ThemePreference);
        }
    }
);

// Timezone filter & auto-detect
const tzFilter = ref('');
const availableTimezones = computed(() => {
    const all = props.timezones && props.timezones.length > 0 ? props.timezones : ['UTC'];
    const q = tzFilter.value.trim().toLowerCase();
    if (!q) {
        return all;
    }
    return all.filter((tz) => tz.toLowerCase().includes(q) || tz === settingForm.timezone);
});

const detectSystemTimezone = () => {
    try {
        const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (detected) {
            settingForm.timezone = detected;
        }
    } catch {
        // Ignore if unsupported
    }
};

// Notification category labels
const notificationCategoryItems: { key: NotificationCategory; label: string; desc: string }[] = [
    { key: 'task_reminders', label: 'Task Reminders', desc: 'Scheduled task due alarms & snoozed alerts' },
    { key: 'overdue_tasks', label: 'Overdue Tasks', desc: 'Critical alerts when tasks pass their due time' },
    { key: 'project_deadlines', label: 'Project Deadlines', desc: '7d / 3d / 1d / target-day project reminders' },
    { key: 'milestone_deadlines', label: 'Milestone Stage Gates', desc: 'Milestone due reminders & completions' },
    { key: 'habit_reminders', label: 'Habit Gentle Nudges', desc: 'Spiritual & daily discipline check-in reminders' },
    { key: 'focus_timer', label: 'Focus & Break Timer', desc: 'Pomodoro session & break completion alerts' },
    { key: 'daily_briefing', label: 'Daily Morning Briefing', desc: 'Scheduled local morning plan summary' },
    { key: 'weekly_digest', label: 'Weekly Digest', desc: 'Scheduled weekly reflection & metrics summary' },
    { key: 'system_notifications', label: 'System & Backup Notices', desc: 'Local backup verification & system status' },
];

const projectReminderOptions = [
    { days: 7, label: '7 days before' },
    { days: 3, label: '3 days before' },
    { days: 1, label: '1 day before' },
    { days: 0, label: 'On target day' },
];

const toggleDefaultProjectDay = (day: number) => {
    const idx = settingForm.project_reminder_days.indexOf(day);
    if (idx >= 0) {
        settingForm.project_reminder_days.splice(idx, 1);
    } else {
        settingForm.project_reminder_days.push(day);
    }
};

const previewSound = () => {
    playPreset(settingForm.notification_sound, settingForm.notification_volume);
};

const sendTestBrowserNotification = async () => {
    if (permissionState.value !== 'granted') {
        const res = await requestPermission();
        if (res !== 'granted') return;
    }
    playPreset(settingForm.notification_sound, settingForm.notification_volume);
    await sendDesktopNotification(
        'Personal Operations Hub — Test Alert',
        'Local Windows browser notification and sound are working properly.',
        '/settings'
    );
};

const sendTestWindowsToast = () => {
    router.post(route('settings.test-windows-toast'), {}, { preserveScroll: true });
};

// Calendar feed controls
const copiedFeed = ref(false);
const copyCalendarFeed = async () => {
    if (!props.calendarFeedUrl) return;
    try {
        await navigator.clipboard.writeText(props.calendarFeedUrl);
        copiedFeed.value = true;
        setTimeout(() => {
            copiedFeed.value = false;
        }, 2000);
    } catch {
        // Ignore clipboard errors
    }
};

const generateCalendarToken = () => {
    router.post(route('calendar.feed.generate'), {}, { preserveScroll: true });
};

const revokeCalendarToken = () => {
    router.delete(route('calendar.feed.revoke'), { preserveScroll: true });
};

// Weekly Review Prompts
const newPromptText = ref('');

const addPrompt = () => {
    if (!newPromptText.value.trim()) return;
    settingForm.weekly_review_prompts.push(newPromptText.value.trim());
    newPromptText.value = '';
};

const removePrompt = (index: number) => {
    settingForm.weekly_review_prompts.splice(index, 1);
};

const submitSettings = () => {
    settingForm.put(route('settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout title="Settings">
        <Head title="Settings & Customization" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header with Data Management Quick Link -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <SettingsIcon class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Workspace Preferences</h2>
                        <p class="text-xs text-neutral-400">
                            Timezone, local alarms, desktop notifications, quiet hours, Pomodoro defaults & automated backups
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('settings.data')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 text-xs font-medium transition"
                    >
                        <Database class="w-4 h-4" />
                        <span>Data & Backups</span>
                    </Link>
                </div>
            </div>

            <!-- Settings Form -->
            <form @submit.prevent="submitSettings" class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs space-y-8 text-xs">
                <!-- 1. Profile & Appearance -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        Profile & Appearance
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Display Name</label>
                            <input
                                v-model="settingForm.display_name"
                                type="text"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Theme</label>
                            <select
                                v-model="settingForm.theme"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="light">Light Theme</option>
                                <option value="dark">Dark Theme</option>
                                <option value="system">System Default</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2. Timezone, Calendar & Scheduling Rules -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        <div class="flex items-center gap-2">
                            <Globe class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                                Timezone & Scheduling Rules
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="detectSystemTimezone"
                            class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                        >
                            Detect Windows Timezone
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Active Timezone ({{ settingForm.timezone }})</label>
                            <select
                                v-model="settingForm.timezone"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option v-for="tz in availableTimezones" :key="tz" :value="tz">
                                    {{ tz }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Filter Timezones</label>
                            <input
                                v-model="tzFilter"
                                type="text"
                                placeholder="Search city or region (e.g. Cairo, London, New_York)..."
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Week Starts On</label>
                            <select
                                v-model.number="settingForm.week_start"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option :value="1">Monday</option>
                                <option :value="0">Sunday</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Date Format</label>
                            <select
                                v-model="settingForm.date_format"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="YYYY-MM-DD">YYYY-MM-DD (ISO)</option>
                                <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Time Format</label>
                            <select
                                v-model="settingForm.time_format"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="24h">24-hour (14:00)</option>
                                <option value="12h">12-hour (2:00 PM)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Default Task Priority</label>
                            <select
                                v-model="settingForm.default_priority"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Default Task Duration (min)</label>
                            <input
                                v-model.number="settingForm.default_task_duration"
                                type="number"
                                min="5"
                                step="5"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Daily Phone Target (min)</label>
                            <input
                                v-model.number="settingForm.phone_usage_target_minutes"
                                type="number"
                                min="0"
                                max="1440"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- 3. Notifications, Browser Alerts & Sound -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        <div class="flex items-center gap-2">
                            <Bell class="w-4 h-4 text-indigo-500" />
                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                                Local Notifications, Desktop Alerts & Sound
                            </h3>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button
                                type="button"
                                @click="sendTestWindowsToast"
                                class="px-3 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium hover:bg-emerald-100 transition"
                            >
                                Test Native Windows Toast
                            </button>
                            <button
                                type="button"
                                @click="sendTestBrowserNotification"
                                class="px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-medium hover:bg-indigo-100 transition"
                            >
                                Test Browser & Sound Alert
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <label class="flex items-center gap-2.5 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 cursor-pointer">
                            <input type="checkbox" v-model="settingForm.notifications_enabled" class="rounded text-indigo-600" />
                            <div>
                                <span class="font-semibold text-neutral-900 dark:text-white block">In-App Notifications</span>
                                <span class="text-[10px] text-neutral-400">Enable Notification Center & alarms</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 cursor-pointer">
                            <input type="checkbox" v-model="settingForm.windows_toast_enabled" class="rounded text-indigo-600" />
                            <div>
                                <span class="font-semibold text-neutral-900 dark:text-white block">Browserless Windows Toast</span>
                                <span class="text-[10px] text-neutral-400">Native Action Center alert even with browser closed</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 cursor-pointer">
                            <input type="checkbox" v-model="settingForm.browser_notifications_enabled" class="rounded text-indigo-600" />
                            <div>
                                <span class="font-semibold text-neutral-900 dark:text-white block">Windows Browser Popups</span>
                                <span class="text-[10px] text-neutral-400">
                                    Permission: {{ isSupported ? permissionState : 'unsupported' }}
                                </span>
                            </div>
                        </label>

                        <label class="flex items-center gap-2.5 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 cursor-pointer">
                            <input type="checkbox" v-model="settingForm.notification_sound_enabled" class="rounded text-indigo-600" />
                            <div>
                                <span class="font-semibold text-neutral-900 dark:text-white block">Notification Sound</span>
                                <span class="text-[10px] text-neutral-400">Synthesized offline Web Audio</span>
                            </div>
                        </label>
                    </div>

                    <div class="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px]">
                        <span class="text-neutral-600 dark:text-neutral-300">
                            <strong>Windows System Tray Companion:</strong> Launch live status icon, Quick Capture & 1-click backups from your taskbar:
                        </span>
                        <code class="px-2 py-1 rounded bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 font-mono text-[10px] text-indigo-600 dark:text-indigo-400 select-all">
                            powershell -ExecutionPolicy Bypass -File .\scripts\tray-companion.ps1
                        </code>
                    </div>

                    <div v-if="isSupported && permissionState !== 'granted'" class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 flex items-center justify-between gap-3">
                        <span class="text-amber-800 dark:text-amber-300">
                            Browser desktop notifications require permission ({{ permissionState }}).
                        </span>
                        <button
                            type="button"
                            @click="requestPermission"
                            class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-medium shrink-0"
                        >
                            Grant Permission
                        </button>
                    </div>

                    <!-- Sound Preset & Volume -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block font-medium mb-1">Alarm Sound Tone</label>
                            <select
                                v-model="settingForm.notification_sound"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="chime">Warm Chime</option>
                                <option value="bell">Clear Bell</option>
                                <option value="soft_ping">Soft Ping</option>
                                <option value="digital_alarm">Digital Alarm</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Sound Volume ({{ settingForm.notification_volume }}%)</label>
                            <input
                                v-model.number="settingForm.notification_volume"
                                type="range"
                                min="0"
                                max="100"
                                step="5"
                                class="w-full accent-indigo-600"
                            />
                        </div>

                        <div>
                            <button
                                type="button"
                                @click="previewSound"
                                class="w-full py-2 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 inline-flex items-center justify-center gap-1.5 font-medium"
                            >
                                <Volume2 class="w-3.5 h-3.5 text-indigo-500" />
                                <span>Preview Tone</span>
                            </button>
                        </div>
                    </div>

                    <!-- Per-category Notification Toggles -->
                    <div>
                        <label class="block font-semibold text-neutral-800 dark:text-neutral-200 mb-2">
                            Notification Categories
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                            <label
                                v-for="item in notificationCategoryItems"
                                :key="item.key"
                                class="flex items-start gap-2 p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    v-model="settingForm.notification_preferences[item.key]"
                                    class="mt-0.5 rounded text-indigo-600"
                                />
                                <div>
                                    <span class="font-medium text-neutral-900 dark:text-white block">{{ item.label }}</span>
                                    <span class="text-[10px] text-neutral-400">{{ item.desc }}</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 4. Quiet Hours / Do Not Disturb -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        <Moon class="w-4 h-4 text-indigo-500" />
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                            Quiet Hours (Do Not Disturb)
                        </h3>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <label class="inline-flex items-center gap-2 cursor-pointer font-medium">
                            <input type="checkbox" v-model="settingForm.quiet_hours_enabled" class="rounded text-indigo-600" />
                            <span>Defer non-critical reminders during Quiet Hours</span>
                        </label>

                        <label v-if="settingForm.quiet_hours_enabled" class="inline-flex items-center gap-2 cursor-pointer text-neutral-600 dark:text-neutral-400">
                            <input type="checkbox" v-model="settingForm.allow_critical_in_quiet_hours" class="rounded text-indigo-600" />
                            <span>Allow critical overdue alerts through Quiet Hours</span>
                        </label>
                    </div>

                    <div v-if="settingForm.quiet_hours_enabled" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Quiet Start</label>
                            <input
                                v-model="settingForm.quiet_hours_start"
                                type="time"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Quiet End</label>
                            <input
                                v-model="settingForm.quiet_hours_end"
                                type="time"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- 5. Briefings & Reminder Defaults -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        Briefings & Default Deadline Alarms
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-2.5">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-neutral-900 dark:text-white">
                                <input type="checkbox" v-model="settingForm.daily_briefing_enabled" class="rounded text-indigo-600" />
                                <span>Automated Morning Briefing</span>
                            </label>
                            <div>
                                <label class="block text-[11px] text-neutral-500 mb-1">Delivery Time (Local)</label>
                                <input
                                    v-model="settingForm.daily_briefing_time"
                                    type="time"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-2.5">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-neutral-900 dark:text-white">
                                <input type="checkbox" v-model="settingForm.evening_checkin_nudge_enabled" class="rounded text-indigo-600" />
                                <span>Evening Habit Check-In Nudge</span>
                            </label>
                            <div>
                                <label class="block text-[11px] text-neutral-500 mb-1">Gentle Nudge Time (If Unlogged)</label>
                                <input
                                    v-model="settingForm.evening_checkin_nudge_time"
                                    type="time"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-2.5">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-neutral-900 dark:text-white">
                                <input type="checkbox" v-model="settingForm.weekly_digest_enabled" class="rounded text-indigo-600" />
                                <span>Automated Weekly Digest</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] text-neutral-500 mb-1">Day</label>
                                    <select
                                        v-model.number="settingForm.weekly_digest_day"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    >
                                        <option :value="0">Sunday</option>
                                        <option :value="1">Monday</option>
                                        <option :value="5">Friday</option>
                                        <option :value="6">Saturday</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] text-neutral-500 mb-1">Time</label>
                                    <input
                                        v-model="settingForm.weekly_digest_time"
                                        type="time"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Default Task Reminder Preset</label>
                            <select
                                v-model="settingForm.default_task_reminder_preset"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="none">None</option>
                                <option value="at_due">At due time (default 09:00)</option>
                                <option value="15m">15 minutes before</option>
                                <option value="30m">30 minutes before</option>
                                <option value="1h">1 hour before</option>
                                <option value="1d">1 day before</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Default Project Target Offsets</label>
                            <div class="flex flex-wrap gap-1.5 pt-0.5">
                                <button
                                    v-for="opt in projectReminderOptions"
                                    :key="opt.days"
                                    type="button"
                                    @click="toggleDefaultProjectDay(opt.days)"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-medium border transition"
                                    :class="
                                        settingForm.project_reminder_days.includes(opt.days)
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
                                    "
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. Focus / Pomodoro Timer Defaults -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        <Timer class="w-4 h-4 text-indigo-500" />
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                            Focus / Pomodoro Timer Defaults
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Focus Session (min)</label>
                            <input
                                v-model.number="settingForm.focus_work_minutes"
                                type="number"
                                min="1"
                                max="180"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Short Break (min)</label>
                            <input
                                v-model.number="settingForm.focus_short_break_minutes"
                                type="number"
                                min="1"
                                max="60"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Long Break (min)</label>
                            <input
                                v-model.number="settingForm.focus_long_break_minutes"
                                type="number"
                                min="1"
                                max="90"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="settingForm.focus_auto_start_breaks" class="rounded text-indigo-600" />
                        <span>Automatically start a short break when a focus block finishes</span>
                    </label>
                </div>

                <!-- 7. Automated Local Backups & Calendar Subscription -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2 border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        <Database class="w-4 h-4 text-indigo-500" />
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                            Automated SQLite Backups & Calendar Sync
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                        <label class="inline-flex items-center gap-2 cursor-pointer font-medium">
                            <input type="checkbox" v-model="settingForm.backup_enabled" class="rounded text-indigo-600" />
                            <span>Run daily verified SQLite backups automatically</span>
                        </label>
                        <div>
                            <label class="block font-medium mb-1">Backup Retention Window (days)</label>
                            <input
                                v-model.number="settingForm.backup_retention_days"
                                type="number"
                                min="1"
                                max="365"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <!-- Private .ics Feed Token -->
                    <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-neutral-900 dark:text-white inline-flex items-center gap-1.5">
                                <Link2 class="w-3.5 h-3.5 text-indigo-500" />
                                Private Local Calendar Feed (.ics)
                            </span>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="generateCalendarToken"
                                    class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                                >
                                    <RefreshCw class="w-3 h-3" />
                                    <span>{{ calendarFeedUrl ? 'Regenerate' : 'Generate Link' }}</span>
                                </button>
                                <button
                                    v-if="calendarFeedUrl"
                                    type="button"
                                    @click="revokeCalendarToken"
                                    class="text-rose-600 dark:text-rose-400 hover:underline font-medium"
                                >
                                    Revoke
                                </button>
                            </div>
                        </div>

                        <div v-if="calendarFeedUrl" class="flex gap-2">
                            <input
                                type="text"
                                readonly
                                :value="calendarFeedUrl"
                                class="flex-1 text-xs font-mono rounded-lg border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 dark:text-white"
                            />
                            <button
                                type="button"
                                @click="copyCalendarFeed"
                                class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium inline-flex items-center gap-1"
                            >
                                <Check v-if="copiedFeed" class="w-3.5 h-3.5" />
                                <Copy v-else class="w-3.5 h-3.5" />
                                <span>{{ copiedFeed ? 'Copied' : 'Copy' }}</span>
                            </button>
                        </div>
                        <p v-else class="text-[11px] text-neutral-400">
                            Generate a private token URL to subscribe from Outlook, Thunderbird, or Windows Calendar.
                        </p>
                    </div>
                </div>

                <!-- 8. Weekly Review Prompts Customization -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        Weekly Review Reflection Prompts
                    </h3>
                    <p class="text-neutral-400 text-[11px]">
                        Tailor the questions that guide your end-of-week review
                    </p>

                    <div class="space-y-2">
                        <div
                            v-for="(prompt, idx) in settingForm.weekly_review_prompts"
                            :key="idx"
                            class="flex items-center gap-2"
                        >
                            <span class="w-6 text-neutral-400 font-mono">{{ idx + 1 }}.</span>
                            <input
                                v-model="settingForm.weekly_review_prompts[idx]"
                                type="text"
                                class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                            <button
                                type="button"
                                @click="removePrompt(idx)"
                                class="p-1.5 text-neutral-400 hover:text-rose-500 rounded"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <input
                            v-model="newPromptText"
                            @keydown.enter.prevent="addPrompt"
                            type="text"
                            placeholder="Add a new custom reflection question..."
                            class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                        />
                        <button
                            type="button"
                            @click="addPrompt"
                            class="px-3 py-1.5 bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 rounded-lg text-neutral-700 dark:text-neutral-300 font-medium"
                        >
                            Add Question
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4 border-t border-neutral-100 dark:border-neutral-800">
                    <button
                        type="submit"
                        :disabled="settingForm.processing"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-sm transition disabled:opacity-50"
                    >
                        <Save class="w-4 h-4" />
                        <span>Save Preferences</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
