<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Category, Project, Task } from '@/types';
import {
    Calendar as CalendarIcon,
    AlertCircle,
    CheckCircle2,
    Clock,
    ChevronRight,
    Sparkles,
    Smartphone,
    Layers,
    FolderKanban,
    Plus,
    ArrowUpRight,
    Flame,
    Check,
} from 'lucide-vue-next';

interface MetricData {
    dueTodayCount: number;
    overdueCount: number;
    completedTodayCount: number;
    upcomingCount: number;
}

interface HabitSummary {
    id: number;
    name: string;
    category: string;
    status: 'completed' | 'partial' | 'not_completed' | 'pending';
    notes: string | null;
}

const props = defineProps<{
    todayDate: string;
    metrics: MetricData;
    priorities: Task[];
    dueTodayTasks: Task[];
    overdueTasks: Task[];
    areas: Category[];
    projects: Project[];
    habits: HabitSummary[];
    phoneUsage: {
        minutes: number;
        target: number;
        notes: string | null;
    };
}>();

const toggleTaskCompletion = (task: Task) => {
    router.post(route('tasks.toggle', task.id), {}, { preserveScroll: true });
};

const recordHabitCheckIn = (habitId: number, status: 'completed' | 'partial' | 'not_completed') => {
    const today = new Date().toISOString().split('T')[0];
    router.post(route('habits.check-in', habitId), { date: today, status }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout title="Dashboard">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Welcome & Date Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div>
                    <div class="flex items-center gap-2 text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">
                        <CalendarIcon class="w-3.5 h-3.5" />
                        <span>{{ todayDate }}</span>
                    </div>
                    <h2 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-white">
                        Welcome, {{ $page.props.auth.user.setting?.display_name || $page.props.auth.user.name }}
                    </h2>
                </div>
                <div class="flex items-center gap-2.5">
                    <Link
                        :href="route('my-day.index')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <span>Open My Day</span>
                        <ChevronRight class="w-4 h-4" />
                    </Link>
                </div>
            </div>

            <!-- Top Metric Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Due Today -->
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-2">
                        <span class="text-xs font-medium">Due Today</span>
                        <Clock class="w-4 h-4 text-indigo-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ metrics.dueTodayCount }}
                    </div>
                    <div class="mt-1 text-[11px] text-neutral-400">
                        Tasks needing action today
                    </div>
                </div>

                <!-- Overdue -->
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-2">
                        <span class="text-xs font-medium">Overdue</span>
                        <AlertCircle class="w-4 h-4 text-rose-500" />
                    </div>
                    <div class="text-2xl font-bold" :class="metrics.overdueCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-neutral-900 dark:text-white'">
                        {{ metrics.overdueCount }}
                    </div>
                    <div class="mt-1 text-[11px] text-neutral-400">
                        {{ metrics.overdueCount > 0 ? 'Requires attention' : 'All caught up' }}
                    </div>
                </div>

                <!-- Completed Today -->
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-2">
                        <span class="text-xs font-medium">Completed Today</span>
                        <CheckCircle2 class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ metrics.completedTodayCount }}
                    </div>
                    <div class="mt-1 text-[11px] text-neutral-400">
                        Accomplished today
                    </div>
                </div>

                <!-- Upcoming Deadlines -->
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-2">
                        <span class="text-xs font-medium">Upcoming (7d)</span>
                        <CalendarIcon class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ metrics.upcomingCount }}
                    </div>
                    <div class="mt-1 text-[11px] text-neutral-400">
                        Deadlines in next 7 days
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Priorities, Due Today & Overdue -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Today's Top Priorities -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                                    <Flame class="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Today's Priorities</h3>
                                    <p class="text-[11px] text-neutral-400">Up to 3 high-impact focuses for today</p>
                                </div>
                            </div>
                            <Link :href="route('my-day.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                Edit in My Day
                            </Link>
                        </div>

                        <div v-if="priorities.length === 0" class="text-center py-6 text-neutral-400 text-xs">
                            No main priorities assigned for today yet.
                            <Link :href="route('my-day.index')" class="text-indigo-600 dark:text-indigo-400 hover:underline ml-1">
                                Pick your 3 priorities
                            </Link>
                        </div>

                        <div v-else class="space-y-2.5">
                            <div
                                v-for="(task, idx) in priorities"
                                :key="task.id"
                                class="flex items-start gap-3 p-3 rounded-xl border border-neutral-100 dark:border-neutral-800/80 hover:border-neutral-200 dark:hover:border-neutral-700 transition bg-neutral-50/50 dark:bg-neutral-900/40"
                            >
                                <span class="text-xs font-bold text-neutral-400 pt-0.5">#{{ idx + 1 }}</span>
                                <button
                                    @click="toggleTaskCompletion(task)"
                                    class="mt-0.5 w-4 h-4 rounded border flex items-center justify-center transition"
                                    :class="task.status === 'completed' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-neutral-300 dark:border-neutral-600 hover:border-indigo-500'"
                                >
                                    <Check v-if="task.status === 'completed'" class="w-3 h-3" />
                                </button>
                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-xs font-medium text-neutral-900 dark:text-white"
                                        :class="{ 'line-through text-neutral-400 dark:text-neutral-500': task.status === 'completed' }"
                                    >
                                        {{ task.title }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-neutral-400 flex-wrap">
                                        <span v-if="task.category" class="inline-flex items-center gap-1 font-medium" :style="{ color: task.category.color }">
                                            <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: task.category.color }"></span>
                                            {{ task.category.name }}
                                        </span>
                                        <span v-if="task.next_action" class="text-neutral-500 dark:text-neutral-400 italic">
                                            Next: {{ task.next_action }}
                                        </span>
                                    </div>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] uppercase font-semibold tracking-wider shrink-0"
                                    :class="task.priority === 'high' ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'"
                                >
                                    {{ task.priority }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tasks Due Today & Overdue -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Today's Schedule & Deadlines</h3>
                                <p class="text-[11px] text-neutral-400">Tasks requiring completion or rescheduling</p>
                            </div>
                            <Link :href="route('tasks.index', { date_filter: 'today' })" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                View all tasks
                            </Link>
                        </div>

                        <!-- Overdue Warning List (if any) -->
                        <div v-if="overdueTasks.length > 0" class="mb-4 space-y-2">
                            <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wider flex items-center gap-1">
                                <AlertCircle class="w-3.5 h-3.5" />
                                <span>Overdue Tasks ({{ overdueTasks.length }})</span>
                            </div>
                            <div
                                v-for="task in overdueTasks.slice(0, 3)"
                                :key="'od-' + task.id"
                                class="flex items-center justify-between p-2.5 rounded-lg border border-rose-200/60 dark:border-rose-900/40 bg-rose-50/40 dark:bg-rose-950/20 text-xs"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <button
                                        @click="toggleTaskCompletion(task)"
                                        class="w-4 h-4 rounded border border-rose-400 flex items-center justify-center shrink-0"
                                    >
                                        <Check v-if="task.status === 'completed'" class="w-3 h-3 text-white" />
                                    </button>
                                    <span class="font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ task.title }}</span>
                                </div>
                                <span class="text-[10px] text-rose-600 dark:text-rose-400 shrink-0">Due {{ task.due_date }}</span>
                            </div>
                        </div>

                        <!-- Due Today List -->
                        <div v-if="dueTodayTasks.length === 0" class="text-center py-6 text-neutral-400 text-xs">
                            No remaining tasks due today.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="task in dueTodayTasks"
                                :key="'today-' + task.id"
                                class="flex items-center justify-between p-2.5 rounded-lg border border-neutral-100 dark:border-neutral-800 hover:border-neutral-200 dark:hover:border-neutral-700 transition text-xs"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <button
                                        @click="toggleTaskCompletion(task)"
                                        class="w-4 h-4 rounded border flex items-center justify-center shrink-0 transition"
                                        :class="task.status === 'completed' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-neutral-300 dark:border-neutral-600 hover:border-indigo-500'"
                                    >
                                        <Check v-if="task.status === 'completed'" class="w-3 h-3" />
                                    </button>
                                    <span
                                        class="font-medium text-neutral-800 dark:text-neutral-200 truncate"
                                        :class="{ 'line-through text-neutral-400': task.status === 'completed' }"
                                    >
                                        {{ task.title }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span v-if="task.category" class="text-[10px] px-2 py-0.5 rounded font-medium" :style="{ backgroundColor: (task.category.color || '#6366f1') + '15', color: task.category.color || '#6366f1' }">
                                        {{ task.category.name }}
                                    </span>
                                    <span v-if="task.estimated_duration" class="text-[10px] text-neutral-400">
                                        {{ task.estimated_duration }}m
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Areas / Categories Overview -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                    <Layers class="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Active Areas</h3>
                                    <p class="text-[11px] text-neutral-400">Responsibility domains and active load</p>
                                </div>
                            </div>
                            <Link :href="route('categories.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                Manage areas
                            </Link>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <Link
                                v-for="cat in areas"
                                :key="cat.id"
                                :href="route('tasks.index', { category_id: cat.id })"
                                class="p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 hover:border-neutral-200 dark:hover:border-neutral-700 bg-neutral-50/30 dark:bg-neutral-900/40 transition group"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: cat.color || '#6366f1' }"></span>
                                        <span class="text-xs font-semibold text-neutral-900 dark:text-white group-hover:text-indigo-600 transition truncate max-w-[130px]">
                                            {{ cat.name }}
                                        </span>
                                    </div>
                                    <ArrowUpRight class="w-3.5 h-3.5 text-neutral-400 group-hover:text-indigo-600 transition" />
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-neutral-500 dark:text-neutral-400">
                                    <span>{{ cat.active_tasks_count }} active tasks</span>
                                    <span v-if="(cat.upcoming_deadlines_count ?? 0) > 0" class="text-amber-600 dark:text-amber-400 font-medium">
                                        {{ cat.upcoming_deadlines_count }} upcoming
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Right Col: Habits, Projects & Phone Tracker -->
                <div class="space-y-6">
                    <!-- Personal Habits Check-in -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                                    <Sparkles class="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Habits & Spiritual Life</h3>
                                    <p class="text-[11px] text-neutral-400">Consistency with grace</p>
                                </div>
                            </div>
                            <Link :href="route('habits.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                Habits page
                            </Link>
                        </div>

                        <div class="space-y-2.5">
                            <div
                                v-for="habit in habits"
                                :key="habit.id"
                                class="flex items-center justify-between p-2.5 rounded-xl border border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/30"
                            >
                                <div>
                                    <p class="text-xs font-medium text-neutral-900 dark:text-white">{{ habit.name }}</p>
                                    <p class="text-[10px] text-neutral-400">{{ habit.category }}</p>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        @click="recordHabitCheckIn(habit.id, 'completed')"
                                        class="px-2 py-1 rounded text-[11px] font-medium transition"
                                        :class="habit.status === 'completed' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-neutral-100 text-neutral-600 hover:bg-emerald-50 hover:text-emerald-700 dark:bg-neutral-800 dark:text-neutral-400'"
                                        title="Completed"
                                    >
                                        Done
                                    </button>
                                    <button
                                        @click="recordHabitCheckIn(habit.id, 'partial')"
                                        class="px-2 py-1 rounded text-[11px] font-medium transition"
                                        :class="habit.status === 'partial' ? 'bg-amber-500 text-white shadow-xs' : 'bg-neutral-100 text-neutral-600 hover:bg-amber-50 hover:text-amber-700 dark:bg-neutral-800 dark:text-neutral-400'"
                                        title="Partially completed"
                                    >
                                        Partial
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Projects Overview -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                    <FolderKanban class="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Active Projects</h3>
                                    <p class="text-[11px] text-neutral-400">Milestones and execution</p>
                                </div>
                            </div>
                            <Link :href="route('projects.index')" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                View all
                            </Link>
                        </div>

                        <div v-if="projects.length === 0" class="text-center py-6 text-neutral-400 text-xs">
                            No active projects.
                        </div>
                        <div v-else class="space-y-3">
                            <Link
                                v-for="proj in projects"
                                :key="proj.id"
                                :href="route('projects.show', proj.id)"
                                class="block p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 hover:border-neutral-200 dark:hover:border-neutral-700 bg-neutral-50/30 dark:bg-neutral-900/40 transition group"
                            >
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-semibold text-neutral-900 dark:text-white group-hover:text-indigo-600 transition truncate">
                                        {{ proj.name }}
                                    </span>
                                    <span class="text-[10px] text-neutral-400">
                                        {{ proj.progress_percent }}%
                                    </span>
                                </div>
                                <!-- Progress Bar -->
                                <div class="w-full bg-neutral-200 dark:bg-neutral-800 rounded-full h-1.5 mb-2 overflow-hidden">
                                    <div class="bg-indigo-600 h-1.5 rounded-full transition-all" :style="{ width: `${proj.progress_percent}%` }"></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-neutral-400">
                                    <span>{{ proj.completed_tasks_count }} / {{ proj.total_tasks_count }} tasks done</span>
                                    <span>{{ proj.milestones?.length || 0 }} milestones</span>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Phone Usage Summary -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                                    <Smartphone class="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Phone Usage Today</h3>
                                    <p class="text-[11px] text-neutral-400">Mindful leisure tracking</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-baseline justify-between py-2">
                            <div>
                                <span class="text-2xl font-bold text-neutral-900 dark:text-white">{{ phoneUsage.minutes }}</span>
                                <span class="text-xs text-neutral-400 ml-1">mins recorded</span>
                            </div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                Target: &le; {{ phoneUsage.target }}m
                            </span>
                        </div>
                        <div class="w-full bg-neutral-200 dark:bg-neutral-800 rounded-full h-2 overflow-hidden">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="phoneUsage.minutes > phoneUsage.target ? 'bg-amber-500' : 'bg-purple-500'"
                                :style="{ width: `${Math.min(100, (phoneUsage.minutes / (phoneUsage.target || 60)) * 100)}%` }"
                            ></div>
                        </div>
                        <p v-if="phoneUsage.notes" class="text-[11px] text-neutral-400 italic mt-2">
                            "{{ phoneUsage.notes }}"
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
