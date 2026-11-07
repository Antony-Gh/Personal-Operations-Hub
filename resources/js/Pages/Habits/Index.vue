<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HabitHeatmap from '@/Components/HabitHeatmap.vue';
import { Habit, HabitHeatmap as HabitHeatmapType } from '@/types';
import {
    Sparkles,
    Plus,
    Check,
    Archive,
    Flame,
    Trophy,
    TrendingUp,
    CalendarCheck2,
} from 'lucide-vue-next';

const props = defineProps<{
    habits: Habit[];
    summary?: {
        top_current_streak: number;
        all_time_best_streak: number;
        average_completion_percentage: number;
        average_weekly_consistency: number;
        completed_today_count: number;
        total_active_habits: number;
    };
    heatmap?: HabitHeatmapType;
    todayDate: string;
    pastDates: string[];
}>();

const isCreateOpen = ref(false);
const habitForm = useForm({
    name: '',
    description: '',
    category: 'Spiritual Life',
    frequency: 'daily' as 'daily' | 'weekly',
    target_days_per_week: 7,
});

const submitHabit = () => {
    habitForm.post(route('habits.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            habitForm.reset();
        },
    });
};

const checkIn = (habitId: number, date: string, status: 'completed' | 'partial' | 'not_completed') => {
    router.post(
        route('habits.check-in', habitId),
        { date, status },
        { preserveScroll: true }
    );
};

const cycleHistoryDay = (habitId: number, date: string, currentStatus: string) => {
    const nextStatus: 'completed' | 'partial' | 'not_completed' =
        currentStatus === 'completed'
            ? 'partial'
            : currentStatus === 'partial'
              ? 'not_completed'
              : 'completed';
    checkIn(habitId, date, nextStatus);
};

const archiveHabit = (habitId: number) => {
    if (confirm('Archive this habit? You can restore it anytime.')) {
        router.delete(route('habits.destroy', habitId), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout title="Habits & Spiritual Life">
        <Head title="Habits & Spiritual Life" />

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <Sparkles class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Habits & Spiritual Life</h2>
                        <p class="text-xs text-neutral-400">
                            Nurturing daily disciplines, spiritual presence, and recovery with gentle consistency
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="isCreateOpen = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add Habit</span>
                    </button>
                </div>
            </div>

            <!-- Streak & Consistency Analytics Cards -->
            <div v-if="summary" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Top Current Streak</span>
                        <Flame class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.top_current_streak }} <span class="text-xs font-normal text-neutral-400">days</span>
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">Active momentum</p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">All-Time Best Streak</span>
                        <Trophy class="w-4 h-4 text-indigo-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.all_time_best_streak }} <span class="text-xs font-normal text-neutral-400">days</span>
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">Longest sustained rhythm</p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Weekly Consistency</span>
                        <TrendingUp class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.average_weekly_consistency }}%
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">Against weekly targets</p>
                </div>

                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400 mb-1.5">
                        <span class="text-xs font-medium">Checked In Today</span>
                        <CalendarCheck2 class="w-4 h-4 text-purple-500" />
                    </div>
                    <div class="text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ summary.completed_today_count }}/{{ summary.total_active_habits }}
                    </div>
                    <p class="text-[11px] text-neutral-400 mt-1">{{ summary.average_completion_percentage }}% overall completion</p>
                </div>
            </div>

            <!-- Monthly Consistency Heatmap -->
            <HabitHeatmap v-if="heatmap" :heatmap="heatmap" />

            <!-- Habits Grid -->
            <div class="space-y-4">
                <div
                    v-for="habit in habits"
                    :key="habit.id"
                    class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs transition"
                >
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                    {{ habit.category }}
                                </span>
                                <span class="text-xs text-neutral-400">Target: {{ habit.target_days_per_week }} days/week</span>
                                <span
                                    v-if="(habit.current_streak || 0) > 0"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                                >
                                    <Flame class="w-3 h-3" /> {{ habit.current_streak }}d streak
                                </span>
                                <span
                                    v-if="(habit.best_streak || 0) > 0"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"
                                >
                                    Best: {{ habit.best_streak }}d
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-neutral-900 dark:text-white">{{ habit.name }}</h3>
                            <p v-if="habit.description" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                {{ habit.description }}
                            </p>
                        </div>

                        <!-- Today Check-in Buttons -->
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs font-medium text-neutral-500 mr-1">Today:</span>
                            <button
                                type="button"
                                @click="checkIn(habit.id, todayDate, 'completed')"
                                class="px-3 py-1.5 rounded-xl text-xs font-medium transition flex items-center gap-1"
                                :class="habit.today_status === 'completed' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-emerald-50 hover:text-emerald-700'"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>Completed</span>
                            </button>
                            <button
                                type="button"
                                @click="checkIn(habit.id, todayDate, 'partial')"
                                class="px-3 py-1.5 rounded-xl text-xs font-medium transition"
                                :class="habit.today_status === 'partial' ? 'bg-amber-500 text-white shadow-xs' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-amber-50 hover:text-amber-700'"
                            >
                                Partial
                            </button>
                            <button
                                type="button"
                                @click="archiveHabit(habit.id)"
                                class="p-1.5 text-neutral-400 hover:text-rose-500 rounded-lg ml-2"
                                title="Archive habit"
                            >
                                <Archive class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Past 14 Days Consistency Matrix + Analytics Pills -->
                    <div class="pt-3 border-t border-neutral-100 dark:border-neutral-800">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-neutral-400 mb-2">
                            <span>Past 14 Days (click any day to toggle)</span>
                            <div class="flex items-center gap-3 text-neutral-600 dark:text-neutral-300 font-medium">
                                <span>{{ habit.completed_in_7_days }}/{{ habit.target_days_per_week }} this week ({{ habit.weekly_consistency ?? 0 }}%)</span>
                                <span v-if="habit.monthly_consistency !== undefined" class="text-neutral-400">•</span>
                                <span v-if="habit.monthly_consistency !== undefined">30d: {{ habit.monthly_consistency }}%</span>
                                <span v-if="habit.total_completed_days !== undefined" class="text-neutral-400">•</span>
                                <span v-if="habit.total_completed_days !== undefined">{{ habit.total_completed_days }} total days</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                            <button
                                v-for="item in habit.history"
                                :key="item.date"
                                type="button"
                                @click="cycleHistoryDay(habit.id, item.date, item.status)"
                                class="flex-1 min-w-[28px] max-w-[45px] text-center group"
                                :title="`${item.date}: ${item.status} (click to cycle)`"
                            >
                                <div
                                    class="h-8 rounded-lg flex items-center justify-center text-[10px] font-bold transition shadow-xs group-hover:ring-2 group-hover:ring-indigo-400"
                                    :class="{
                                        'bg-emerald-500 text-white': item.status === 'completed',
                                        'bg-amber-400 text-neutral-900': item.status === 'partial',
                                        'bg-neutral-100 dark:bg-neutral-800 text-neutral-400': item.status === 'none' || item.status === 'not_completed',
                                    }"
                                >
                                    <Check v-if="item.status === 'completed'" class="w-3.5 h-3.5" />
                                    <span v-else-if="item.status === 'partial'">~</span>
                                    <span v-else>&bull;</span>
                                </div>
                                <span class="text-[9px] text-neutral-400 font-mono block mt-1">
                                    {{ item.date.slice(8) }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Habit Modal -->
            <div
                v-if="isCreateOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isCreateOpen = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-xl p-5 max-w-md w-full border border-neutral-200 dark:border-neutral-800 shadow-xl space-y-4">
                    <h4 class="text-sm font-semibold text-neutral-900 dark:text-white">Create Habit / Spiritual Practice</h4>
                    <form @submit.prevent="submitHabit" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium mb-1">Habit Name *</label>
                            <input
                                v-model="habitForm.name"
                                type="text"
                                required
                                placeholder="e.g. Prayer, Reading, Meditation, Walking"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Category</label>
                            <input
                                v-model="habitForm.category"
                                type="text"
                                placeholder="e.g. Spiritual Life, Health, Learning"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Description / Intent</label>
                            <textarea
                                v-model="habitForm.description"
                                rows="2"
                                placeholder="Gentle reminder of why this practice matters to you"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-medium mb-1">Frequency</label>
                                <select
                                    v-model="habitForm.frequency"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-medium mb-1">Target Days/Week</label>
                                <input
                                    v-model.number="habitForm.target_days_per_week"
                                    type="number"
                                    min="1"
                                    max="7"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="isCreateOpen = false"
                                class="px-3 py-1.5 text-neutral-600 hover:bg-neutral-100 rounded"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-3 py-1.5 bg-indigo-600 text-white rounded font-medium hover:bg-indigo-700"
                            >
                                Save Habit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
