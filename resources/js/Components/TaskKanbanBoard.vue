<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PageProps, Task } from '@/types';
import { formatDateWithDay, formatTime12Hour, isOverdueLocal } from '@/utils/date';
import { useFocusTimer } from '@/composables/useFocusTimer';
import {
    Check,
    Calendar,
    Clock,
    FolderKanban,
    Timer,
    Repeat,
    Edit3,
    AlertCircle,
    Lock,
} from 'lucide-vue-next';

const incompleteBlockers = (task: Task) =>
    (task.blocked_by_tasks || []).filter((b) => b.status !== 'completed');

const props = defineProps<{
    tasks: Task[];
}>();

const emit = defineEmits<{
    (e: 'edit-task', task: Task): void;
    (e: 'toggle-task', task: Task): void;
}>();

const page = usePage<PageProps>();
const { startFocusSession } = useFocusTimer();

const draggingTaskId = ref<number | null>(null);
const dragOverColumn = ref<string | null>(null);

const columns: {
    key: Task['status'];
    title: string;
    accent: string;
}[] = [
    { key: 'not_started', title: 'Not Started', accent: 'bg-neutral-400' },
    { key: 'in_progress', title: 'In Progress', accent: 'bg-indigo-500' },
    { key: 'waiting', title: 'Waiting / Deferred', accent: 'bg-amber-500' },
    { key: 'completed', title: 'Completed', accent: 'bg-emerald-500' },
];

const tasksByColumn = computed(() => {
    const grouped: Record<string, Task[]> = {
        not_started: [],
        in_progress: [],
        waiting: [],
        completed: [],
    };

    for (const task of props.tasks) {
        if (task.status === 'deferred' || task.status === 'waiting') {
            grouped.waiting.push(task);
        } else if (grouped[task.status]) {
            grouped[task.status].push(task);
        } else {
            grouped.not_started.push(task);
        }
    }

    return grouped;
});

const onDragStart = (e: DragEvent, task: Task) => {
    draggingTaskId.value = task.id;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(task.id));
    }
};

const onDragEnd = () => {
    draggingTaskId.value = null;
    dragOverColumn.value = null;
};

const onDropColumn = (targetStatus: Task['status']) => {
    const taskId = draggingTaskId.value;
    dragOverColumn.value = null;
    draggingTaskId.value = null;

    if (!taskId) {
        return;
    }

    const task = props.tasks.find((t) => t.id === taskId);
    if (!task || task.status === targetStatus) {
        return;
    }

    moveTaskStatus(task, targetStatus);
};

const moveTaskStatus = (task: Task, targetStatus: Task['status']) => {
    router.patch(
        route('tasks.update-status', task.id),
        { status: targetStatus },
        { preserveScroll: true }
    );
};

const launchTaskFocus = (task: Task) => {
    void startFocusSession({
        mode: 'focus',
        planned_minutes: task.estimated_duration || page.props.auth.user?.setting?.focus_work_minutes || 25,
        task_id: task.id,
    });
};

const priorityBadgeClass = (p: string) => {
    if (p === 'high') {
        return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900';
    }
    if (p === 'medium') {
        return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900';
    }
    return 'bg-neutral-100 text-neutral-600 border-neutral-200 dark:bg-neutral-800 dark:text-neutral-400 dark:border-neutral-700';
};
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
        <div
            v-for="col in columns"
            :key="col.key"
            @dragover.prevent="dragOverColumn = col.key"
            @dragleave="dragOverColumn === col.key ? (dragOverColumn = null) : null"
            @drop.prevent="onDropColumn(col.key)"
            class="rounded-2xl border transition-colors p-3 flex flex-col min-h-[26rem]"
            :class="
                dragOverColumn === col.key
                    ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/30'
                    : 'border-neutral-200 dark:border-neutral-800 bg-neutral-100/70 dark:bg-neutral-900/60'
            "
        >
            <!-- Column Header -->
            <div class="flex items-center justify-between px-1 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full" :class="col.accent"></span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-200">
                        {{ col.title }}
                    </h3>
                </div>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 shadow-sm">
                    {{ tasksByColumn[col.key]?.length || 0 }}
                </span>
            </div>

            <!-- Cards -->
            <div class="space-y-2.5 flex-1">
                <div
                    v-if="(tasksByColumn[col.key]?.length || 0) === 0"
                    class="py-10 px-3 text-center rounded-xl border border-dashed border-neutral-300 dark:border-neutral-800 text-xs text-neutral-400"
                >
                    Drop tasks here
                </div>

                <div
                    v-for="task in tasksByColumn[col.key]"
                    :key="task.id"
                    draggable="true"
                    @dragstart="onDragStart($event, task)"
                    @dragend="onDragEnd"
                    class="group rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 p-3 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-700 transition cursor-grab active:cursor-grabbing"
                    :class="draggingTaskId === task.id ? 'opacity-50' : ''"
                >
                    <!-- Top Row: Checkbox + Priority + Actions -->
                    <div class="flex items-start gap-2">
                        <button
                            type="button"
                            @click.stop="emit('toggle-task', task)"
                            class="mt-0.5 w-4 h-4 rounded border flex items-center justify-center shrink-0 transition"
                            :class="
                                task.status === 'completed'
                                    ? 'bg-emerald-500 border-emerald-500 text-white'
                                    : 'border-neutral-300 dark:border-neutral-600 hover:border-indigo-500'
                            "
                            title="Toggle completion"
                        >
                            <Check v-if="task.status === 'completed'" class="w-3 h-3" />
                        </button>

                        <button
                            type="button"
                            @click="emit('edit-task', task)"
                            class="flex-1 text-left text-xs font-semibold leading-snug hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                            :class="
                                task.status === 'completed'
                                    ? 'line-through text-neutral-400 dark:text-neutral-500'
                                    : 'text-neutral-900 dark:text-neutral-100'
                            "
                        >
                            {{ task.title }}
                        </button>

                        <span
                            class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded border shrink-0"
                            :class="priorityBadgeClass(task.priority)"
                        >
                            {{ task.priority }}
                        </span>
                    </div>

                    <!-- Category & Project -->
                    <div
                        v-if="task.category || task.project || task.is_recurring || incompleteBlockers(task).length > 0"
                        class="flex flex-wrap items-center gap-1.5 mt-2"
                    >
                        <span
                            v-if="task.category"
                            class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: task.category.color }"></span>
                            {{ task.category.name }}
                        </span>

                        <span
                            v-if="task.project"
                            class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300"
                        >
                            <FolderKanban class="w-2.5 h-2.5" />
                            {{ task.project.name }}
                        </span>

                        <span
                            v-if="task.is_recurring"
                            class="inline-flex items-center gap-0.5 text-[10px] text-purple-600 dark:text-purple-400"
                            title="Recurring task"
                        >
                            <Repeat class="w-3 h-3" />
                        </span>

                        <span
                            v-if="incompleteBlockers(task).length > 0"
                            class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900"
                            :title="'Blocked by: ' + incompleteBlockers(task).map((b) => b.title).join(', ')"
                        >
                            <Lock class="w-2.5 h-2.5" />
                            Blocked ({{ incompleteBlockers(task).length }})
                        </span>
                    </div>

                    <!-- Tags -->
                    <div v-if="task.tags && task.tags.length > 0" class="flex flex-wrap gap-1 mt-2">
                        <span
                            v-for="tag in task.tags"
                            :key="tag.id"
                            class="text-[10px] px-1.5 py-0.5 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400"
                        >
                            #{{ tag.name }}
                        </span>
                    </div>

                    <!-- Footer Metadata & Quick Actions -->
                    <div class="flex items-center justify-between gap-2 mt-3 pt-2 border-t border-neutral-100 dark:border-neutral-800/80 text-[11px]">
                        <div class="flex items-center gap-2 text-neutral-500 dark:text-neutral-400 min-w-0">
                            <span
                                v-if="task.due_date"
                                class="inline-flex items-center gap-1 truncate"
                                :class="
                                    isOverdueLocal(task.due_date, task.status, page.props.auth.user?.setting?.timezone)
                                        ? 'text-rose-600 dark:text-rose-400 font-semibold'
                                        : ''
                                "
                            >
                                <AlertCircle
                                    v-if="isOverdueLocal(task.due_date, task.status, page.props.auth.user?.setting?.timezone)"
                                    class="w-3 h-3 shrink-0"
                                />
                                <Calendar v-else class="w-3 h-3 shrink-0" />
                                {{ formatDateWithDay(task.due_date) }}<template v-if="task.due_time"> {{ formatTime12Hour(task.due_time) }}</template>
                            </span>

                            <span v-if="task.estimated_duration || task.actual_duration" class="inline-flex items-center gap-0.5 shrink-0">
                                <Clock class="w-3 h-3" />
                                <template v-if="task.actual_duration">{{ task.actual_duration }}/</template>{{ task.estimated_duration || 0 }}m
                            </span>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <!-- Touch/Keyboard Accessible Column Switcher -->
                            <select
                                :value="task.status === 'deferred' ? 'waiting' : task.status"
                                @change="moveTaskStatus(task, ($event.target as HTMLSelectElement).value as any)"
                                class="md:hidden text-[10px] py-0.5 pl-1.5 pr-5 rounded border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                                aria-label="Change task status"
                            >
                                <option value="not_started">To Do</option>
                                <option value="in_progress">Doing</option>
                                <option value="waiting">Waiting</option>
                                <option value="completed">Done</option>
                            </select>

                            <button
                                v-if="task.status !== 'completed'"
                                type="button"
                                @click.stop="launchTaskFocus(task)"
                                class="p-1 rounded-md text-neutral-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition"
                                title="Start Focus Timer for this task"
                            >
                                <Timer class="w-3.5 h-3.5" />
                            </button>

                            <button
                                type="button"
                                @click.stop="emit('edit-task', task)"
                                class="p-1 rounded-md text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                title="Edit task"
                            >
                                <Edit3 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

