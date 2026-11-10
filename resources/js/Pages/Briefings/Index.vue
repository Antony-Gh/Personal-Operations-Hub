<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatTime12Hour } from '@/utils/date';
import { useFocusTimer } from '@/composables/useFocusTimer';
import {
    Sun,
    Calendar,
    AlertTriangle,
    CheckCircle2,
    Sparkles,
    ShieldAlert,
    ShieldCheck,
    FolderKanban,
    Timer,
    Bell,
    Trophy,
    ArrowRight,
} from 'lucide-vue-next';

const props = defineProps<{
    initialTab: 'daily' | 'weekly';
    dailyBriefing: {
        date: string;
        formatted_date: string;
        timezone: string;
        due_today_tasks: any[];
        priorities: any[];
        overdue_tasks: any[];
        upcoming_deadlines: any[];
        projects: any[];
        habits: {
            completed_count: number;
            total_count: number;
            items: any[];
        };
        bad_habits?: {
            clean_count: number;
            total_count: number;
            items: any[];
        };
        calendar_events: any[];
        reminders: any[];
    };
    weeklyDigest: {
        week_start: string;
        week_end: string;
        formatted_range: string;
        completed_tasks: any[];
        incomplete_tasks: any[];
        upcoming_deadlines: any[];
        focus_summary: {
            total_minutes: number;
            completed_sessions: number;
        };
        habit_summary: {
            average_consistency: number;
            items: any[];
        };
        bad_habit_summary?: {
            average_clean_rate: number;
            items: any[];
        };
        projects: any[];
        achievements: string[];
    };
}>();

const activeTab = ref<'daily' | 'weekly'>(props.initialTab || 'daily');
const { startFocusSession } = useFocusTimer();

const sendBriefingNotification = (type: 'daily' | 'weekly') => {
    router.post(
        route(type === 'daily' ? 'briefings.send-daily' : 'briefings.send-weekly'),
        {},
        { preserveScroll: true }
    );
};

const toggleTask = (taskId: number) => {
    router.post(route('tasks.toggle', taskId), {}, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout title="Daily Briefing & Weekly Digest">
        <Head title="Briefings" />

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header & Tab Switcher -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-sm">
                <div>
                    <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 text-xs font-semibold uppercase tracking-wider">
                        <Sun class="w-4 h-4" />
                        <span>Operational Intelligence • {{ dailyBriefing.timezone }}</span>
                    </div>
                    <h2 class="text-xl font-bold text-neutral-900 dark:text-white mt-1">
                        {{ activeTab === 'daily' ? dailyBriefing.formatted_date : `Weekly Digest (${weeklyDigest.formatted_range})` }}
                    </h2>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex p-1 rounded-xl bg-neutral-100 dark:bg-neutral-800">
                        <button
                            type="button"
                            @click="activeTab = 'daily'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
                            :class="
                                activeTab === 'daily'
                                    ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                    : 'text-neutral-600 dark:text-neutral-400'
                            "
                        >
                            Daily Briefing
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'weekly'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
                            :class="
                                activeTab === 'weekly'
                                    ? 'bg-white dark:bg-neutral-900 text-indigo-600 dark:text-indigo-400 shadow-sm'
                                    : 'text-neutral-600 dark:text-neutral-400'
                            "
                        >
                            Weekly Digest
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="sendBriefingNotification(activeTab)"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition shadow-sm"
                    >
                        <Bell class="w-3.5 h-3.5" />
                        Send Snapshot Alert
                    </button>
                </div>
            </div>

            <!-- DAILY BRIEFING TAB -->
            <div v-if="activeTab === 'daily'" class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-neutral-500">Due Today</p>
                        <p class="text-2xl font-bold text-neutral-900 dark:text-white mt-1">
                            {{ dailyBriefing.due_today_tasks.length }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-rose-600 dark:text-rose-400 font-medium">Overdue</p>
                        <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                            {{ dailyBriefing.overdue_tasks.length }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-neutral-500">Habits Checked</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                            {{ dailyBriefing.habits.completed_count }} / {{ dailyBriefing.habits.total_count }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-neutral-500">Scheduled Reminders</p>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                            {{ dailyBriefing.reminders.length }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Priorities & Due Today -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                                    Top Priorities & Tasks Due Today
                                </h3>
                                <Link :href="route('my-day.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1">
                                    Open My Day <ArrowRight class="w-3 h-3" />
                                </Link>
                            </div>

                            <div v-if="dailyBriefing.due_today_tasks.length === 0 && dailyBriefing.priorities.length === 0" class="py-6 text-center text-xs text-neutral-400">
                                No tasks scheduled for today yet.
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="task in (dailyBriefing.due_today_tasks.length ? dailyBriefing.due_today_tasks : dailyBriefing.priorities)"
                                    :key="task.id"
                                    class="flex items-center justify-between gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-100 dark:border-neutral-800"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <button
                                            type="button"
                                            @click="toggleTask(task.id)"
                                            class="w-4 h-4 rounded border flex items-center justify-center shrink-0"
                                            :class="
                                                task.status === 'completed'
                                                    ? 'bg-emerald-500 border-emerald-500 text-white'
                                                    : 'border-neutral-300 dark:border-neutral-600'
                                            "
                                        >
                                            <CheckCircle2 v-if="task.status === 'completed'" class="w-3 h-3" />
                                        </button>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-neutral-900 dark:text-white truncate">
                                                {{ task.title }}
                                            </p>
                                            <p class="text-[11px] text-neutral-500">
                                                <span class="uppercase font-semibold">{{ task.priority }}</span>
                                                <template v-if="task.due_time"> • {{ formatTime12Hour(task.due_time) }}</template>
                                                <template v-if="task.project"> • {{ task.project.name }}</template>
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        v-if="task.status !== 'completed'"
                                        type="button"
                                        @click="startFocusSession({ mode: 'focus', task_id: task.id, planned_minutes: task.estimated_duration || 25 })"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 text-[11px] font-medium hover:bg-indigo-100 transition shrink-0"
                                    >
                                        <Timer class="w-3 h-3" /> Focus
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Overdue Alert Block -->
                        <div
                            v-if="dailyBriefing.overdue_tasks.length > 0"
                            class="bg-rose-50/50 dark:bg-rose-950/20 rounded-2xl border border-rose-200 dark:border-rose-900/60 p-5"
                        >
                            <div class="flex items-center gap-2 text-rose-700 dark:text-rose-300 font-semibold text-sm mb-3">
                                <AlertTriangle class="w-4 h-4" />
                                <span>Overdue Attention Required ({{ dailyBriefing.overdue_tasks.length }})</span>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="task in dailyBriefing.overdue_tasks.slice(0, 8)"
                                    :key="task.id"
                                    class="flex items-center justify-between p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-rose-100 dark:border-rose-900/40 text-xs"
                                >
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100 truncate">{{ task.title }}</span>
                                    <span class="text-rose-600 dark:text-rose-400 font-mono text-[11px] shrink-0 ml-2">
                                        Due {{ task.due_date }}<template v-if="task.due_time"> {{ formatTime12Hour(task.due_time) }}</template>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Events, Habits, Active Projects -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white flex items-center gap-2 mb-3">
                                <Calendar class="w-4 h-4 text-indigo-500" /> Today's Commitments
                            </h3>
                            <div v-if="dailyBriefing.calendar_events.length === 0" class="text-xs text-neutral-400 py-3">
                                No fixed calendar events today.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="ev in dailyBriefing.calendar_events"
                                    :key="ev.id"
                                    class="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 text-xs"
                                >
                                    <p class="font-semibold text-neutral-900 dark:text-white">{{ ev.title }}</p>
                                    <p class="text-[11px] text-neutral-500 mt-0.5">
                                        {{ ev.all_day ? 'All Day' : new Date(ev.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white flex items-center gap-2">
                                    <Sparkles class="w-4 h-4 text-emerald-500" /> Daily Habits
                                </h3>
                                <Link :href="route('habits.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Check in
                                </Link>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="h in dailyBriefing.habits.items"
                                    :key="h.id"
                                    class="flex items-center justify-between text-xs py-1.5 border-b last:border-0 border-neutral-100 dark:border-neutral-800"
                                >
                                    <span class="text-neutral-800 dark:text-neutral-200">{{ h.name }}</span>
                                    <span class="text-[11px] font-semibold" :class="h.status === 'completed' ? 'text-emerald-600' : 'text-neutral-400'">
                                        🔥 {{ h.current_streak }}d • {{ h.status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="dailyBriefing.bad_habits && dailyBriefing.bad_habits.total_count > 0"
                            class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white flex items-center gap-2">
                                    <ShieldAlert class="w-4 h-4 text-rose-500" /> Habits to Break ({{ dailyBriefing.bad_habits.clean_count }}/{{ dailyBriefing.bad_habits.total_count }} clean)
                                </h3>
                                <Link :href="route('bad-habits.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Open
                                </Link>
                            </div>
                            <div class="space-y-2.5">
                                <div
                                    v-for="bh in dailyBriefing.bad_habits.items"
                                    :key="bh.id"
                                    class="text-xs py-1.5 border-b last:border-0 border-neutral-100 dark:border-neutral-800"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-neutral-800 dark:text-neutral-200">{{ bh.name }}</span>
                                        <span
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold"
                                            :class="bh.status === 'clean' ? 'text-emerald-600' : bh.status === 'slipped' ? 'text-rose-500' : 'text-neutral-400'"
                                        >
                                            <ShieldCheck class="w-3 h-3" /> {{ bh.current_clean_streak }}d • {{ bh.status }}
                                        </span>
                                    </div>
                                    <p v-if="bh.replacement_action" class="text-[11px] text-neutral-400 mt-0.5">
                                        Instead: {{ bh.replacement_action }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WEEKLY DIGEST TAB -->
            <div v-else class="space-y-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-neutral-500">Tasks Completed</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                            {{ weeklyDigest.completed_tasks.length }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-neutral-500">Deep Focus Logged</p>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                            {{ weeklyDigest.focus_summary.total_minutes }}m
                        </p>
                    </div>
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-neutral-500">Habit Consistency</p>
                        <p class="text-2xl font-bold text-neutral-900 dark:text-white mt-1">
                            {{ weeklyDigest.habit_summary.average_consistency }}%
                        </p>
                    </div>
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4">
                        <p class="text-xs text-neutral-500">Upcoming Deadlines</p>
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                            {{ weeklyDigest.upcoming_deadlines.length }}
                        </p>
                    </div>
                </div>

                <!-- Key Achievements -->
                <div class="bg-gradient-to-r from-indigo-600 to-violet-600 rounded-2xl p-5 text-white shadow-sm">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-indigo-100 mb-2">
                        <Trophy class="w-4 h-4" /> Weekly Highlights & Achievements
                    </div>
                    <ul class="space-y-1.5 text-sm font-medium">
                        <li v-for="(item, idx) in weeklyDigest.achievements" :key="idx" class="flex items-center gap-2">
                            <CheckCircle2 class="w-4 h-4 text-emerald-300 shrink-0" />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Active Project Progress -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-white flex items-center gap-2 mb-4">
                            <FolderKanban class="w-4 h-4 text-indigo-500" /> Project Progress Summary
                        </h3>
                        <div v-if="weeklyDigest.projects.length === 0" class="text-xs text-neutral-400 py-4">
                            No active projects.
                        </div>
                        <div class="space-y-3">
                            <Link
                                v-for="p in weeklyDigest.projects"
                                :key="p.id"
                                :href="route('projects.show', p.id)"
                                class="block p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            >
                                <div class="flex items-center justify-between text-xs font-semibold text-neutral-900 dark:text-white mb-1.5">
                                    <span>{{ p.name }}</span>
                                    <span>{{ p.progress_percent }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                                    <div class="h-full bg-indigo-600 rounded-full" :style="{ width: `${p.progress_percent}%` }"></div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Incomplete Carry-over & Upcoming -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                                Upcoming 7-Day Deadlines
                            </h3>
                            <Link :href="route('weekly-review.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                Open Weekly Review
                            </Link>
                        </div>
                        <div v-if="weeklyDigest.upcoming_deadlines.length === 0" class="text-xs text-neutral-400 py-4">
                            No upcoming deadlines in the next 7 days.
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="t in weeklyDigest.upcoming_deadlines"
                                :key="t.id"
                                class="flex items-center justify-between p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 text-xs"
                            >
                                <span class="font-medium text-neutral-900 dark:text-neutral-100 truncate">{{ t.title }}</span>
                                <span class="text-neutral-500 font-mono text-[11px] ml-2 shrink-0">{{ t.due_date }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly Clean Streaks Summary -->
                    <div
                        v-if="weeklyDigest.bad_habit_summary && weeklyDigest.bad_habit_summary.items.length > 0"
                        class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 lg:col-span-2"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white flex items-center gap-2">
                                <ShieldCheck class="w-4 h-4 text-emerald-500" /> Bad Habits Weekly Clean Rate ({{ weeklyDigest.bad_habit_summary.average_clean_rate }}%)
                            </h3>
                            <Link :href="route('bad-habits.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                View Bad Habits
                            </Link>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="bh in weeklyDigest.bad_habit_summary.items"
                                :key="bh.id"
                                class="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 text-xs flex items-center justify-between"
                            >
                                <div>
                                    <p class="font-semibold text-neutral-900 dark:text-white">{{ bh.name }}</p>
                                    <p class="text-[11px] text-neutral-400">Best: {{ bh.best_clean_streak }}d</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-emerald-600 dark:text-emerald-400">{{ bh.current_clean_streak }}d clean</p>
                                    <p class="text-[11px] text-neutral-500">{{ bh.weekly_clean_rate }}% wk</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

