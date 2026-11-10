<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WeekdaySelector from '@/Components/WeekdaySelector.vue';
import { formatDateWithDay, formatTime12Hour } from '@/utils/date';
import { PageProps, Project, ProjectMilestone, Task, TaskReminderPreset } from '@/types';
import { useFocusTimer } from '@/composables/useFocusTimer';
import {
    ChevronLeft,
    Plus,
    Check,
    Trash2,
    Edit3,
    Flag,
    Bell,
    Timer,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
}>();

const page = usePage<PageProps>();
const { startFocusSession } = useFocusTimer();

// Milestone Management
const isAddMilestoneOpen = ref(false);
const milestoneForm = useForm({
    title: '',
    due_date: '',
    due_time: '',
    reminders_enabled: true,
});

const submitMilestone = () => {
    milestoneForm.post(route('projects.milestones.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            isAddMilestoneOpen.value = false;
            milestoneForm.reset();
        },
    });
};

const toggleMilestone = (m: ProjectMilestone) => {
    router.post(route('milestones.toggle', m.id), {}, { preserveScroll: true });
};

const deleteMilestone = (m: ProjectMilestone) => {
    if (confirm(`Remove milestone "${m.title}"?`)) {
        router.delete(route('milestones.destroy', m.id), { preserveScroll: true });
    }
};

// Task Operations in Project
const toggleTask = (task: Task) => {
    router.post(route('tasks.toggle', task.id), {}, { preserveScroll: true });
};

const launchFocus = (task: Task) => {
    void startFocusSession({
        mode: 'focus',
        planned_minutes: task.estimated_duration || page.props.auth.user?.setting?.focus_work_minutes || 25,
        task_id: task.id,
    });
};

// Add Task Directly to Project
const isAddTaskOpen = ref(false);
const taskForm = useForm({
    title: '',
    category_id: props.project.category_id || '',
    project_id: props.project.id,
    priority: (page.props.auth.user?.setting?.default_priority || 'medium') as 'high' | 'medium' | 'low',
    status: 'not_started' as Task['status'],
    due_date: '',
    due_time: '',
    reminder_preset: (page.props.auth.user?.setting?.default_task_reminder_preset || 'none') as TaskReminderPreset,
    estimated_duration: page.props.auth.user?.setting?.default_task_duration || 45,
});

const submitTask = () => {
    taskForm.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isAddTaskOpen.value = false;
            taskForm.reset();
        },
    });
};

// Edit Project Metadata & Reminders
const isEditProjectOpen = ref(false);
const reminderDayOptions = [
    { days: 7, label: '7 days before' },
    { days: 3, label: '3 days before' },
    { days: 1, label: '1 day before' },
    { days: 0, label: 'On target day' },
];

const projectForm = useForm({
    name: props.project.name,
    category_id: props.project.category_id || '',
    status: props.project.status,
    description: props.project.description || '',
    start_date: props.project.start_date || '',
    target_date: props.project.target_date || '',
    reminders_enabled: props.project.reminders_enabled ?? true,
    reminder_days: Array.isArray(props.project.reminder_days) && props.project.reminder_days.length > 0
        ? [...props.project.reminder_days]
        : [...(page.props.auth.user?.setting?.project_reminder_days || [7, 3, 1, 0])],
    overdue_reminders_enabled: props.project.overdue_reminders_enabled ?? true,
    notes: props.project.notes || '',
});

const toggleProjectReminderDay = (day: number) => {
    const idx = projectForm.reminder_days.indexOf(day);
    if (idx >= 0) {
        projectForm.reminder_days.splice(idx, 1);
    } else {
        projectForm.reminder_days.push(day);
    }
};

const updateProject = () => {
    projectForm.put(route('projects.update', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditProjectOpen.value = false;
        },
    });
};

const deleteProject = () => {
    if (confirm(`Are you sure you want to delete project "${props.project.name}"? Tasks will remain unassigned.`)) {
        router.delete(route('projects.destroy', props.project.id));
    }
};
</script>

<template>
    <AuthenticatedLayout :title="project.name">
        <Head :title="project.name" />

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Back to Projects & Actions -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('projects.index')"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-neutral-900 dark:hover:text-white transition"
                >
                    <ChevronLeft class="w-4 h-4" />
                    <span>Back to Projects</span>
                </Link>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="isEditProjectOpen = true"
                        class="p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        title="Edit Project"
                    >
                        <Edit3 class="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        @click="deleteProject"
                        class="p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 text-neutral-400 transition"
                        title="Delete Project"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Project Card Header -->
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span v-if="project.category" class="text-xs font-semibold px-2 py-0.5 rounded" :style="{ backgroundColor: (project.category.color || '#6366f1') + '15', color: project.category.color || '#6366f1' }">
                                {{ project.category.name }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ project.status.replace('_', ' ') }}
                            </span>
                            <span v-if="project.target_date" class="inline-flex items-center gap-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                <Bell v-if="project.reminders_enabled" class="w-3 h-3 text-amber-500" />
                                Target: {{ project.target_date }}
                            </span>
                        </div>
                        <h2 class="text-xl font-bold text-neutral-900 dark:text-white">{{ project.name }}</h2>
                    </div>

                    <div class="text-right">
                        <div class="text-2xl font-bold text-neutral-900 dark:text-white">{{ project.progress_percent }}%</div>
                        <div class="text-xs text-neutral-400">
                            {{ project.completed_tasks_count }} of {{ project.total_tasks_count }} tasks done
                        </div>
                    </div>
                </div>

                <div class="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-2 overflow-hidden">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="{ width: `${project.progress_percent}%` }"></div>
                </div>

                <p v-if="project.description" class="text-xs text-neutral-600 dark:text-neutral-400">
                    {{ project.description }}
                </p>

                <div v-if="project.notes" class="p-3 bg-neutral-50 dark:bg-neutral-800/40 rounded-xl text-xs text-neutral-600 dark:text-neutral-300 border border-neutral-100 dark:border-neutral-800">
                    <span class="font-semibold text-neutral-800 dark:text-white block mb-0.5">Notes:</span>
                    {{ project.notes }}
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Cols: Project Tasks -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">
                                    Project Tasks ({{ project.tasks?.length || 0 }})
                                </h3>
                                <p class="text-[11px] text-neutral-400">Deliverables and action items</p>
                            </div>
                            <button
                                type="button"
                                @click="isAddTaskOpen = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Task</span>
                            </button>
                        </div>

                        <div v-if="!project.tasks || project.tasks.length === 0" class="text-center py-8 text-neutral-400 text-xs">
                            No tasks associated with this project yet.
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="task in project.tasks"
                                :key="task.id"
                                class="flex items-center justify-between p-3 rounded-xl border border-neutral-100 dark:border-neutral-800 hover:border-neutral-200 dark:hover:border-neutral-700 text-xs transition"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <button
                                        type="button"
                                        @click="toggleTask(task)"
                                        class="w-4 h-4 rounded border flex items-center justify-center transition shrink-0"
                                        :class="task.status === 'completed' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-neutral-300 dark:border-neutral-600'"
                                    >
                                        <Check v-if="task.status === 'completed'" class="w-3 h-3" />
                                    </button>
                                    <span
                                        class="font-medium text-neutral-900 dark:text-white truncate"
                                        :class="{ 'line-through text-neutral-400': task.status === 'completed' }"
                                    >
                                        {{ task.title }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        v-if="task.status !== 'completed'"
                                        type="button"
                                        @click="launchFocus(task)"
                                        class="p-1 rounded text-neutral-400 hover:text-indigo-600 transition"
                                        title="Start Focus Timer"
                                    >
                                        <Timer class="w-3.5 h-3.5" />
                                    </button>
                                    <span
                                        class="px-1.5 py-0.5 rounded text-[9px] uppercase font-bold"
                                        :class="task.priority === 'high' ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/40' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'"
                                    >
                                        {{ task.priority }}
                                    </span>
                                    <span v-if="task.due_date" class="text-[10px] text-neutral-400 font-mono">
                                        {{ formatDateWithDay(task.due_date) }}<template v-if="task.due_time"> {{ formatTime12Hour(task.due_time) }}</template>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Col: Milestones Timeline -->
                <div class="space-y-4">
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                                    <Flag class="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Milestones</h3>
                                    <p class="text-[11px] text-neutral-400">Critical stage gates</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="isAddMilestoneOpen = true"
                                class="p-1.5 rounded-lg border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300"
                                title="Add Milestone"
                            >
                                <Plus class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div v-if="!project.milestones || project.milestones.length === 0" class="text-center py-6 text-neutral-400 text-xs">
                            No milestones defined.
                        </div>

                        <div v-else class="space-y-3 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-neutral-200 dark:before:bg-neutral-800">
                            <div
                                v-for="m in project.milestones"
                                :key="m.id"
                                class="relative flex items-start gap-3 pl-1 text-xs"
                            >
                                <button
                                    type="button"
                                    @click="toggleMilestone(m)"
                                    class="w-5 h-5 rounded-full border-2 bg-white dark:bg-neutral-900 flex items-center justify-center shrink-0 z-10 transition"
                                    :class="m.is_completed ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-neutral-300 dark:border-neutral-600'"
                                >
                                    <Check v-if="m.is_completed" class="w-3 h-3 text-white" />
                                </button>

                                <div class="flex-1 bg-neutral-50/50 dark:bg-neutral-900/50 p-2.5 rounded-xl border border-neutral-100 dark:border-neutral-800">
                                    <div class="flex items-center justify-between">
                                        <p
                                            class="font-semibold text-neutral-900 dark:text-white"
                                            :class="{ 'line-through text-neutral-400': m.is_completed }"
                                        >
                                            {{ m.title }}
                                        </p>
                                        <button
                                            type="button"
                                            @click="deleteMilestone(m)"
                                            class="text-neutral-400 hover:text-rose-500"
                                        >
                                            <Trash2 class="w-3 h-3" />
                                        </button>
                                    </div>
                                    <p v-if="m.due_date" class="text-[10px] text-neutral-400 mt-0.5 flex items-center gap-1">
                                        <Bell v-if="m.reminders_enabled" class="w-2.5 h-2.5 text-amber-500" />
                                        <span>Target: {{ formatDateWithDay(m.due_date) }}<template v-if="m.due_time"> {{ formatTime12Hour(m.due_time) }}</template></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Project Modal -->
            <div
                v-if="isEditProjectOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isEditProjectOpen = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">Edit Project</h3>
                        <button type="button" @click="isEditProjectOpen = false" class="p-1 text-neutral-400 hover:text-neutral-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="updateProject" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-medium mb-1">Project Name *</label>
                            <input
                                v-model="projectForm.name"
                                type="text"
                                required
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium mb-1">Area / Category</label>
                                <select
                                    v-model="projectForm.category_id"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="">None</option>
                                    <option v-for="cat in $page.props.categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-medium mb-1">Status</label>
                                <select
                                    v-model="projectForm.status"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="planned">Planned</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="completed">Completed</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Description</label>
                            <textarea
                                v-model="projectForm.description"
                                rows="2"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium mb-1">Start Date</label>
                                <input
                                    v-model="projectForm.start_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-medium mb-1">Target Date</label>
                                <input
                                    v-model="projectForm.target_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <!-- Deadline Reminders -->
                        <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-2.5">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-neutral-800 dark:text-neutral-200">
                                <input
                                    type="checkbox"
                                    v-model="projectForm.reminders_enabled"
                                    class="rounded text-indigo-600 border-neutral-300"
                                />
                                <Bell class="w-3.5 h-3.5 text-indigo-500" />
                                <span>Project Deadline Reminders</span>
                            </label>

                            <div v-if="projectForm.reminders_enabled" class="space-y-2 pt-1">
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="opt in reminderDayOptions"
                                        :key="opt.days"
                                        type="button"
                                        @click="toggleProjectReminderDay(opt.days)"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-medium border transition"
                                        :class="
                                            projectForm.reminder_days.includes(opt.days)
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : 'bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
                                        "
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>

                                <label class="inline-flex items-center gap-2 cursor-pointer text-[11px] text-neutral-600 dark:text-neutral-400">
                                    <input
                                        type="checkbox"
                                        v-model="projectForm.overdue_reminders_enabled"
                                        class="rounded text-indigo-600 border-neutral-300"
                                    />
                                    <span>Send overdue project alert if past target date</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Notes</label>
                            <textarea
                                v-model="projectForm.notes"
                                rows="2"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="isEditProjectOpen = false"
                                class="px-4 py-2 rounded-lg text-neutral-600 hover:bg-neutral-100"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="projectForm.processing"
                                class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium"
                            >
                                Update Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Milestone Modal -->
            <div
                v-if="isAddMilestoneOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isAddMilestoneOpen = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-xl p-5 max-w-sm w-full border border-neutral-200 dark:border-neutral-800 shadow-xl space-y-4">
                    <h4 class="text-sm font-semibold text-neutral-900 dark:text-white">Add Milestone</h4>
                    <form @submit.prevent="submitMilestone" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium mb-1">Milestone Title *</label>
                            <input
                                v-model="milestoneForm.title"
                                type="text"
                                required
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-medium mb-1">Target Due Date</label>
                                <input
                                    v-model="milestoneForm.due_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-medium mb-1">Due Time</label>
                                <input
                                    v-model="milestoneForm.due_time"
                                    type="time"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-neutral-700 dark:text-neutral-300">
                            <input
                                type="checkbox"
                                v-model="milestoneForm.reminders_enabled"
                                class="rounded text-indigo-600 border-neutral-300"
                            />
                            <span>Enable milestone deadline reminders</span>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="isAddMilestoneOpen = false"
                                class="px-3 py-1.5 text-neutral-600 hover:bg-neutral-100 rounded"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-3 py-1.5 bg-indigo-600 text-white rounded font-medium hover:bg-indigo-700"
                            >
                                Add Milestone
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Task Modal -->
            <div
                v-if="isAddTaskOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isAddTaskOpen = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-xl p-5 max-w-sm w-full border border-neutral-200 dark:border-neutral-800 shadow-xl space-y-4">
                    <h4 class="text-sm font-semibold text-neutral-900 dark:text-white">Add Task to Project</h4>
                    <form @submit.prevent="submitTask" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium mb-1">Task Title *</label>
                            <input
                                v-model="taskForm.title"
                                type="text"
                                required
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-medium mb-1">Priority</label>
                                <select
                                    v-model="taskForm.priority"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-medium mb-1">Est. Mins</label>
                                <input
                                    v-model.number="taskForm.estimated_duration"
                                    type="number"
                                    min="5"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>
                        <!-- Due Date with Weekday Auto-Calculator -->
                        <div class="p-2.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800 space-y-2.5">
                            <WeekdaySelector
                                v-model="taskForm.due_date"
                                label="Due Date (Pick day in current week)"
                            />
                            <div class="pt-2 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-2 flex-wrap">
                                <label class="block font-medium text-xs">Due Time (Optional)</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="taskForm.due_time"
                                        type="time"
                                        class="text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white py-1"
                                    />
                                    <span v-if="taskForm.due_time" class="text-[11px] font-mono text-neutral-500">
                                        {{ formatTime12Hour(taskForm.due_time) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Reminder</label>
                            <select
                                v-model="taskForm.reminder_preset"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="none">None</option>
                                <option value="at_due">At due time</option>
                                <option value="15m">15m before</option>
                                <option value="30m">30m before</option>
                                <option value="1h">1h before</option>
                                <option value="1d">1d before</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="isAddTaskOpen = false"
                                class="px-3 py-1.5 text-neutral-600 hover:bg-neutral-100 rounded"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-3 py-1.5 bg-indigo-600 text-white rounded font-medium hover:bg-indigo-700"
                            >
                                Create Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
