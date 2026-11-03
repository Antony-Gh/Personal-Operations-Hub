<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Task, WeeklyReview } from '@/types';
import {
    HeartHandshake,
    Calendar,
    ChevronLeft,
    ChevronRight,
    CheckCircle2,
    AlertCircle,
    Save,
    History,
    Sparkles,
} from 'lucide-vue-next';

const props = defineProps<{
    review: WeeklyReview;
    weekStart: string;
    weekEnd: string;
    formattedRange: string;
    prompts: string[];
    completedTasks: Task[];
    overdueTasks: Task[];
    upcomingTasks: Task[];
    pastReviews: WeeklyReview[];
}>();

const initialResponses: Record<string, string> = {};
props.prompts.forEach((p) => {
    initialResponses[p] = props.review.responses?.[p] || '';
});

const reviewForm = useForm({
    responses: initialResponses,
    notes: props.review.notes || '',
    is_completed: props.review.is_completed ?? true,
});

const submitReview = () => {
    reviewForm.put(route('weekly-review.update', props.review.id), {
        preserveScroll: true,
    });
};

const changeWeek = (dateStr: string) => {
    router.get(route('weekly-review.index'), { date: dateStr });
};

const shiftWeek = (weeks: number) => {
    const current = new Date(props.weekStart);
    current.setDate(current.getDate() + weeks * 7);
    changeWeek(current.toISOString().split('T')[0]);
};
</script>

<template>
    <Head title="Weekly Review" />

    <AuthenticatedLayout title="Weekly Review">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header with Week Selector -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                        <HeartHandshake class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">
                            Weekly Reflection: {{ formattedRange }}
                        </h2>
                        <p class="text-xs text-neutral-400">
                            Pause, celebrate wins, calibrate capacity, and protect priorities for the week ahead
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="shiftWeek(-1)"
                        class="p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        title="Previous Week"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button
                        @click="changeWeek(new Date().toISOString().split('T')[0])"
                        class="px-3 py-1.5 rounded-xl border border-neutral-200 dark:border-neutral-800 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800"
                    >
                        Current Week
                    </button>
                    <button
                        @click="shiftWeek(1)"
                        class="p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        title="Next Week"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Top Summary Cards (Completed, Overdue, Upcoming) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Completed this week -->
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center gap-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 mb-2">
                        <CheckCircle2 class="w-4 h-4" />
                        <span>Completed This Week ({{ completedTasks.length }})</span>
                    </div>
                    <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                        <p v-if="completedTasks.length === 0" class="text-neutral-400 text-xs">No tasks completed in this window.</p>
                        <div
                            v-for="task in completedTasks.slice(0, 5)"
                            :key="task.id"
                            class="text-xs text-neutral-700 dark:text-neutral-300 truncate"
                        >
                            &bull; {{ task.title }}
                        </div>
                    </div>
                </div>

                <!-- Overdue Tasks -->
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center gap-2 text-xs font-medium text-rose-600 dark:text-rose-400 mb-2">
                        <AlertCircle class="w-4 h-4" />
                        <span>Needs Attention / Overdue ({{ overdueTasks.length }})</span>
                    </div>
                    <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                        <p v-if="overdueTasks.length === 0" class="text-neutral-400 text-xs">Zero overdue tasks. Great pacing!</p>
                        <div
                            v-for="task in overdueTasks.slice(0, 5)"
                            :key="task.id"
                            class="text-xs text-neutral-700 dark:text-neutral-300 truncate"
                        >
                            &bull; {{ task.title }}
                        </div>
                    </div>
                </div>

                <!-- Upcoming Deadlines -->
                <div class="bg-white dark:bg-neutral-900 p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="flex items-center gap-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 mb-2">
                        <Calendar class="w-4 h-4" />
                        <span>Upcoming Next Week ({{ upcomingTasks.length }})</span>
                    </div>
                    <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                        <p v-if="upcomingTasks.length === 0" class="text-neutral-400 text-xs">No deadlines scheduled next week.</p>
                        <div
                            v-for="task in upcomingTasks.slice(0, 5)"
                            :key="task.id"
                            class="text-xs text-neutral-700 dark:text-neutral-300 truncate"
                        >
                            &bull; {{ task.title }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reflection Prompts Form -->
            <form @submit.prevent="submitReview" class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs space-y-6">
                <div class="border-b border-neutral-100 dark:border-neutral-800 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">Guided Reflection Questions</h3>
                        <p class="text-xs text-neutral-400">Answer truthfully to calibrate workload and recharge energy</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="reviewForm.processing"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition disabled:opacity-50"
                    >
                        <Save class="w-4 h-4" />
                        <span>Save Review</span>
                    </button>
                </div>

                <div class="space-y-5">
                    <div
                        v-for="(prompt, idx) in prompts"
                        :key="idx"
                        class="space-y-1.5"
                    >
                        <label class="block text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                            {{ idx + 1 }}. {{ prompt }}
                        </label>
                        <textarea
                            v-model="reviewForm.responses[prompt]"
                            rows="2"
                            placeholder="Write your honest reflection..."
                            class="w-full text-xs rounded-xl border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <!-- General Notes -->
                    <div class="space-y-1.5 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                        <label class="block text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                            Additional Summary / Observations
                        </label>
                        <textarea
                            v-model="reviewForm.notes"
                            rows="3"
                            placeholder="Any key insights or takeaways for this week"
                            class="w-full text-xs rounded-xl border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="flex justify-end pt-3">
                    <button
                        type="submit"
                        :disabled="reviewForm.processing"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition disabled:opacity-50"
                    >
                        <Save class="w-4 h-4" />
                        <span>Save Weekly Reflection</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

