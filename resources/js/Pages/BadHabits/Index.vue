<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HabitHeatmap from '@/Components/HabitHeatmap.vue';
import { BadHabit, HabitHeatmap as HabitHeatmapType } from '@/types';
import {
    ShieldAlert,
    ShieldCheck,
    Plus,
    Minus,
    Check,
    Archive,
    RotateCcw,
    Edit3,
    Flame,
    Trophy,
    TrendingUp,
    ArrowRight,
    AlertTriangle,
    MessageSquareText,
    Gauge,
    Clock,
    DollarSign,
    Link2,
    Sparkles,
    BarChart3,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    badHabits: BadHabit[];
    availableHabits?: {
        id: number;
        name: string;
        category: string;
    }[];
    archivedBadHabits?: {
        id: number;
        name: string;
        category: string;
        replacement_action: string | null;
    }[];
    summary?: {
        top_clean_streak: number;
        all_time_best_clean_streak: number;
        average_clean_percentage: number;
        average_weekly_clean_rate: number;
        stayed_clean_today_count: number;
        slipped_today_count: number;
        total_active_bad_habits: number;
        total_minutes_reclaimed?: number;
        total_hours_reclaimed?: number;
        total_money_reclaimed?: number;
    };
    triggerAnalytics?: {
        total_logged_slips: number;
        top_triggers: { trigger: string; count: number; share_percent: number }[];
        day_of_week_slips: { day: string; count: number; share_percent: number }[];
        peak_day?: { day: string; count: number; share_percent: number } | null;
        headline_insight: string;
    };
    heatmap?: HabitHeatmapType;
    todayDate: string;
    pastDates: string[];
}>();

const isModalOpen = ref(false);
const editingId = ref<number | null>(null);
const showArchived = ref(false);
const expandedReflections = ref<Record<number, boolean>>({});

const badHabitForm = useForm({
    name: '',
    description: '',
    category: 'Digital Discipline',
    trigger_cue: '',
    replacement_action: '',
    linked_habit_id: '' as string | number,
    minutes_saved_per_day: 0,
    money_saved_per_day: 0,
    has_daily_limit: false,
    daily_limit_value: 2 as number | null,
    daily_limit_unit: 'times' as 'times' | 'minutes',
    target_clean_days_per_week: 7,
});

const isSlipModalOpen = ref(false);
const slipTargetHabit = ref<BadHabit | null>(null);
const slipForm = useForm({
    date: props.todayDate,
    status: 'slipped' as 'clean' | 'minor_slip' | 'slipped',
    observed_value: null as number | null,
    trigger_note: '',
    notes: '',
});

const commonTriggers = [
    'Late-Night Fatigue',
    'Stress / Overload',
    'Boredom',
    'Phone Within Reach',
    'Procrastinating Hard Task',
    'Social / Environment',
];

const openCreateModal = () => {
    editingId.value = null;
    badHabitForm.reset();
    badHabitForm.clearErrors();
    badHabitForm.category = 'Digital Discipline';
    badHabitForm.linked_habit_id = '';
    badHabitForm.minutes_saved_per_day = 0;
    badHabitForm.money_saved_per_day = 0;
    badHabitForm.has_daily_limit = false;
    badHabitForm.daily_limit_value = 2;
    badHabitForm.daily_limit_unit = 'times';
    badHabitForm.target_clean_days_per_week = 7;
    isModalOpen.value = true;
};

const openEditModal = (bh: BadHabit) => {
    editingId.value = bh.id;
    badHabitForm.clearErrors();
    badHabitForm.name = bh.name;
    badHabitForm.description = bh.description || '';
    badHabitForm.category = bh.category || 'Digital Discipline';
    badHabitForm.trigger_cue = bh.trigger_cue || '';
    badHabitForm.replacement_action = bh.replacement_action || '';
    badHabitForm.linked_habit_id = bh.linked_habit_id || '';
    badHabitForm.minutes_saved_per_day = bh.minutes_saved_per_day ?? 0;
    badHabitForm.money_saved_per_day = bh.money_saved_per_day ?? 0;
    badHabitForm.has_daily_limit = bh.has_daily_limit;
    badHabitForm.daily_limit_value = bh.daily_limit_value ?? 2;
    badHabitForm.daily_limit_unit = bh.daily_limit_unit || 'times';
    badHabitForm.target_clean_days_per_week = bh.target_clean_days_per_week || 7;
    isModalOpen.value = true;
};

const submitBadHabit = () => {
    if (editingId.value !== null) {
        badHabitForm.put(route('bad-habits.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
                editingId.value = null;
            },
        });
    } else {
        badHabitForm.post(route('bad-habits.store'), {
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
                badHabitForm.reset();
            },
        });
    }
};

const markClean = (badHabitId: number, date: string, logReplacement = false) => {
    router.post(
        route('bad-habits.check-in', badHabitId),
        { date, status: 'clean', log_replacement: logReplacement },
        { preserveScroll: true }
    );
};

const openSlipReflection = (
    bh: BadHabit,
    status: 'minor_slip' | 'slipped',
    date: string = props.todayDate,
    observedOverride: number | null = null
) => {
    slipTargetHabit.value = bh;
    slipForm.date = date;
    slipForm.status = status;
    slipForm.observed_value =
        observedOverride !== null
            ? observedOverride
            : date === props.todayDate
              ? (bh.today_observed_value ?? null)
              : null;
    slipForm.trigger_note =
        date === props.todayDate && bh.today_trigger_note
            ? bh.today_trigger_note
            : bh.trigger_cue || '';
    slipForm.notes = date === props.todayDate ? bh.today_notes || '' : '';
    isSlipModalOpen.value = true;
};

const submitSlipReflection = () => {
    if (!slipTargetHabit.value) {
        return;
    }
    slipForm.post(route('bad-habits.check-in', slipTargetHabit.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSlipModalOpen.value = false;
            slipTargetHabit.value = null;
        },
    });
};

const adjustDailyLimitCount = (bh: BadHabit, delta: number) => {
    const current = bh.today_observed_value ?? 0;
    const step = bh.daily_limit_unit === 'minutes' ? delta * 5 : delta;
    const nextVal = Math.max(0, current + step);
    const limit = bh.daily_limit_value ?? 0;

    if (nextVal > limit && current <= limit) {
        openSlipReflection(bh, 'slipped', props.todayDate, nextVal);
        return;
    }

    router.post(
        route('bad-habits.check-in', bh.id),
        {
            date: props.todayDate,
            observed_value: nextVal,
            status: nextVal <= limit ? 'clean' : 'slipped',
        },
        { preserveScroll: true }
    );
};

const cycleHistoryDay = (bh: BadHabit, date: string, currentStatus: string) => {
    if (currentStatus === 'clean') {
        openSlipReflection(bh, 'minor_slip', date);
    } else if (currentStatus === 'minor_slip') {
        openSlipReflection(bh, 'slipped', date);
    } else {
        markClean(bh.id, date);
    }
};

const archiveBadHabit = (id: number) => {
    if (confirm('Archive this bad habit? You can restore it anytime from Archived items.')) {
        router.delete(route('bad-habits.destroy', id), { preserveScroll: true });
    }
};

const restoreBadHabit = (id: number) => {
    router.post(route('bad-habits.restore', id), {}, { preserveScroll: true });
};

const toggleReflections = (id: number) => {
    expandedReflections.value[id] = !expandedReflections.value[id];
};
</script>

<template>
    <AuthenticatedLayout title="Bad Habits & Recovery">
        <Head title="Bad Habits & Recovery" />

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                        <ShieldAlert class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Bad Habits & Recovery</h2>
                        <p class="text-xs text-neutral-400">
                            Break unhelpful patterns with trigger awareness, healthy replacements, daily caps, and clean-day streaks
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="archivedBadHabits && archivedBadHabits.length > 0"
                        type="button"
                        @click="showArchived = !showArchived"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                    >
                        <Archive class="w-3.5 h-3.5" />
                        <span>Archived ({{ archivedBadHabits.length }})</span>
                    </button>
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add Bad Habit</span>
                    </button>
                </div>
            </div>

            <!-- Analytics Summary Cards -->
            <div v-if="summary" class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Top Clean Streak</span>
                        <Flame class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.top_clean_streak }} <span class="text-xs font-normal text-neutral-400">days</span>
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">Consecutive clean days</p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Best Clean Streak</span>
                        <Trophy class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.all_time_best_clean_streak }} <span class="text-xs font-normal text-neutral-400">days</span>
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">Personal clean record</p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Stayed Clean Today</span>
                        <ShieldCheck class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.stayed_clean_today_count }}
                        <span class="text-xs font-normal text-neutral-400">/ {{ summary.total_active_bad_habits }}</span>
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">Checked in clean today</p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Overall Clean Rate</span>
                        <TrendingUp class="w-4 h-4 text-indigo-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.average_clean_percentage }}%
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">
                        {{ summary.average_weekly_clean_rate }}% clean this week
                    </p>
                </div>

                <div class="col-span-2 lg:col-span-1 bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Reclaimed Life</span>
                        <Clock class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ summary.total_hours_reclaimed ?? 0 }}h
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">
                        ${{ (summary.total_money_reclaimed ?? 0).toFixed(2) }} saved on clean days
                    </p>
                </div>
            </div>

            <!-- Trigger & Day-of-Week Slip Pattern Intelligence -->
            <div
                v-if="triggerAnalytics && badHabits.length > 0"
                class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <BarChart3 class="w-4 h-4 text-indigo-500" />
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                            Trigger & Slip Pattern Intelligence
                        </h3>
                    </div>
                    <span class="text-[11px] font-medium px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                        {{ triggerAnalytics.headline_insight }}
                    </span>
                </div>

                <div v-if="triggerAnalytics.total_logged_slips > 0" class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-1">
                    <!-- Top Triggers -->
                    <div class="space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                            Most Common Triggers ({{ triggerAnalytics.total_logged_slips }} logged slips)
                        </p>
                        <div v-if="triggerAnalytics.top_triggers.length === 0" class="text-xs text-neutral-400">
                            Add trigger tags when logging slips to uncover root causes.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="t in triggerAnalytics.top_triggers"
                                :key="t.trigger"
                                class="space-y-1 text-xs"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-neutral-700 dark:text-neutral-200 truncate">{{ t.trigger }}</span>
                                    <span class="text-[11px] font-mono text-neutral-500">{{ t.count }} ({{ t.share_percent }}%)</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-amber-500"
                                        :style="{ width: `${Math.max(6, t.share_percent)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day-of-Week Distribution -->
                    <div class="space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                            Day-of-Week Vulnerability
                        </p>
                        <div class="grid grid-cols-7 gap-2 items-end h-24 pt-2">
                            <div
                                v-for="d in triggerAnalytics.day_of_week_slips"
                                :key="d.day"
                                class="flex flex-col items-center justify-end h-full gap-1"
                            >
                                <span class="text-[10px] font-mono text-neutral-400">{{ d.count }}</span>
                                <div class="w-full bg-neutral-100 dark:bg-neutral-800 rounded-md h-14 flex items-end overflow-hidden">
                                    <div
                                        class="w-full rounded-md transition-all"
                                        :class="d.count > 0 ? 'bg-rose-500/80' : 'bg-transparent'"
                                        :style="{ height: `${d.count > 0 ? Math.max(15, d.share_percent) : 0}%` }"
                                    ></div>
                                </div>
                                <span class="text-[10px] font-medium text-neutral-500">{{ d.day }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Clean-Days Heatmap -->
            <HabitHeatmap
                v-if="heatmap && badHabits.length > 0"
                :heatmap="heatmap"
                route-name="bad-habits.index"
                title="Clean-Days Heatmap"
                subtitle="Daily clean discipline intensity across habits you are breaking"
                unit-label="clean"
            />

            <!-- Empty State -->
            <div
                v-if="badHabits.length === 0"
                class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-12 text-center space-y-3"
            >
                <ShieldCheck class="w-10 h-10 mx-auto text-emerald-500/70" />
                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">No bad habits tracked yet</h3>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 max-w-md mx-auto">
                    Add habits you want to reduce or eliminate—like late-night doomscrolling, impulsive snacking, or task avoidance—complete with trigger cues, healthy replacements, and optional daily caps.
                </p>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                >
                    <Plus class="w-4 h-4" />
                    <span>Track First Bad Habit</span>
                </button>
            </div>

            <!-- Active Bad Habits List -->
            <div v-else class="space-y-4">
                <div
                    v-for="bh in badHabits"
                    :key="bh.id"
                    class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4"
                >
                    <!-- Top Row: Title, Badges, Actions -->
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                                    {{ bh.name }}
                                </h3>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 font-medium">
                                    {{ bh.category }}
                                </span>
                                <span
                                    v-if="(bh.current_clean_streak || 0) > 0"
                                    class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-semibold"
                                    title="Stayed Clean adds +1 day; Minor Slip holds streak; Slipped resets current streak"
                                >
                                    <ShieldCheck class="w-3 h-3" /> {{ bh.current_clean_streak }}d clean streak
                                </span>
                                <span class="text-[11px] text-neutral-400">
                                    Best: {{ bh.best_clean_streak || 0 }}d • Total clean: {{ bh.total_clean_days || 0 }}d
                                </span>
                                <span
                                    v-if="(bh.minutes_reclaimed || 0) > 0"
                                    class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-medium"
                                >
                                    <Clock class="w-3 h-3" />
                                    +{{ Math.round(((bh.minutes_reclaimed || 0) / 60) * 10) / 10 }}h reclaimed
                                </span>
                                <span
                                    v-if="(bh.money_reclaimed || 0) > 0"
                                    class="inline-flex items-center gap-0.5 text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium"
                                >
                                    <DollarSign class="w-3 h-3" />
                                    ${{ (bh.money_reclaimed || 0).toFixed(2) }} saved
                                </span>
                                <span
                                    v-if="bh.linked_habit"
                                    class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-medium"
                                    :title="`Linked replacement Good Habit (${bh.linked_habit.today_status})`"
                                >
                                    <Link2 class="w-3 h-3" />
                                    Instead: {{ bh.linked_habit.name }}
                                </span>
                            </div>
                            <p v-if="bh.description" class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ bh.description }}
                            </p>
                        </div>

                        <!-- Today Check-In Controls -->
                        <div class="flex items-center gap-2 flex-wrap shrink-0">
                            <button
                                v-if="bh.linked_habit"
                                type="button"
                                @click="markClean(bh.id, todayDate, true)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-medium transition border"
                                :class="
                                    bh.today_status === 'clean' && bh.linked_habit.today_status === 'completed'
                                        ? 'bg-purple-600 border-purple-600 text-white shadow-xs'
                                        : 'bg-purple-50 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/50'
                                "
                                :title="`Stay Clean AND mark '${bh.linked_habit.name}' completed today`"
                            >
                                <Sparkles class="w-3.5 h-3.5" />
                                <span>Clean + Log Replacement</span>
                            </button>

                            <button
                                type="button"
                                @click="markClean(bh.id, todayDate, false)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-medium transition"
                                :class="
                                    bh.today_status === 'clean'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950/50 dark:hover:text-emerald-300'
                                "
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>Stayed Clean</span>
                            </button>

                            <button
                                type="button"
                                @click="openSlipReflection(bh, 'minor_slip', todayDate)"
                                class="px-3 py-1.5 rounded-xl text-xs font-medium transition"
                                :class="
                                    bh.today_status === 'minor_slip'
                                        ? 'bg-amber-500 text-white shadow-xs'
                                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950/50 dark:hover:text-amber-300'
                                "
                                title="Caught yourself early — holds streak without adding a day"
                            >
                                Minor Slip
                            </button>

                            <button
                                type="button"
                                @click="openSlipReflection(bh, 'slipped', todayDate)"
                                class="px-3 py-1.5 rounded-xl text-xs font-medium transition"
                                :class="
                                    bh.today_status === 'slipped'
                                        ? 'bg-rose-600 text-white shadow-xs'
                                        : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/50 dark:hover:text-rose-300'
                                "
                                title="Log a slip and reflect gently on what triggered it"
                            >
                                Slipped
                            </button>

                            <button
                                type="button"
                                @click="openEditModal(bh)"
                                class="p-1.5 rounded-lg text-neutral-400 hover:text-indigo-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                title="Edit bad habit"
                            >
                                <Edit3 class="w-4 h-4" />
                            </button>

                            <button
                                type="button"
                                @click="archiveBadHabit(bh.id)"
                                class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                title="Archive bad habit"
                            >
                                <Archive class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Trigger Cue -> Healthy Replacement Banner -->
                    <div
                        v-if="bh.trigger_cue || bh.replacement_action"
                        class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-100 dark:border-neutral-800 text-xs"
                    >
                        <div v-if="bh.trigger_cue" class="flex items-start gap-2">
                            <AlertTriangle class="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" />
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 block">
                                    Watch for Trigger
                                </span>
                                <span class="text-neutral-700 dark:text-neutral-200">{{ bh.trigger_cue }}</span>
                            </div>
                        </div>
                        <div v-if="bh.replacement_action" class="flex items-start gap-2">
                            <ArrowRight class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block">
                                    Healthy Replacement Instead
                                </span>
                                <span class="text-neutral-800 dark:text-neutral-100 font-medium">
                                    {{ bh.replacement_action }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Limit / Cap Mode Bar -->
                    <div
                        v-if="bh.has_daily_limit && bh.daily_limit_value !== null"
                        class="p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 bg-neutral-50/40 dark:bg-neutral-900/50 space-y-2"
                    >
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div class="flex items-center gap-2 text-xs">
                                <Gauge class="w-4 h-4 text-indigo-500" />
                                <span class="font-semibold text-neutral-800 dark:text-neutral-200">
                                    Daily Cap:
                                </span>
                                <span
                                    class="font-mono font-bold"
                                    :class="
                                        (bh.today_observed_value ?? 0) > bh.daily_limit_value
                                            ? 'text-rose-600 dark:text-rose-400'
                                            : 'text-emerald-600 dark:text-emerald-400'
                                    "
                                >
                                    {{ bh.today_observed_value ?? 0 }} / {{ bh.daily_limit_value }} {{ bh.daily_limit_unit }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    @click="adjustDailyLimitCount(bh, -1)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                >
                                    <Minus class="w-3 h-3" />
                                    <span>{{ bh.daily_limit_unit === 'minutes' ? '5m' : '1' }}</span>
                                </button>
                                <button
                                    type="button"
                                    @click="adjustDailyLimitCount(bh, 1)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 text-xs text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>{{ bh.daily_limit_unit === 'minutes' ? '5m' : '1' }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="w-full h-1.5 rounded-full bg-neutral-200 dark:bg-neutral-800 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="
                                    (bh.today_observed_value ?? 0) > bh.daily_limit_value
                                        ? 'bg-rose-500'
                                        : 'bg-emerald-500'
                                "
                                :style="{
                                    width: `${Math.min(100, bh.daily_limit_value > 0 ? ((bh.today_observed_value ?? 0) / bh.daily_limit_value) * 100 : 0)}%`,
                                }"
                            ></div>
                        </div>
                    </div>

                    <!-- 14-Day Clean History Matrix -->
                    <div class="pt-1">
                        <div class="flex items-center justify-between text-[11px] text-neutral-400 mb-2">
                            <span>Past 14 Days (click any day to update)</span>
                            <span>
                                Weekly clean rate: <strong class="text-neutral-700 dark:text-neutral-200">{{ bh.weekly_clean_rate ?? 0 }}%</strong>
                                • Target: {{ bh.target_clean_days_per_week }}/7 clean days
                            </span>
                        </div>

                        <div class="grid grid-cols-7 sm:grid-cols-14 gap-1.5">
                            <button
                                v-for="day in bh.history"
                                :key="day.date"
                                type="button"
                                @click="cycleHistoryDay(bh, day.date, day.status)"
                                :title="`${day.date}: ${day.status}${day.trigger_note ? ' — Trigger: ' + day.trigger_note : ''}${day.notes ? ' (' + day.notes + ')' : ''}`"
                                class="h-8 rounded-lg flex flex-col items-center justify-center text-[10px] font-semibold transition"
                                :class="[
                                    day.status === 'clean'
                                        ? 'bg-emerald-500 text-white'
                                        : day.status === 'minor_slip'
                                          ? 'bg-amber-400 text-amber-950'
                                          : day.status === 'slipped'
                                            ? 'bg-rose-500 text-white'
                                            : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700',
                                    day.date === todayDate ? 'ring-2 ring-indigo-500 ring-offset-1 dark:ring-offset-neutral-900' : '',
                                ]"
                            >
                                <span>{{ day.date.slice(8) }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Recent Slip Reflections -->
                    <div
                        v-if="bh.recent_reflections && bh.recent_reflections.length > 0"
                        class="border-t border-neutral-100 dark:border-neutral-800 pt-3"
                    >
                        <button
                            type="button"
                            @click="toggleReflections(bh.id)"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                        >
                            <MessageSquareText class="w-3.5 h-3.5" />
                            <span>
                                {{ expandedReflections[bh.id] ? 'Hide' : 'View' }} Trigger & Recovery Reflections ({{ bh.recent_reflections.length }})
                            </span>
                        </button>

                        <div v-if="expandedReflections[bh.id]" class="mt-2.5 space-y-2">
                            <div
                                v-for="(refItem, rIdx) in bh.recent_reflections"
                                :key="rIdx"
                                class="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 text-xs space-y-0.5"
                            >
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-semibold text-neutral-700 dark:text-neutral-200">
                                        {{ refItem.date }}
                                        <span
                                            v-if="refItem.trigger_note"
                                            class="ml-1.5 px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 text-[10px]"
                                        >
                                            {{ refItem.trigger_note }}
                                        </span>
                                    </span>
                                    <span
                                        class="uppercase text-[10px] font-bold"
                                        :class="refItem.status === 'slipped' ? 'text-rose-500' : 'text-amber-500'"
                                    >
                                        {{ refItem.status.replace('_', ' ') }}
                                    </span>
                                </div>
                                <p v-if="refItem.notes" class="text-neutral-600 dark:text-neutral-300">
                                    {{ refItem.notes }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Archived Bad Habits Panel -->
            <div
                v-if="showArchived && archivedBadHabits && archivedBadHabits.length > 0"
                class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 space-y-3"
            >
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-400">
                    Archived Bad Habits
                </h3>
                <div class="space-y-2">
                    <div
                        v-for="arch in archivedBadHabits"
                        :key="arch.id"
                        class="flex items-center justify-between p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 text-xs"
                    >
                        <div>
                            <p class="font-semibold text-neutral-800 dark:text-neutral-200">{{ arch.name }}</p>
                            <p class="text-[11px] text-neutral-400">{{ arch.category }}</p>
                        </div>
                        <button
                            type="button"
                            @click="restoreBadHabit(arch.id)"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 text-xs font-medium hover:bg-indigo-100 transition"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                            <span>Restore</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Bad Habit Modal -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
            @click.self="isModalOpen = false"
        >
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-lg p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-neutral-900 dark:text-white">
                        {{ editingId ? 'Edit Bad Habit' : 'Add Bad Habit to Break' }}
                    </h3>
                    <button
                        type="button"
                        @click="isModalOpen = false"
                        class="p-1 rounded-lg text-neutral-400 hover:text-neutral-600"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitBadHabit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                            Bad Habit Name *
                        </label>
                        <input
                            v-model="badHabitForm.name"
                            type="text"
                            required
                            placeholder="e.g., Late-Night Doomscrolling after 11 PM"
                            class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                        />
                        <p v-if="badHabitForm.errors.name" class="text-xs text-rose-500 mt-1">
                            {{ badHabitForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                Category
                            </label>
                            <input
                                v-model="badHabitForm.category"
                                type="text"
                                placeholder="Digital Discipline, Sleep, Focus..."
                                class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                Target Clean Days / Week
                            </label>
                            <input
                                v-model.number="badHabitForm.target_clean_days_per_week"
                                type="number"
                                min="1"
                                max="7"
                                required
                                class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                            Common Trigger Cue (What usually sparks it?)
                        </label>
                        <input
                            v-model="badHabitForm.trigger_cue"
                            type="text"
                            placeholder="e.g., Keeping phone on nightstand when tired"
                            class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                            Healthy Replacement Action (What will you do instead?)
                        </label>
                        <input
                            v-model="badHabitForm.replacement_action"
                            type="text"
                            placeholder="e.g., Dock phone across room and read 5 pages offline"
                            class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                        />
                    </div>

                    <div v-if="availableHabits && availableHabits.length > 0">
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                            Link to Existing Good Habit Replacement (Optional 1-Click Check-In)
                        </label>
                        <select
                            v-model="badHabitForm.linked_habit_id"
                            class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                        >
                            <option value="">No linked Good Habit</option>
                            <option v-for="h in availableHabits" :key="h.id" :value="h.id">
                                {{ h.name }} ({{ h.category }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                Est. Minutes Reclaimed per Clean Day
                            </label>
                            <input
                                v-model.number="badHabitForm.minutes_saved_per_day"
                                type="number"
                                min="0"
                                max="1440"
                                class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                Est. Money Saved per Clean Day ($)
                            </label>
                            <input
                                v-model.number="badHabitForm.money_saved_per_day"
                                type="number"
                                min="0"
                                step="0.5"
                                class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                            Why You Are Breaking This (Optional Motivation)
                        </label>
                        <textarea
                            v-model="badHabitForm.description"
                            rows="2"
                            placeholder="Protect morning clarity and deep sleep..."
                            class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                        ></textarea>
                    </div>

                    <!-- Daily Limit / Cap Mode -->
                    <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-3">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input
                                v-model="badHabitForm.has_daily_limit"
                                type="checkbox"
                                class="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                                Enable Daily Limit / Cap Mode (e.g., max N times or minutes before counting as a slip)
                            </span>
                        </label>

                        <div v-if="badHabitForm.has_daily_limit" class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="block text-[11px] font-medium text-neutral-500 mb-1">
                                    Daily Max Allowed
                                </label>
                                <input
                                    v-model.number="badHabitForm.daily_limit_value"
                                    type="number"
                                    min="0"
                                    max="10000"
                                    required
                                    class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-neutral-500 mb-1">
                                    Unit
                                </label>
                                <select
                                    v-model="badHabitForm.daily_limit_unit"
                                    class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white text-sm"
                                >
                                    <option value="times">Times / Occurrences</option>
                                    <option value="minutes">Minutes</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="badHabitForm.processing"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm disabled:opacity-50"
                        >
                            {{ editingId ? 'Save Changes' : 'Add Bad Habit' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Slip / Minor Slip Reflection Modal -->
        <div
            v-if="isSlipModalOpen && slipTargetHabit"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
            @click.self="isSlipModalOpen = false"
        >
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-md p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">
                            {{ slipForm.status === 'minor_slip' ? 'Log Minor Slip' : 'Log Slip & Reset Gently' }}
                        </h3>
                        <p class="text-xs text-neutral-400">
                            {{ slipTargetHabit.name }} • {{ slipForm.date }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="isSlipModalOpen = false"
                        class="p-1 rounded-lg text-neutral-400 hover:text-neutral-600"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div
                    v-if="slipTargetHabit.replacement_action"
                    class="p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200/70 dark:border-emerald-900/50 text-xs text-emerald-800 dark:text-emerald-300"
                >
                    <strong>Next-time replacement:</strong> {{ slipTargetHabit.replacement_action }}
                </div>

                <form @submit.prevent="submitSlipReflection" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">
                            What triggered it? (Optional)
                        </label>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <button
                                v-for="chip in commonTriggers"
                                :key="chip"
                                type="button"
                                @click="slipForm.trigger_note = chip"
                                class="px-2 py-1 rounded-lg text-[11px] font-medium border transition"
                                :class="
                                    slipForm.trigger_note === chip
                                        ? 'bg-amber-500 border-amber-500 text-white'
                                        : 'border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                                "
                            >
                                {{ chip }}
                            </button>
                        </div>
                        <input
                            v-model="slipForm.trigger_note"
                            type="text"
                            placeholder="Trigger or context..."
                            class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                            Reflection / Recovery Note (No guilt—just data)
                        </label>
                        <textarea
                            v-model="slipForm.notes"
                            rows="2"
                            placeholder="What happened, and what tiny adjustment will help tomorrow?"
                            class="w-full rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white text-sm"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="isSlipModalOpen = false"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="slipForm.processing"
                            class="px-4 py-2 rounded-xl text-white text-xs font-medium shadow-sm disabled:opacity-50"
                            :class="slipForm.status === 'minor_slip' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-rose-600 hover:bg-rose-700'"
                        >
                            Save Check-In
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

