<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CalendarEvent, DailyPlan, PageProps, PhoneUsageLog, Task } from '@/types';
import { addDaysToLocalDate, formatTime12Hour, getLocalDateString } from '@/utils/date';
import { useFocusTimer } from '@/composables/useFocusTimer';
import {
    Sun,
    ChevronLeft,
    ChevronRight,
    Check,
    Flame,
    ShieldAlert,
    Clock,
    Trash2,
    ArrowUp,
    ArrowDown,
    ListTodo,
    Timer,
    AlertTriangle,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    date: string;
    formattedDate: string;
    dailyPlan: DailyPlan;
    plannedTasks: Task[];
    unplannedDueTasks: Task[];
    candidateTasks: Task[];
    fixedCommitments: CalendarEvent[];
    capacity?: {
        workday_minutes: number;
        committed_event_minutes: number;
        available_free_minutes: number;
        planned_task_minutes: number;
        is_overbooked: boolean;
        overbooked_by_minutes: number;
        utilization_percent: number;
    };
    phoneUsage: PhoneUsageLog | null;
    targetPhoneMinutes: number;
}>();

const page = usePage<PageProps>();
const { startFocusSession } = useFocusTimer();

const planForm = useForm({
    fallback_plan: props.dailyPlan.fallback_plan || '',
    personal_recovery_notes: props.dailyPlan.personal_recovery_notes || '',
    notes: props.dailyPlan.notes || '',
});

const savePlanDetails = () => {
    planForm.put(route('my-day.update', props.dailyPlan.id), {
        preserveScroll: true,
    });
};

const changeDate = (newDate: string) => {
    router.get(route('my-day.index'), { date: newDate });
};

const goToAdjacentDay = (offset: number) => {
    changeDate(addDaysToLocalDate(props.date, offset));
};

const goToToday = () => {
    changeDate(getLocalDateString(page.props.auth.user?.setting?.timezone));
};

// Task Operations
const addTaskToPlan = (taskId: number, isPriority = false) => {
    router.post(
        route('my-day.add-task', props.dailyPlan.id),
        { task_id: taskId, is_priority: isPriority },
        { preserveScroll: true }
    );
};

const togglePriority = (task: Task) => {
    router.post(
        route('my-day.toggle-priority', [props.dailyPlan.id, task.id]),
        {},
        { preserveScroll: true }
    );
};

const removeTaskFromPlan = (task: Task) => {
    router.delete(
        route('my-day.remove-task', [props.dailyPlan.id, task.id]),
        { preserveScroll: true }
    );
};

const toggleTaskCompletion = (task: Task) => {
    router.post(route('tasks.toggle', task.id), {}, { preserveScroll: true });
};

const launchFocus = (task: Task) => {
    void startFocusSession({
        mode: 'focus',
        planned_minutes: task.estimated_duration || page.props.auth.user?.setting?.focus_work_minutes || 25,
        task_id: task.id,
    });
};

// Reorder tasks
const moveTaskOrder = (index: number, direction: 'up' | 'down') => {
    const items = [...props.plannedTasks];
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= items.length) return;

    const temp = items[index];
    items[index] = items[targetIndex];
    items[targetIndex] = temp;

    const order = items.map((t) => t.id);
    router.post(route('my-day.reorder', props.dailyPlan.id), { order }, { preserveScroll: true });
};

// Reschedule task
const reschedulingTaskId = ref<number | null>(null);
const rescheduleDate = ref(props.date);

const openReschedule = (task: Task) => {
    reschedulingTaskId.value = task.id;
    rescheduleDate.value = task.due_date || props.date;
};

const submitReschedule = (task: Task) => {
    router.post(
        route('tasks.reschedule', task.id),
        { due_date: rescheduleDate.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                reschedulingTaskId.value = null;
            },
        }
    );
};

// Visual Time-Blocking (06:00 - 23:00)
const timelineHours = Array.from({ length: 18 }, (_, i) => {
    const h = i + 6;
    return `${String(h).padStart(2, '0')}:00`;
});

const draggingPlanTaskId = ref<number | null>(null);
const dragOverHour = ref<string | null>(null);

const onPlanTaskDragStart = (e: DragEvent, task: Task) => {
    draggingPlanTaskId.value = task.id;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(task.id));
    }
};

const onPlanTaskDragEnd = () => {
    draggingPlanTaskId.value = null;
    dragOverHour.value = null;
};

const saveTimeBlock = (task: Task, startTime: string | null, endTime: string | null = null) => {
    router.patch(
        route('my-day.time-block', [props.dailyPlan.id, task.id]),
        {
            scheduled_start_time: startTime,
            scheduled_end_time: endTime,
        },
        { preserveScroll: true }
    );
};

const onDropHour = (hourHm: string) => {
    const tId = draggingPlanTaskId.value;
    draggingPlanTaskId.value = null;
    dragOverHour.value = null;
    if (!tId) {
        return;
    }
    const task = props.plannedTasks.find((t) => t.id === tId);
    if (!task) {
        return;
    }
    saveTimeBlock(task, hourHm, null);
};

const timelineByHour = computed(() => {
    const map: Record<
        string,
        {
            events: CalendarEvent[];
            tasks: Task[];
        }
    > = {};
    for (const hm of timelineHours) {
        map[hm] = { events: [], tasks: [] };
    }

    for (const evt of props.fixedCommitments) {
        const startHm = evt.local_start_hm || (evt.start_time ? new Date(evt.start_time).toTimeString().slice(0, 5) : null);
        if (!startHm) {
            continue;
        }
        const bucket = `${startHm.slice(0, 2)}:00`;
        if (map[bucket]) {
            map[bucket].events.push(evt);
        }
    }

    for (const t of props.plannedTasks) {
        if (!t.scheduled_start_time) {
            continue;
        }
        const bucket = `${t.scheduled_start_time.slice(0, 2)}:00`;
        if (map[bucket]) {
            map[bucket].tasks.push(t);
        }
    }

    return map;
});
</script>

<template>
    <AuthenticatedLayout title="My Day">
        <Head title="My Day - Daily Planning" />

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Daily Planning Header with Navigation -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <Sun class="w-6 h-6" />
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-neutral-900 dark:text-white">{{ formattedDate }}</h2>
                            <button
                                type="button"
                                @click="goToToday"
                                class="px-2 py-0.5 text-[10px] font-semibold bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 rounded transition"
                            >
                                Today
                            </button>
                        </div>
                        <p class="text-xs text-neutral-400">
                            Intentional focus, clear priorities, and realistic capacity
                        </p>
                    </div>
                </div>

                <!-- Date Picker & Controls -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="goToAdjacentDay(-1)"
                        class="p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        title="Previous Day"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <input
                        type="date"
                        :value="date"
                        @change="changeDate(($event.target as HTMLInputElement).value)"
                        class="text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white px-3 py-1.5 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <button
                        type="button"
                        @click="goToAdjacentDay(1)"
                        class="p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        title="Next Day"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Capacity Meter & Overbooking Warning -->
            <div
                v-if="capacity"
                class="rounded-2xl border p-4 shadow-xs space-y-2.5"
                :class="
                    capacity.is_overbooked
                        ? 'bg-rose-50/70 dark:bg-rose-950/30 border-rose-300 dark:border-rose-900'
                        : 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800'
                "
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <AlertTriangle v-if="capacity.is_overbooked" class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                        <Clock v-else class="w-4 h-4 text-indigo-500 shrink-0" />
                        <span
                            class="font-semibold"
                            :class="capacity.is_overbooked ? 'text-rose-800 dark:text-rose-200' : 'text-neutral-900 dark:text-white'"
                        >
                            <template v-if="capacity.is_overbooked">
                                Overbooked by {{ capacity.overbooked_by_minutes }}m ({{ Math.round((capacity.overbooked_by_minutes / 60) * 10) / 10 }}h) — consider rescheduling or using your Fallback Plan
                            </template>
                            <template v-else>
                                Realistic Daily Capacity: {{ capacity.planned_task_minutes }}m planned of {{ capacity.available_free_minutes }}m free focus capacity ({{ capacity.utilization_percent }}%)
                            </template>
                        </span>
                    </div>
                    <div class="text-[11px] text-neutral-500 dark:text-neutral-400 font-mono">
                        Fixed events: {{ capacity.committed_event_minutes }}m • Remaining: {{ Math.max(0, capacity.available_free_minutes - capacity.planned_task_minutes) }}m
                    </div>
                </div>
                <div class="w-full h-2 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="
                            capacity.is_overbooked
                                ? 'bg-rose-500'
                                : capacity.utilization_percent >= 85
                                  ? 'bg-amber-500'
                                  : 'bg-emerald-500'
                        "
                        :style="{ width: `${Math.min(100, capacity.utilization_percent)}%` }"
                    ></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Planned Tasks & Top Priorities -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Planned Tasks Box -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                                    Planned for This Day ({{ plannedTasks.length }})
                                </h3>
                                <p class="text-[11px] text-neutral-400">
                                    Reorder tasks, mark up to 3 main priorities, and start focus blocks
                                </p>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="plannedTasks.length === 0" class="text-center py-10 px-4 border border-dashed border-neutral-200 dark:border-neutral-800 rounded-xl text-neutral-400 text-xs">
                            <ListTodo class="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                            No tasks planned for this day yet. Select from tasks due today below or add candidate tasks.
                        </div>

                        <!-- Planned Task List -->
                        <div v-else class="space-y-2.5">
                            <div
                                v-for="(task, idx) in plannedTasks"
                                :key="'plan-' + task.id"
                                draggable="true"
                                @dragstart="onPlanTaskDragStart($event, task)"
                                @dragend="onPlanTaskDragEnd"
                                class="flex items-start gap-3 p-3 rounded-xl border transition cursor-grab active:cursor-grabbing"
                                :class="
                                    task.is_priority
                                        ? 'border-amber-300 dark:border-amber-700/60 bg-amber-50/20 dark:bg-amber-950/10'
                                        : 'border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/30'
                                "
                            >
                                <!-- Complete Checkbox -->
                                <button
                                    type="button"
                                    @click="toggleTaskCompletion(task)"
                                    class="mt-0.5 w-4 h-4 rounded border flex items-center justify-center transition shrink-0"
                                    :class="task.status === 'completed' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-neutral-300 dark:border-neutral-600 hover:border-indigo-500'"
                                >
                                    <Check v-if="task.status === 'completed'" class="w-3 h-3" />
                                </button>

                                <!-- Title & details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span
                                            class="text-xs font-medium text-neutral-900 dark:text-white"
                                            :class="{ 'line-through text-neutral-400 dark:text-neutral-500': task.status === 'completed' }"
                                        >
                                            {{ task.title }}
                                        </span>
                                        <span
                                            v-if="task.is_priority"
                                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                        >
                                            <Flame class="w-2.5 h-2.5" /> Priority
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 mt-1 text-[11px] text-neutral-400 flex-wrap">
                                        <span v-if="task.category" class="inline-flex items-center gap-1 font-medium" :style="{ color: task.category.color }">
                                            <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: task.category.color }"></span>
                                            {{ task.category.name }}
                                        </span>
                                        <span v-if="task.project" class="text-neutral-500 dark:text-neutral-400">
                                            &bull; {{ task.project.name }}
                                        </span>
                                        <span v-if="task.due_time" class="font-mono">
                                            &bull; {{ formatTime12Hour(task.due_time) }}
                                        </span>
                                        <span v-if="task.estimated_duration || task.actual_duration" class="text-neutral-400">
                                            &bull; <template v-if="task.actual_duration">{{ task.actual_duration }}/</template>{{ task.estimated_duration || 0 }}m
                                        </span>
                                    </div>

                                    <!-- Inline Time-Block Assignment -->
                                    <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                        <span class="text-[10px] font-medium text-neutral-400">Time block:</span>
                                        <input
                                            type="time"
                                            :value="task.scheduled_start_time || ''"
                                            @change="saveTimeBlock(task, ($event.target as HTMLInputElement).value || null, task.scheduled_end_time || null)"
                                            class="text-[11px] py-0.5 px-1.5 rounded border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 font-mono"
                                        />
                                        <span v-if="task.scheduled_start_time" class="text-[10px] text-neutral-400">&ndash;</span>
                                        <input
                                            v-if="task.scheduled_start_time"
                                            type="time"
                                            :value="task.scheduled_end_time || ''"
                                            @change="saveTimeBlock(task, task.scheduled_start_time || null, ($event.target as HTMLInputElement).value || null)"
                                            class="text-[11px] py-0.5 px-1.5 rounded border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 font-mono"
                                        />
                                        <button
                                            v-if="task.scheduled_start_time"
                                            type="button"
                                            @click="saveTimeBlock(task, null, null)"
                                            class="p-0.5 rounded text-neutral-400 hover:text-rose-500"
                                            title="Clear time block"
                                        >
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Action Buttons: Focus, Priority Toggle, Move Up/Down, Remove -->
                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        v-if="task.status !== 'completed'"
                                        type="button"
                                        @click="launchFocus(task)"
                                        class="p-1 rounded text-neutral-400 hover:text-indigo-600 transition"
                                        title="Start Focus Timer"
                                    >
                                        <Timer class="w-3.5 h-3.5" />
                                    </button>

                                    <button
                                        type="button"
                                        @click="togglePriority(task)"
                                        class="p-1 rounded text-neutral-400 hover:text-amber-500 transition"
                                        :class="{ 'text-amber-500': task.is_priority }"
                                        title="Toggle main priority"
                                    >
                                        <Flame class="w-4 h-4" />
                                    </button>

                                    <button
                                        v-if="idx > 0"
                                        type="button"
                                        @click="moveTaskOrder(idx, 'up')"
                                        class="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                        title="Move up"
                                    >
                                        <ArrowUp class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        v-if="idx < plannedTasks.length - 1"
                                        type="button"
                                        @click="moveTaskOrder(idx, 'down')"
                                        class="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                        title="Move down"
                                    >
                                        <ArrowDown class="w-3.5 h-3.5" />
                                    </button>

                                    <button
                                        type="button"
                                        @click="removeTaskFromPlan(task)"
                                        class="p-1 text-neutral-400 hover:text-rose-500 transition"
                                        title="Remove from plan"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tasks Due on this date but not yet planned -->
                    <div v-if="unplannedDueTasks.length > 0" class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                                    Due On This Date (Unplanned)
                                </h3>
                                <p class="text-[11px] text-neutral-400">
                                    These tasks have a due date of today but are not added to your daily schedule yet
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="task in unplannedDueTasks"
                                :key="'due-' + task.id"
                                class="flex items-center justify-between p-2.5 rounded-lg border border-neutral-100 dark:border-neutral-800 hover:border-neutral-200 text-xs"
                            >
                                <div class="min-w-0">
                                    <p class="font-medium text-neutral-800 dark:text-neutral-200 truncate">{{ task.title }}</p>
                                    <p v-if="task.category" class="text-[10px] text-neutral-400">{{ task.category.name }}</p>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        @click="addTaskToPlan(task.id, false)"
                                        class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 text-xs font-medium transition"
                                    >
                                        + Plan
                                    </button>
                                    <button
                                        type="button"
                                        @click="openReschedule(task)"
                                        class="px-2 py-1 rounded bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 text-xs transition"
                                    >
                                        Reschedule
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Candidate Active Tasks to pull from -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                                    Add Tasks to Today's Plan
                                </h3>
                                <p class="text-[11px] text-neutral-400">
                                    Active tasks available in your backlog
                                </p>
                            </div>
                        </div>

                        <div v-if="candidateTasks.length === 0" class="text-center py-4 text-xs text-neutral-400">
                            No additional active tasks found.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="task in candidateTasks.slice(0, 5)"
                                :key="'cand-' + task.id"
                                class="flex items-center justify-between p-2.5 rounded-lg border border-neutral-100 dark:border-neutral-800 text-xs"
                            >
                                <div class="min-w-0">
                                    <span class="font-medium text-neutral-800 dark:text-neutral-200 truncate block">{{ task.title }}</span>
                                    <span class="text-[10px] text-neutral-400">
                                        {{ task.category?.name || 'No Category' }} &bull; Due {{ task.due_date || 'No Date' }}<template v-if="task.due_time"> {{ formatTime12Hour(task.due_time) }}</template>
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    @click="addTaskToPlan(task.id)"
                                    class="px-2.5 py-1 rounded bg-neutral-100 hover:bg-indigo-600 hover:text-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 font-medium transition text-xs shrink-0"
                                >
                                    + Add to Plan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Col: Visual Time-Blocking Timeline, Fixed Commitments & Fallback Plan -->
                <div class="space-y-6">
                    <!-- Interactive Visual Hourly Time-Blocking Timeline (06:00 - 23:00) -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                    <Clock class="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Visual Time-Block Schedule</h3>
                                    <p class="text-[11px] text-neutral-400">Drag planned tasks onto any hour slot (06:00–23:00)</p>
                                </div>
                            </div>
                        </div>

                        <div class="max-h-[26rem] overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800/80 border border-neutral-100 dark:border-neutral-800 rounded-xl">
                            <div
                                v-for="hm in timelineHours"
                                :key="hm"
                                @dragover.prevent="dragOverHour = hm"
                                @dragleave="dragOverHour === hm ? (dragOverHour = null) : null"
                                @drop.prevent="onDropHour(hm)"
                                class="flex items-stretch min-h-[2.4rem] text-xs transition-colors"
                                :class="
                                    dragOverHour === hm
                                        ? 'bg-indigo-50/70 dark:bg-indigo-950/40'
                                        : 'hover:bg-neutral-50/60 dark:hover:bg-neutral-800/30'
                                "
                            >
                                <div class="w-14 shrink-0 py-2 px-2 font-mono text-[10px] text-neutral-400 border-r border-neutral-100 dark:border-neutral-800 select-none">
                                    {{ hm }}
                                </div>
                                <div class="flex-1 p-1.5 space-y-1">
                                    <div
                                        v-for="evt in timelineByHour[hm]?.events || []"
                                        :key="'tl-evt-' + evt.id"
                                        class="px-2 py-1 rounded-md bg-blue-50 dark:bg-blue-950/60 border border-blue-200/70 dark:border-blue-900 text-blue-800 dark:text-blue-200 text-[11px] flex items-center justify-between gap-2"
                                    >
                                        <span class="font-medium truncate">{{ evt.title }}</span>
                                        <span class="font-mono text-[10px] shrink-0">{{ evt.local_start_hm }}–{{ evt.local_end_hm }}</span>
                                    </div>
                                    <div
                                        v-for="t in timelineByHour[hm]?.tasks || []"
                                        :key="'tl-task-' + t.id"
                                        class="px-2 py-1 rounded-md bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/70 dark:border-indigo-900 text-indigo-800 dark:text-indigo-200 text-[11px] flex items-center justify-between gap-2"
                                    >
                                        <span class="font-medium truncate" :class="{ 'line-through opacity-60': t.status === 'completed' }">
                                            {{ t.title }}
                                        </span>
                                        <span class="font-mono text-[10px] shrink-0">
                                            {{ t.scheduled_start_time }}<template v-if="t.scheduled_end_time">–{{ t.scheduled_end_time }}</template>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fixed Commitments (Calendar Events on this Date) -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                                <Clock class="w-4 h-4" />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Fixed Commitments</h3>
                                <p class="text-[11px] text-neutral-400">Lectures, meetings & scheduled events</p>
                            </div>
                        </div>

                        <div v-if="fixedCommitments.length === 0" class="text-center py-4 text-xs text-neutral-400">
                            No scheduled calendar events for this day.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="evt in fixedCommitments"
                                :key="evt.id"
                                class="p-2.5 rounded-xl border border-neutral-100 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/30 text-xs"
                            >
                                <p class="font-semibold text-neutral-900 dark:text-white">{{ evt.title }}</p>
                                <p class="text-[11px] text-neutral-500 mt-0.5">
                                    {{ evt.start_time ? new Date(evt.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }} -
                                    {{ evt.end_time ? new Date(evt.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }}
                                </p>
                                <p v-if="evt.location" class="text-[10px] text-neutral-400 mt-0.5">
                                    Location: {{ evt.location }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Fallback Plan & Recovery Time -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 space-y-4">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                                <ShieldAlert class="w-4 h-4" />
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Fallback & Recovery Plan</h3>
                                <p class="text-[11px] text-neutral-400">Realistic plans when energy is low</p>
                            </div>
                        </div>

                        <form @submit.prevent="savePlanDetails" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    Fallback Plan (If Day Gets Overwhelmed)
                                </label>
                                <textarea
                                    v-model="planForm.fallback_plan"
                                    rows="2"
                                    placeholder="e.g. Focus only on priority #1 and postpone lab schematics."
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                                    Personal & Recovery Time
                                </label>
                                <textarea
                                    v-model="planForm.personal_recovery_notes"
                                    rows="2"
                                    placeholder="e.g. Walk around the lake at 5 PM, quiet reading before bed."
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                ></textarea>
                            </div>

                            <button
                                type="submit"
                                :disabled="planForm.processing"
                                class="w-full py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition shadow-xs disabled:opacity-50"
                            >
                                Save Daily Notes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reschedule Modal -->
            <div
                v-if="reschedulingTaskId"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                @click.self="reschedulingTaskId = null"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-xl p-5 max-w-sm w-full border border-neutral-200 dark:border-neutral-800 shadow-xl space-y-4">
                    <h4 class="text-sm font-semibold text-neutral-900 dark:text-white">Reschedule Task</h4>
                    <p class="text-xs text-neutral-400">Pick a new target due date for this task:</p>
                    <input
                        type="date"
                        v-model="rescheduleDate"
                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                    />
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            @click="reschedulingTaskId = null"
                            class="px-3 py-1.5 text-xs text-neutral-600 hover:bg-neutral-100 rounded"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitReschedule(unplannedDueTasks.find((t) => t.id === reschedulingTaskId)!)"
                            class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded font-medium hover:bg-indigo-700"
                        >
                            Confirm Date
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
