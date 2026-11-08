<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    BarChart3,
    Target,
    Clock,
    TrendingUp,
    Smartphone,
    CheckCircle2,
    AlertTriangle,
    ArrowUpRight,
} from 'lucide-vue-next';

interface EvaluatedTaskRow {
    id: number;
    title: string;
    status: string;
    category: string | null;
    category_color: string | null;
    estimated_duration: number;
    actual_duration: number;
    delta_minutes: number;
    accuracy_percent: number;
    verdict: 'underestimated' | 'on_target' | 'overestimated';
}

interface DeepWorkCategoryRow {
    id: number | null;
    name: string;
    color: string;
    minutes: number;
    hours: number;
    sessions_count: number;
    share_percent: number;
}

interface WeeklyVelocityRow {
    week_start: string;
    label: string;
    completed_tasks: number;
    focus_minutes: number;
}

interface DailyCorrelationRow {
    date: string;
    label: string;
    phone_minutes: number;
    has_phone_log: boolean;
    focus_minutes: number;
    good_habit_rate: number;
    bad_habit_clean_rate: number;
}

const props = defineProps<{
    analytics: {
        range_days: number;
        estimation: {
            evaluated_tasks_count: number;
            total_estimated_minutes: number;
            total_actual_minutes: number;
            average_accuracy_percent: number;
            average_bias_minutes: number;
            underestimated_count: number;
            on_target_count: number;
            overestimated_count: number;
            tasks: EvaluatedTaskRow[];
        };
        deep_work: {
            total_minutes: number;
            total_hours: number;
            by_category: DeepWorkCategoryRow[];
        };
        weekly_velocity: WeeklyVelocityRow[];
        correlations: {
            phone_target_minutes: number;
            low_screen_avg_focus_minutes: number;
            high_screen_avg_focus_minutes: number;
            low_screen_avg_clean_rate: number;
            high_screen_avg_clean_rate: number;
            insight: string;
            daily_series: DailyCorrelationRow[];
        };
    };
}>();

const rangeOptions = [
    { days: 7, label: '7 Days' },
    { days: 14, label: '14 Days' },
    { days: 30, label: '30 Days' },
    { days: 90, label: '90 Days' },
];

const switchRange = (days: number) => {
    router.get(route('analytics.index'), { range: days }, { preserveState: true, replace: true });
};

const maxVelocityTasks = computed(() =>
    Math.max(1, ...props.analytics.weekly_velocity.map((w) => w.completed_tasks))
);

const maxVelocityFocus = computed(() =>
    Math.max(1, ...props.analytics.weekly_velocity.map((w) => w.focus_minutes))
);
</script>

<template>
    <AuthenticatedLayout title="Analytics & Insights">
        <Head title="Personal Analytics & Insights" />

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header & Range Switcher -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <BarChart3 class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Personal Analytics & Insights</h2>
                        <p class="text-xs text-neutral-400">
                            100% local insights into estimation calibration, deep-work allocation, weekly velocity, and screen-time correlations
                        </p>
                    </div>
                </div>

                <div class="flex items-center border border-neutral-200 dark:border-neutral-800 rounded-xl p-0.5 bg-neutral-100/60 dark:bg-neutral-800/60 self-start sm:self-auto">
                    <button
                        v-for="opt in rangeOptions"
                        :key="opt.days"
                        type="button"
                        @click="switchRange(opt.days)"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition"
                        :class="
                            analytics.range_days === opt.days
                                ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs'
                                : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400'
                        "
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- Top KPI Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Estimation Accuracy</span>
                        <Target class="w-4 h-4 text-indigo-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ analytics.estimation.average_accuracy_percent }}%
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">
                        Avg bias: {{ analytics.estimation.average_bias_minutes > 0 ? '+' : '' }}{{ analytics.estimation.average_bias_minutes }}m across {{ analytics.estimation.evaluated_tasks_count }} tasks
                    </p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Deep-Work Focus ({{ analytics.range_days }}d)</span>
                        <Clock class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ analytics.deep_work.total_hours }}h
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">
                        {{ analytics.deep_work.total_minutes }} focus minutes logged
                    </p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Focus Within Phone Cap</span>
                        <Smartphone class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ analytics.correlations.low_screen_avg_focus_minutes }}m
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">
                        vs {{ analytics.correlations.high_screen_avg_focus_minutes }}m when exceeding {{ analytics.correlations.phone_target_minutes }}m cap
                    </p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Calibration Split</span>
                        <TrendingUp class="w-4 h-4 text-purple-500" />
                    </div>
                    <div class="text-base font-bold text-neutral-900 dark:text-white pt-1">
                        <span class="text-emerald-600 dark:text-emerald-400">{{ analytics.estimation.on_target_count }} on-target</span>
                        <span class="text-neutral-400 mx-1">•</span>
                        <span class="text-amber-600 dark:text-amber-400">{{ analytics.estimation.underestimated_count }} under</span>
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">
                        {{ analytics.estimation.overestimated_count }} overestimated tasks
                    </p>
                </div>
            </div>

            <!-- Row 2: Deep Work by Area + 6-Week Execution Velocity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Deep-Work Hours by Category -->
                <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                                Deep-Work Hours by Area ({{ analytics.range_days }}d)
                            </h3>
                            <p class="text-[11px] text-neutral-400">
                                Where your focused Pomodoro and logged task minutes actually go
                            </p>
                        </div>
                    </div>

                    <div v-if="analytics.deep_work.by_category.length === 0" class="py-10 text-center text-xs text-neutral-400 border border-dashed border-neutral-200 dark:border-neutral-800 rounded-xl">
                        Complete Focus Timer blocks or log actual minutes on tasks to visualize deep-work distribution.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="cat in analytics.deep_work.by_category"
                            :key="cat.name"
                            class="space-y-1.5 text-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-2 font-medium text-neutral-800 dark:text-neutral-200">
                                    <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: cat.color }"></span>
                                    {{ cat.name }}
                                </span>
                                <span class="font-mono text-[11px] text-neutral-500">
                                    {{ cat.hours }}h ({{ cat.minutes }}m • {{ cat.share_percent }}%)
                                </span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :style="{
                                        width: `${Math.max(4, cat.share_percent)}%`,
                                        backgroundColor: cat.color,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6-Week Execution Velocity -->
                <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white">6-Week Execution Velocity</h3>
                        <p class="text-[11px] text-neutral-400">
                            Completed tasks (indigo) & logged focus minutes (emerald) week over week
                        </p>
                    </div>

                    <div class="grid grid-cols-6 gap-3 items-end h-44 pt-4">
                        <div
                            v-for="wk in analytics.weekly_velocity"
                            :key="wk.week_start"
                            class="flex flex-col items-center justify-end h-full gap-1.5"
                        >
                            <div class="text-[10px] font-mono text-neutral-500 text-center leading-tight">
                                <div>{{ wk.completed_tasks }}t</div>
                                <div class="text-emerald-600 dark:text-emerald-400">{{ wk.focus_minutes }}m</div>
                            </div>
                            <div class="w-full flex items-end justify-center gap-1 h-28 bg-neutral-50 dark:bg-neutral-800/50 rounded-lg p-1">
                                <div
                                    class="w-1/2 rounded bg-indigo-500 transition-all"
                                    :style="{ height: `${wk.completed_tasks > 0 ? Math.max(10, (wk.completed_tasks / maxVelocityTasks) * 100) : 0}%` }"
                                    :title="`${wk.completed_tasks} completed tasks`"
                                ></div>
                                <div
                                    class="w-1/2 rounded bg-emerald-500 transition-all"
                                    :style="{ height: `${wk.focus_minutes > 0 ? Math.max(10, (wk.focus_minutes / maxVelocityFocus) * 100) : 0}%` }"
                                    :title="`${wk.focus_minutes} focus minutes`"
                                ></div>
                            </div>
                            <span class="text-[10px] font-medium text-neutral-500">{{ wk.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Screen Time vs Focus & Habit Discipline Correlation -->
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                            14-Day Screen Time vs. Focus & Habit Discipline
                        </h3>
                        <p class="text-[11px] text-neutral-400">
                            How leisure phone usage compares against deep focus minutes and clean-habit adherence
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300">
                        <ArrowUpRight class="w-3.5 h-3.5 shrink-0" />
                        {{ analytics.correlations.insight }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-neutral-400 border-b border-neutral-100 dark:border-neutral-800">
                            <tr>
                                <th class="py-2 pr-3">Date</th>
                                <th class="py-2 px-3">Phone Usage</th>
                                <th class="py-2 px-3">Deep Focus</th>
                                <th class="py-2 px-3">Good Habits</th>
                                <th class="py-2 pl-3">Bad Habits Clean</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            <tr v-for="row in analytics.correlations.daily_series" :key="row.date" class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40">
                                <td class="py-2 pr-3 font-medium text-neutral-800 dark:text-neutral-200 whitespace-nowrap">
                                    {{ row.label }} <span class="text-[10px] font-mono text-neutral-400 ml-1">{{ row.date }}</span>
                                </td>
                                <td class="py-2 px-3 font-mono">
                                    <span
                                        v-if="row.has_phone_log"
                                        class="px-2 py-0.5 rounded text-[11px] font-semibold"
                                        :class="
                                            row.phone_minutes <= analytics.correlations.phone_target_minutes
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                                        "
                                    >
                                        {{ row.phone_minutes }}m
                                    </span>
                                    <span v-else class="text-neutral-400 text-[11px]">—</span>
                                </td>
                                <td class="py-2 px-3 font-mono text-indigo-600 dark:text-indigo-400 font-semibold">
                                    {{ row.focus_minutes }}m
                                </td>
                                <td class="py-2 px-3 font-mono text-neutral-700 dark:text-neutral-300">
                                    {{ row.good_habit_rate }}%
                                </td>
                                <td class="py-2 pl-3 font-mono text-emerald-600 dark:text-emerald-400">
                                    {{ row.bad_habit_clean_rate }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Row 4: Task Duration Estimation Calibration Table -->
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white">
                        Task Duration Calibration (Estimated vs. Actual Focus)
                    </h3>
                    <p class="text-[11px] text-neutral-400">
                        Compare your planned duration estimates against real logged minutes to sharpen daily capacity planning
                    </p>
                </div>

                <div v-if="analytics.estimation.tasks.length === 0" class="py-8 text-center text-xs text-neutral-400 border border-dashed border-neutral-200 dark:border-neutral-800 rounded-xl">
                    Tasks that have both an Estimated Duration and Logged Actual Duration will appear here automatically.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-neutral-400 border-b border-neutral-100 dark:border-neutral-800">
                            <tr>
                                <th class="py-2 pr-3">Task</th>
                                <th class="py-2 px-3">Area</th>
                                <th class="py-2 px-3">Estimated</th>
                                <th class="py-2 px-3">Actual</th>
                                <th class="py-2 px-3">Delta</th>
                                <th class="py-2 pl-3">Verdict</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            <tr v-for="t in analytics.estimation.tasks" :key="t.id" class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40">
                                <td class="py-2.5 pr-3 font-medium text-neutral-900 dark:text-white">
                                    {{ t.title }}
                                </td>
                                <td class="py-2.5 px-3 text-neutral-500">
                                    {{ t.category || '—' }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-neutral-600 dark:text-neutral-300">
                                    {{ t.estimated_duration }}m
                                </td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-neutral-900 dark:text-white">
                                    {{ t.actual_duration }}m
                                </td>
                                <td
                                    class="py-2.5 px-3 font-mono font-semibold"
                                    :class="
                                        t.delta_minutes > 0
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : t.delta_minutes < 0
                                              ? 'text-emerald-600 dark:text-emerald-400'
                                              : 'text-neutral-500'
                                    "
                                >
                                    {{ t.delta_minutes > 0 ? '+' : '' }}{{ t.delta_minutes }}m
                                </td>
                                <td class="py-2.5 pl-3">
                                    <span
                                        v-if="t.verdict === 'on_target'"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                                    >
                                        <CheckCircle2 class="w-3 h-3" /> On Target ({{ t.accuracy_percent }}%)
                                    </span>
                                    <span
                                        v-else-if="t.verdict === 'underestimated'"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                                    >
                                        <AlertTriangle class="w-3 h-3" /> Underestimated ({{ t.accuracy_percent }}%)
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                                    >
                                        Overestimated ({{ t.accuracy_percent }}%)
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

