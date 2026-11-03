<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Project, ProjectMilestone, Task } from '@/types';
import {
    FolderKanban,
    ChevronLeft,
    CheckCircle2,
    Calendar,
    Clock,
    Plus,
    Check,
    Trash2,
    Edit3,
    Flag,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    project: Project;
}>();

// Milestone Management
const isAddMilestoneOpen = ref(false);
const milestoneForm = useForm({
    title: '',
    due_date: '',
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

// Add Task Directly to Project
const isAddTaskOpen = ref(false);
const taskForm = useForm({
    title: '',
    category_id: props.project.category_id || '',
    project_id: props.project.id,
    priority: 'medium' as 'high' | 'medium' | 'low',
    status: 'not_started' as any,
    due_date: '',
    estimated_duration: 45,
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

// Edit Project Metadata
const isEditProjectOpen = ref(false);
const projectForm = useForm({
    name: props.project.name,
    category_id: props.project.category_id || '',
    status: props.project.status,
    description: props.project.description || '',
    start_date: props.project.start_date || '',
    target_date: props.project.target_date || '',
    notes: props.project.notes || '',
});

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
    <Head :title="project.name" />

    <AuthenticatedLayout :title="project.name">
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
                        @click="isEditProjectOpen = true"
                        class="p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        title="Edit Project"
                    >
                        <Edit3 class="w-4 h-4" />
                    </button>
                    <button
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
                        <div class="flex items-center gap-2 mb-1">
                            <span v-if="project.category" class="text-xs font-semibold px-2 py-0.5 rounded" :style="{ backgroundColor: (project.category.color || '#6366f1') + '15', color: project.category.color || '#6366f1' }">
                                {{ project.category.name }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ project.status.replace('_', ' ') }}
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
                                    <span
                                        class="px-1.5 py-0.5 rounded text-[9px] uppercase font-bold"
                                        :class="task.priority === 'high' ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/40' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'"
                                    >
                                        {{ task.priority }}
                                    </span>
                                    <span v-if="task.due_date" class="text-[10px] text-neutral-400 font-mono">
                                        {{ task.due_date }}
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
                                            @click="deleteMilestone(m)"
                                            class="text-neutral-400 hover:text-rose-500"
                                        >
                                            <Trash2 class="w-3 h-3" />
                                        </button>
                                    </div>
                                    <p v-if="m.due_date" class="text-[10px] text-neutral-400 mt-0.5">
                                        Target: {{ m.due_date }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
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
                        <div>
                            <label class="block font-medium mb-1">Target Due Date</label>
                            <input
                                v-model="milestoneForm.due_date"
                                type="date"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
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
                                <label class="block font-medium mb-1">Due Date</label>
                                <input
                                    v-model="taskForm.due_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
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

