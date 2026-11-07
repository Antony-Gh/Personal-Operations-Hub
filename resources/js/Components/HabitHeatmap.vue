<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { HabitHeatmap } from '@/types';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps<{
    heatmap: HabitHeatmap;
}>();

const weekdayHeaders = computed(() => {
    return props.heatmap.week_start === 0
        ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
        : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
});

const navigateMonth = (targetMonth: string) => {
    router.get(
        route('habits.index'),
        { heatmap_month: targetMonth },
        { preserveScroll: true, preserveState: true }
    );
};

const levelCellClass = (level: number, isCurrentMonth: boolean) => {
    const dim = isCurrentMonth ? '' : 'opacity-35';
    switch (level) {
        case 4:
            return `${dim} bg-emerald-600 dark:bg-emerald-500 text-white`;
        case 3:
            return `${dim} bg-emerald-400 dark:bg-emerald-600/80 text-emerald-950 dark:text-white`;
        case 2:
            return `${dim} bg-emerald-200 dark:bg-emerald-800/70 text-emerald-900 dark:text-emerald-100`;
        case 1:
            return `${dim} bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-200`;
        default:
            return `${dim} bg-neutral-100 dark:bg-neutral-800/70 text-neutral-500 dark:text-neutral-400`;
    }
};
</script>

<template>
    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-start gap-6">
            <!-- Monthly Calendar Heatmap -->
            <div class="flex-1">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                            Consistency Heatmap — {{ heatmap.month_label }}
                        </h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            Daily habit completion intensity across active habits
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="navigateMonth(heatmap.prev_month)"
                            class="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            title="Previous month"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            type="button"
                            @click="navigateMonth(heatmap.next_month)"
                            class="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            title="Next month"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-7 gap-1.5 mb-1.5">
                    <div
                        v-for="wd in weekdayHeaders"
                        :key="wd"
                        class="text-center text-[10px] font-bold uppercase tracking-wider text-neutral-400"
                    >
                        {{ wd }}
                    </div>
                </div>

                <div class="grid grid-cols-7 gap-1.5">
                    <div
                        v-for="day in heatmap.days"
                        :key="day.date"
                        :title="`${day.date}: ${day.completed_count}/${day.total_habits} completed (${day.ratio}%)`"
                        class="h-9 rounded-lg flex flex-col items-center justify-center text-xs font-medium transition select-none"
                        :class="[
                            levelCellClass(day.level, day.is_current_month),
                            day.is_today ? 'ring-2 ring-indigo-500 ring-offset-1 dark:ring-offset-neutral-900 font-bold' : '',
                        ]"
                    >
                        <span>{{ day.day_number }}</span>
                    </div>
                </div>

                <!-- Legend -->
                <div class="flex items-center justify-end gap-1.5 mt-3 text-[11px] text-neutral-400">
                    <span>Less</span>
                    <span class="w-3.5 h-3.5 rounded bg-neutral-100 dark:bg-neutral-800"></span>
                    <span class="w-3.5 h-3.5 rounded bg-emerald-100 dark:bg-emerald-950"></span>
                    <span class="w-3.5 h-3.5 rounded bg-emerald-200 dark:bg-emerald-800"></span>
                    <span class="w-3.5 h-3.5 rounded bg-emerald-400 dark:bg-emerald-600"></span>
                    <span class="w-3.5 h-3.5 rounded bg-emerald-600 dark:bg-emerald-500"></span>
                    <span>More</span>
                </div>
            </div>

            <!-- 6-Month Rolling Summary -->
            <div class="lg:w-72 border-t lg:border-t-0 lg:border-l border-neutral-100 dark:border-neutral-800 pt-4 lg:pt-0 lg:pl-6">
                <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-400 mb-3">
                    6-Month Momentum
                </h4>
                <div class="space-y-2.5">
                    <button
                        v-for="m in heatmap.monthly_summary"
                        :key="m.month"
                        type="button"
                        @click="navigateMonth(m.month)"
                        class="w-full text-left group"
                    >
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span
                                class="font-medium group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition"
                                :class="
                                    m.month === heatmap.month
                                        ? 'text-indigo-600 dark:text-indigo-400 font-semibold'
                                        : 'text-neutral-700 dark:text-neutral-300'
                                "
                            >
                                {{ m.label }}
                            </span>
                            <span class="text-[11px] text-neutral-500">
                                {{ m.consistency_percent }}% • {{ m.active_days }}d active
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                                :style="{ width: `${m.consistency_percent}%` }"
                            ></div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

