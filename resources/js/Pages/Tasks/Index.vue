<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Category, Project, Tag, Task, TaskChecklist } from '@/types';
import {
    Search,
    Filter,
    Plus,
    CheckSquare,
    Check,
    Clock,
    AlertCircle,
    Calendar,
    ChevronDown,
    MoreHorizontal,
    Trash2,
    Copy,
    Edit3,
    Tag as TagIcon,
    FolderKanban,
    ListCheck,
    Repeat,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    tasks: {
        data: Task[];
        links: any[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        status?: string;
        category_id?: string;
        project_id?: string;
        priority?: string;
        date_filter?: string;
        sort_by?: string;
        sort_dir?: string;
    };
    projects: Project[];
    tags: Tag[];
}>();

// Filter State
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const categoryId = ref(props.filters.category_id || '');
const projectId = ref(props.filters.project_id || '');
const priority = ref(props.filters.priority || '');
const dateFilter = ref(props.filters.date_filter || '');
const sortBy = ref(props.filters.sort_by || 'due_date');
const sortDir = ref(props.filters.sort_dir || 'asc');

const applyFilters = () => {
    router.get(
        route('tasks.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            category_id: categoryId.value || undefined,
            project_id: projectId.value || undefined,
            priority: priority.value || undefined,
            date_filter: dateFilter.value || undefined,
            sort_by: sortBy.value || undefined,
            sort_dir: sortDir.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    status.value = '';
    categoryId.value = '';
    projectId.value = '';
    priority.value = '';
    dateFilter.value = '';
    sortBy.value = 'due_date';
    sortDir.value = 'asc';
    applyFilters();
};

// Selection for Bulk Actions
const selectedTaskIds = ref<number[]>([]);
const selectAll = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedTaskIds.value = props.tasks.data.map((t) => t.id);
    } else {
        selectedTaskIds.value = [];
    }
};

const executeBulkAction = (action: string, payload: Record<string, any> = {}) => {
    if (selectedTaskIds.value.length === 0) return;
    router.post(
        route('tasks.bulk'),
        {
            task_ids: selectedTaskIds.value,
            action,
            ...payload,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedTaskIds.value = [];
                selectAll.value = false;
            },
        }
    );
};

// Toggle Completion
const toggleTask = (task: Task) => {
    router.post(route('tasks.toggle', task.id), {}, { preserveScroll: true });
};

// Duplicate Task
const duplicateTask = (task: Task) => {
    router.post(route('tasks.duplicate', task.id), {}, { preserveScroll: true });
};

// Delete Task
const deleteTask = (task: Task) => {
    if (confirm(`Are you sure you want to delete "${task.title}"?`)) {
        router.delete(route('tasks.destroy', task.id), { preserveScroll: true });
    }
};

// Edit / Detail Modal
const activeTask = ref<Task | null>(null);
const isEditing = ref(false);
const newChecklistTitle = ref('');

const taskForm = useForm({
    title: '',
    description: '',
    category_id: '' as string | number,
    project_id: '' as string | number,
    priority: 'medium' as 'high' | 'medium' | 'low',
    status: 'not_started' as any,
    due_date: '',
    start_date: '',
    estimated_duration: null as number | null,
    actual_duration: null as number | null,
    notes: '',
    next_action: '',
    is_recurring: false,
    recurrence_rule: '',
    tag_ids: [] as number[],
    checklists: [] as { id?: number; title: string; is_completed?: boolean }[],
});

const openCreateModal = () => {
    activeTask.value = null;
    taskForm.reset();
    taskForm.title = '';
    taskForm.priority = 'medium';
    taskForm.status = 'not_started';
    taskForm.category_id = '';
    taskForm.project_id = '';
    taskForm.tag_ids = [];
    taskForm.checklists = [];
    isEditing.value = true;
};

const openEditModal = (task: Task) => {
    activeTask.value = task;
    taskForm.title = task.title;
    taskForm.description = task.description || '';
    taskForm.category_id = task.category_id || '';
    taskForm.project_id = task.project_id || '';
    taskForm.priority = task.priority;
    taskForm.status = task.status;
    taskForm.due_date = task.due_date || '';
    taskForm.start_date = task.start_date || '';
    taskForm.estimated_duration = task.estimated_duration;
    taskForm.actual_duration = task.actual_duration;
    taskForm.notes = task.notes || '';
    taskForm.next_action = task.next_action || '';
    taskForm.is_recurring = task.is_recurring;
    taskForm.recurrence_rule = task.recurrence_rule || '';
    taskForm.tag_ids = task.tags?.map((t) => t.id) || [];
    taskForm.checklists = task.checklists?.map((c) => ({
        id: c.id,
        title: c.title,
        is_completed: c.is_completed,
    })) || [];
    isEditing.value = true;
};

const addChecklistItem = () => {
    if (!newChecklistTitle.value.trim()) return;
    taskForm.checklists.push({
        title: newChecklistTitle.value.trim(),
        is_completed: false,
    });
    newChecklistTitle.value = '';
};

const removeChecklistItem = (idx: number) => {
    taskForm.checklists.splice(idx, 1);
};

const saveTask = () => {
    if (activeTask.value) {
        taskForm.put(route('tasks.update', activeTask.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                isEditing.value = false;
            },
        });
    } else {
        taskForm.post(route('tasks.store'), {
            preserveScroll: true,
            onSuccess: () => {
                isEditing.value = false;
            },
        });
    }
};
</script>

<template>
    <Head title="Tasks Management" />

    <AuthenticatedLayout title="Tasks">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header with Search & Quick Filters -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-white">All Tasks</h2>
                    <p class="text-xs text-neutral-400">
                        {{ tasks.total }} tasks registered across all areas
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>New Task</span>
                    </button>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <!-- Search Input -->
                    <div class="sm:col-span-2 relative">
                        <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5" />
                        <input
                            v-model="search"
                            @input="applyFilters"
                            type="text"
                            placeholder="Search tasks, descriptions..."
                            class="w-full pl-9 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <select
                            v-model="categoryId"
                            @change="applyFilters"
                            class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Areas</option>
                            <option v-for="cat in $page.props.categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <select
                            v-model="status"
                            @change="applyFilters"
                            class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active (Uncompleted)</option>
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="waiting">Waiting</option>
                            <option value="completed">Completed</option>
                            <option value="deferred">Deferred</option>
                        </select>
                    </div>

                    <!-- Date View Filter -->
                    <div>
                        <select
                            v-model="dateFilter"
                            @change="applyFilters"
                            class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Any Due Date</option>
                            <option value="today">Due Today</option>
                            <option value="overdue">Overdue</option>
                            <option value="upcoming">Next 7 Days</option>
                            <option value="unscheduled">Unscheduled</option>
                        </select>
                    </div>

                    <!-- Sort -->
                    <div>
                        <select
                            v-model="sortBy"
                            @change="applyFilters"
                            class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="due_date">Sort by Due Date</option>
                            <option value="priority">Sort by Priority</option>
                            <option value="title">Sort by Title</option>
                            <option value="created_at">Sort by Created</option>
                        </select>
                    </div>
                </div>

                <!-- Reset Filters Option -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <span class="text-neutral-400">Showing page {{ tasks.current_page }} of {{ tasks.last_page }}</span>
                    <button
                        @click="resetFilters"
                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        Reset filters
                    </button>
                </div>
            </div>

            <!-- Bulk Actions Bar (if any selected) -->
            <div
                v-if="selectedTaskIds.length > 0"
                class="flex items-center justify-between bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 px-4 py-2.5 rounded-xl text-xs"
            >
                <span class="font-medium text-indigo-900 dark:text-indigo-200">
                    {{ selectedTaskIds.length }} tasks selected
                </span>
                <div class="flex items-center gap-2">
                    <button
                        @click="executeBulkAction('complete')"
                        class="px-2.5 py-1 bg-white dark:bg-neutral-800 rounded text-neutral-700 dark:text-neutral-300 font-medium hover:bg-neutral-100 shadow-xs"
                    >
                        Mark Completed
                    </button>
                    <button
                        @click="executeBulkAction('reopen')"
                        class="px-2.5 py-1 bg-white dark:bg-neutral-800 rounded text-neutral-700 dark:text-neutral-300 font-medium hover:bg-neutral-100 shadow-xs"
                    >
                        Reopen
                    </button>
                    <button
                        @click="executeBulkAction('delete')"
                        class="px-2.5 py-1 bg-rose-600 text-white rounded font-medium hover:bg-rose-700 shadow-xs"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <!-- Tasks Table / List View -->
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-neutral-50 dark:bg-neutral-800/50 text-neutral-500 uppercase tracking-wider text-[10px] border-b border-neutral-200 dark:border-neutral-800">
                            <tr>
                                <th class="p-3 w-8">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </th>
                                <th class="p-3 w-8"></th>
                                <th class="p-3">Title</th>
                                <th class="p-3">Area</th>
                                <th class="p-3">Project</th>
                                <th class="p-3">Priority</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Due Date</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            <tr v-if="tasks.data.length === 0">
                                <td colspan="9" class="p-8 text-center text-neutral-400">
                                    No tasks match your filter criteria.
                                </td>
                            </tr>
                            <tr
                                v-for="task in tasks.data"
                                :key="task.id"
                                class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition group"
                            >
                                <!-- Checkbox for selection -->
                                <td class="p-3">
                                    <input
                                        type="checkbox"
                                        :value="task.id"
                                        v-model="selectedTaskIds"
                                        class="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </td>

                                <!-- Status Toggle Checkbox -->
                                <td class="p-3">
                                    <button
                                        @click="toggleTask(task)"
                                        class="w-4 h-4 rounded border flex items-center justify-center transition"
                                        :class="task.status === 'completed' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-neutral-300 dark:border-neutral-600 hover:border-indigo-500'"
                                    >
                                        <Check v-if="task.status === 'completed'" class="w-3 h-3" />
                                    </button>
                                </td>

                                <!-- Task Title, Next Action & Checklists -->
                                <td class="p-3 max-w-xs sm:max-w-md">
                                    <div
                                        @click="openEditModal(task)"
                                        class="font-medium text-neutral-900 dark:text-white cursor-pointer hover:text-indigo-600 transition"
                                        :class="{ 'line-through text-neutral-400 dark:text-neutral-500': task.status === 'completed' }"
                                    >
                                        {{ task.title }}
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5 text-[11px] text-neutral-400 flex-wrap">
                                        <span v-if="task.next_action" class="italic text-neutral-500 dark:text-neutral-400">
                                            &rarr; {{ task.next_action }}
                                        </span>
                                        <span v-if="task.checklists?.length" class="inline-flex items-center gap-1 text-[10px]">
                                            <ListCheck class="w-3 h-3" />
                                            {{ task.checklists.filter(c => c.is_completed).length }}/{{ task.checklists.length }}
                                        </span>
                                        <span v-if="task.is_recurring" class="inline-flex items-center gap-0.5 text-[10px] text-indigo-500">
                                            <Repeat class="w-3 h-3" /> Recurring
                                        </span>
                                        <span v-for="tag in task.tags" :key="tag.id" class="px-1.5 py-0.2 rounded text-[9px] bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300">
                                            #{{ tag.name }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Area / Category -->
                                <td class="p-3 whitespace-nowrap">
                                    <span
                                        v-if="task.category"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-medium"
                                        :style="{ backgroundColor: (task.category.color || '#6366f1') + '15', color: task.category.color || '#6366f1' }"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: task.category.color || '#6366f1' }"></span>
                                        {{ task.category.name }}
                                    </span>
                                    <span v-else class="text-neutral-400 text-[11px]">-</span>
                                </td>

                                <!-- Project -->
                                <td class="p-3 whitespace-nowrap">
                                    <span v-if="task.project" class="text-neutral-700 dark:text-neutral-300 font-medium">
                                        {{ task.project.name }}
                                    </span>
                                    <span v-else class="text-neutral-400">-</span>
                                </td>

                                <!-- Priority -->
                                <td class="p-3 whitespace-nowrap">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider"
                                        :class="{
                                            'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400': task.priority === 'high',
                                            'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400': task.priority === 'medium',
                                            'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400': task.priority === 'low',
                                        }"
                                    >
                                        {{ task.priority }}
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="p-3 whitespace-nowrap capitalize text-neutral-600 dark:text-neutral-400">
                                    {{ task.status.replace('_', ' ') }}
                                </td>

                                <!-- Due Date -->
                                <td class="p-3 whitespace-nowrap">
                                    <span v-if="task.due_date" class="font-mono text-[11px] text-neutral-700 dark:text-neutral-300">
                                        {{ task.due_date }}
                                    </span>
                                    <span v-else class="text-neutral-400">-</span>
                                </td>

                                <!-- Row Actions -->
                                <td class="p-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition">
                                        <button
                                            @click="openEditModal(task)"
                                            class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                            title="Edit task"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            @click="duplicateTask(task)"
                                            class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                            title="Duplicate task"
                                        >
                                            <Copy class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            @click="deleteTask(task)"
                                            class="p-1 rounded text-neutral-400 hover:text-rose-500"
                                            title="Delete task"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="tasks.last_page > 1" class="p-3 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-xs">
                    <span class="text-neutral-400">Total: {{ tasks.total }}</span>
                    <div class="flex gap-1">
                        <button
                            v-for="(link, i) in tasks.links"
                            :key="i"
                            :disabled="!link.url"
                            @click="router.get(link.url)"
                            class="px-2.5 py-1 rounded text-xs transition"
                            :class="link.active ? 'bg-indigo-600 text-white font-medium' : 'bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 disabled:opacity-40'"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>

            <!-- Task Edit / Create Full Modal -->
            <div
                v-if="isEditing"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isEditing = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">
                            {{ activeTask ? 'Edit Task' : 'Create Task' }}
                        </h3>
                        <button @click="isEditing = false" class="p-1 text-neutral-400 hover:text-neutral-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="saveTask" class="space-y-4 text-xs">
                        <!-- Title -->
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Title *</label>
                            <input
                                v-model="taskForm.title"
                                type="text"
                                required
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Description</label>
                            <textarea
                                v-model="taskForm.description"
                                rows="2"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Category -->
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Area / Category</label>
                                <select
                                    v-model="taskForm.category_id"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="">None</option>
                                    <option v-for="cat in $page.props.categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Project -->
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Project</label>
                                <select
                                    v-model="taskForm.project_id"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="">None</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- Priority -->
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Priority</label>
                                <select
                                    v-model="taskForm.priority"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Status</label>
                                <select
                                    v-model="taskForm.status"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="not_started">Not Started</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="waiting">Waiting</option>
                                    <option value="completed">Completed</option>
                                    <option value="deferred">Deferred</option>
                                </select>
                            </div>

                            <!-- Due Date -->
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Due Date</label>
                                <input
                                    v-model="taskForm.due_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Start Date</label>
                                <input
                                    v-model="taskForm.start_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Est. Duration (min)</label>
                                <input
                                    v-model.number="taskForm.estimated_duration"
                                    type="number"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Actual Duration (min)</label>
                                <input
                                    v-model.number="taskForm.actual_duration"
                                    type="number"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <!-- Next Action -->
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Next Immediate Action</label>
                            <input
                                v-model="taskForm.next_action"
                                type="text"
                                placeholder="e.g. Check solder connection on pin 4"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <!-- Subtasks / Checklists -->
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Subtasks / Checklist</label>
                            <div class="space-y-1.5 mb-2">
                                <div
                                    v-for="(cl, idx) in taskForm.checklists"
                                    :key="idx"
                                    class="flex items-center gap-2 bg-neutral-50 dark:bg-neutral-800 p-2 rounded-lg"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="cl.is_completed"
                                        class="rounded text-indigo-600 border-neutral-300"
                                    />
                                    <input
                                        type="text"
                                        v-model="cl.title"
                                        class="flex-1 bg-transparent border-0 text-xs text-neutral-900 dark:text-white focus:ring-0 p-0"
                                    />
                                    <button
                                        type="button"
                                        @click="removeChecklistItem(idx)"
                                        class="text-neutral-400 hover:text-rose-500"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    v-model="newChecklistTitle"
                                    @keydown.enter.prevent="addChecklistItem"
                                    type="text"
                                    placeholder="Add new subtask item and press Enter"
                                    class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                                <button
                                    type="button"
                                    @click="addChecklistItem"
                                    class="px-3 py-1.5 bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 rounded-lg text-neutral-700 dark:text-neutral-300 font-medium"
                                >
                                    Add
                                </button>
                            </div>
                        </div>

                        <!-- Recurrence -->
                        <div class="pt-2 border-t border-neutral-100 dark:border-neutral-800 flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="taskForm.is_recurring"
                                    class="rounded text-indigo-600 border-neutral-300"
                                />
                                <span class="font-medium text-neutral-700 dark:text-neutral-300">Recurring Task</span>
                            </label>

                            <select
                                v-if="taskForm.is_recurring"
                                v-model="taskForm.recurrence_rule"
                                class="text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex justify-end gap-2 pt-4 border-t border-neutral-100 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="isEditing = false"
                                class="px-4 py-2 rounded-lg text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="taskForm.processing"
                                class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-sm transition disabled:opacity-50"
                            >
                                Save Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

