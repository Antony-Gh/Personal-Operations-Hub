<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TaskKanbanBoard from '@/Components/TaskKanbanBoard.vue';
import WeekdaySelector from '@/Components/WeekdaySelector.vue';
import { PageProps, Project, Tag, Task, TaskReminderPreset } from '@/types';
import { formatDateWithDay, formatTime12Hour, getLocalDateTimeInputValue, isOverdueLocal } from '@/utils/date';
import { useFocusTimer } from '@/composables/useFocusTimer';
import {
    Search,
    Plus,
    Check,
    Clock,
    AlertCircle,
    Trash2,
    Copy,
    Edit3,
    ListCheck,
    Repeat,
    X,
    LayoutList,
    Kanban,
    Timer,
    Bell,
    Lock,
    Folder,
    FolderKanban,
    Loader2,
} from 'lucide-vue-next';

const props = defineProps<{
    tasks: {
        data: Task[];
        links: any[];
        total: number;
        current_page: number;
        last_page: number;
    };
    kanbanTasks?: Task[];
    viewMode?: 'list' | 'kanban';
    filters: {
        search?: string;
        status?: string;
        category_id?: string;
        project_id?: string;
        priority?: string;
        date_filter?: string;
        sort_by?: string;
        sort_dir?: string;
        view?: string;
    };
    projects: Project[];
    tags: Tag[];
    dependencyCandidates?: {
        id: number;
        title: string;
        status: string;
        project_id: number | null;
    }[];
}>();

const page = usePage<PageProps>();
const { startFocusSession } = useFocusTimer();

// Filter & View State
const currentView = ref<'list' | 'kanban'>(
    props.viewMode === 'kanban' || props.filters.view === 'kanban' ? 'kanban' : 'list'
);
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
            view: currentView.value === 'kanban' ? 'kanban' : undefined,
        },
        { preserveState: true, replace: true }
    );
};

const switchView = (mode: 'list' | 'kanban') => {
    currentView.value = mode;
    applyFilters();
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

// Launch Focus Timer for Task
const launchFocusForTask = (task: Task) => {
    void startFocusSession({
        mode: 'focus',
        planned_minutes: task.estimated_duration || page.props.auth.user?.setting?.focus_work_minutes || 25,
        task_id: task.id,
    });
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
const newCustomReminderInput = ref('');

const taskForm = useForm({
    title: '',
    description: '',
    category_id: '' as string | number,
    project_id: '' as string | number,
    priority: 'medium' as 'high' | 'medium' | 'low',
    status: 'not_started' as Task['status'],
    due_date: '',
    due_time: '',
    reminder_preset: 'none' as TaskReminderPreset,
    remind_at: '',
    custom_reminder_times: [] as string[],
    start_date: '',
    estimated_duration: null as number | null,
    actual_duration: null as number | null,
    notes: '',
    next_action: '',
    is_recurring: false,
    recurrence_rule: 'daily',
    recurrence_interval: 1,
    recurrence_end_type: 'never' as 'never' | 'on_date' | 'after_occurrences',
    recurrence_end_date: '',
    recurrence_max_occurrences: null as number | null,
    tag_ids: [] as number[],
    blocked_by_ids: [] as number[],
    checklists: [] as { id?: number; title: string; is_completed?: boolean }[],
});

const incompleteBlockers = (task: Task) =>
    (task.blocked_by_tasks || []).filter((b) => b.status !== 'completed');

const availableBlockersForModal = computed(() =>
    (props.dependencyCandidates || []).filter((cand) => !activeTask.value || cand.id !== activeTask.value.id)
);

const toggleBlockerSelection = (blockerId: number) => {
    const idx = taskForm.blocked_by_ids.indexOf(blockerId);
    if (idx >= 0) {
        taskForm.blocked_by_ids.splice(idx, 1);
    } else {
        taskForm.blocked_by_ids.push(blockerId);
    }
};

const localProjects = ref<Project[]>([...props.projects]);

watch(
    () => props.projects,
    (newProjects) => {
        localProjects.value = [...newProjects];
    },
    { deep: true }
);

// Quick Add Area (Category)
const isQuickCategoryOpen = ref(false);
const quickCategoryName = ref('');
const quickCategoryColor = ref('#6366f1');
const quickCategoryIcon = ref('Folder');
const isSavingQuickCategory = ref(false);
const quickCategoryError = ref('');

const openQuickCategoryModal = () => {
    quickCategoryName.value = '';
    quickCategoryColor.value = '#6366f1';
    quickCategoryIcon.value = 'Folder';
    quickCategoryError.value = '';
    isQuickCategoryOpen.value = true;
};

const closeQuickCategoryModal = () => {
    isQuickCategoryOpen.value = false;
    quickCategoryName.value = '';
    quickCategoryError.value = '';
};

const submitQuickCategory = async () => {
    if (!quickCategoryName.value.trim()) {
        quickCategoryError.value = 'Please enter an area name.';
        return;
    }
    isSavingQuickCategory.value = true;
    quickCategoryError.value = '';
    try {
        const response = await axios.post(
            route('categories.store'),
            {
                name: quickCategoryName.value.trim(),
                color: quickCategoryColor.value,
                icon: quickCategoryIcon.value,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        const newCat = response.data?.category;
        if (newCat) {
            const categories = page.props.categories as any[];
            if (!categories.some((c) => c.id === newCat.id)) {
                categories.push(newCat);
                categories.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0) || a.name.localeCompare(b.name));
            }
            taskForm.category_id = newCat.id;
        }
        closeQuickCategoryModal();
    } catch (err: any) {
        quickCategoryError.value =
            err.response?.data?.errors?.name?.[0] ||
            err.response?.data?.message ||
            'Failed to create area.';
    } finally {
        isSavingQuickCategory.value = false;
    }
};

// Quick Add Project
const isQuickProjectOpen = ref(false);
const quickProjectName = ref('');
const quickProjectCategoryId = ref<string | number>('');
const quickProjectStatus = ref('in_progress');
const quickProjectDescription = ref('');
const quickProjectTargetDate = ref('');
const isSavingQuickProject = ref(false);
const quickProjectError = ref('');

const openQuickProjectModal = () => {
    quickProjectName.value = '';
    quickProjectCategoryId.value = taskForm.category_id || '';
    quickProjectStatus.value = 'in_progress';
    quickProjectDescription.value = '';
    quickProjectTargetDate.value = '';
    quickProjectError.value = '';
    isQuickProjectOpen.value = true;
};

const closeQuickProjectModal = () => {
    isQuickProjectOpen.value = false;
    quickProjectName.value = '';
    quickProjectError.value = '';
};

const submitQuickProject = async () => {
    if (!quickProjectName.value.trim()) {
        quickProjectError.value = 'Please enter a project name.';
        return;
    }
    isSavingQuickProject.value = true;
    quickProjectError.value = '';
    try {
        const response = await axios.post(
            route('projects.store'),
            {
                name: quickProjectName.value.trim(),
                category_id: quickProjectCategoryId.value || null,
                status: quickProjectStatus.value || 'in_progress',
                description: quickProjectDescription.value.trim() || null,
                target_date: quickProjectTargetDate.value || null,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            }
        );

        const newProj = response.data?.project;
        if (newProj) {
            if (!localProjects.value.some((p) => p.id === newProj.id)) {
                localProjects.value.push(newProj);
                localProjects.value.sort((a, b) => a.name.localeCompare(b.name));
            }
            taskForm.project_id = newProj.id;
            if (!taskForm.category_id && newProj.category_id) {
                taskForm.category_id = newProj.category_id;
            }
        }
        closeQuickProjectModal();
    } catch (err: any) {
        quickProjectError.value =
            err.response?.data?.errors?.name?.[0] ||
            err.response?.data?.message ||
            'Failed to create project.';
    } finally {
        isSavingQuickProject.value = false;
    }
};

watch(
    () => taskForm.category_id,
    (val) => {
        if (val === '__new__') {
            taskForm.category_id = '';
            openQuickCategoryModal();
        }
    }
);

watch(
    () => taskForm.project_id,
    (val) => {
        if (val === '__new__') {
            taskForm.project_id = '';
            openQuickProjectModal();
        }
    }
);

const openCreateModal = () => {
    const setting = page.props.auth.user?.setting;
    activeTask.value = null;
    taskForm.reset();
    taskForm.title = '';
    taskForm.priority = setting?.default_priority || 'medium';
    taskForm.status = 'not_started';
    taskForm.category_id = '';
    taskForm.project_id = '';
    taskForm.due_date = '';
    taskForm.due_time = '';
    taskForm.reminder_preset = setting?.default_task_reminder_preset || 'none';
    taskForm.remind_at = '';
    taskForm.custom_reminder_times = [];
    taskForm.estimated_duration = setting?.default_task_duration || 30;
    taskForm.actual_duration = null;
    taskForm.is_recurring = false;
    taskForm.recurrence_rule = 'daily';
    taskForm.recurrence_interval = 1;
    taskForm.recurrence_end_type = 'never';
    taskForm.recurrence_end_date = '';
    taskForm.recurrence_max_occurrences = null;
    taskForm.tag_ids = [];
    taskForm.blocked_by_ids = [];
    taskForm.checklists = [];
    newCustomReminderInput.value = '';
    isEditing.value = true;
};

const openEditModal = (task: Task) => {
    const tz = page.props.auth.user?.setting?.timezone;
    activeTask.value = task;
    taskForm.title = task.title;
    taskForm.description = task.description || '';
    taskForm.category_id = task.category_id || '';
    taskForm.project_id = task.project_id || '';
    taskForm.priority = task.priority;
    taskForm.status = task.status;
    taskForm.due_date = task.due_date || '';
    taskForm.due_time = task.due_time || '';
    taskForm.reminder_preset = (task.reminder_preset as TaskReminderPreset) || 'none';
    taskForm.remind_at = task.remind_at ? getLocalDateTimeInputValue(tz, new Date(task.remind_at)) : '';
    taskForm.custom_reminder_times = Array.isArray(task.custom_reminder_times) ? [...task.custom_reminder_times] : [];
    taskForm.start_date = task.start_date || '';
    taskForm.estimated_duration = task.estimated_duration;
    taskForm.actual_duration = task.actual_duration;
    taskForm.notes = task.notes || '';
    taskForm.next_action = task.next_action || '';
    taskForm.is_recurring = task.is_recurring;
    taskForm.recurrence_rule = task.recurrence_rule || 'daily';
    taskForm.recurrence_interval = task.recurrence_interval || 1;
    taskForm.recurrence_end_type = task.recurrence_end_type || 'never';
    taskForm.recurrence_end_date = task.recurrence_end_date || '';
    taskForm.recurrence_max_occurrences = task.recurrence_max_occurrences ?? null;
    taskForm.tag_ids = task.tags?.map((t) => t.id) || [];
    taskForm.blocked_by_ids = task.blocked_by_tasks?.map((b) => b.id) || [];
    taskForm.checklists =
        task.checklists?.map((c) => ({
            id: c.id,
            title: c.title,
            is_completed: c.is_completed,
        })) || [];
    newCustomReminderInput.value = '';
    isEditing.value = true;
};

const toggleTagSelection = (tagId: number) => {
    const idx = taskForm.tag_ids.indexOf(tagId);
    if (idx >= 0) {
        taskForm.tag_ids.splice(idx, 1);
    } else {
        taskForm.tag_ids.push(tagId);
    }
};

const addCustomReminderTime = () => {
    if (!newCustomReminderInput.value) return;
    if (!taskForm.custom_reminder_times.includes(newCustomReminderInput.value)) {
        taskForm.custom_reminder_times.push(newCustomReminderInput.value);
    }
    newCustomReminderInput.value = '';
};

const removeCustomReminderTime = (idx: number) => {
    taskForm.custom_reminder_times.splice(idx, 1);
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
    <AuthenticatedLayout title="Tasks">
        <Head title="Tasks Management" />

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header with View Switcher & Create Task -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-white">All Tasks</h2>
                    <p class="text-xs text-neutral-400">
                        {{ tasks.total }} tasks matching current scope • Priority, reminders, recurrence & focus tracking
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- List vs Kanban Toggle -->
                    <div class="flex items-center border border-neutral-200 dark:border-neutral-800 rounded-xl p-0.5 bg-neutral-100/60 dark:bg-neutral-800/60">
                        <button
                            type="button"
                            @click="switchView('list')"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center gap-1.5 transition"
                            :class="currentView === 'list' ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs' : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400'"
                        >
                            <LayoutList class="w-3.5 h-3.5" />
                            <span>List</span>
                        </button>
                        <button
                            type="button"
                            @click="switchView('kanban')"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center gap-1.5 transition"
                            :class="currentView === 'kanban' ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs' : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400'"
                        >
                            <Kanban class="w-3.5 h-3.5" />
                            <span>Kanban</span>
                        </button>
                    </div>

                    <button
                        type="button"
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
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                    <!-- Search Input -->
                    <div class="sm:col-span-2 relative">
                        <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5" />
                        <input
                            v-model="search"
                            @input="applyFilters"
                            type="text"
                            placeholder="Search tasks, actions..."
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

                    <!-- Priority Filter -->
                    <div>
                        <select
                            v-model="priority"
                            @change="applyFilters"
                            class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Priorities</option>
                            <option value="high">High Priority</option>
                            <option value="medium">Medium Priority</option>
                            <option value="low">Low Priority</option>
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
                    <span class="text-neutral-400">
                        <template v-if="currentView === 'list'">
                            Showing page {{ tasks.current_page }} of {{ tasks.last_page }}
                        </template>
                        <template v-else>
                            Showing {{ (kanbanTasks || tasks.data).length }} tasks on Kanban board
                        </template>
                    </span>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        Reset filters
                    </button>
                </div>
            </div>

            <!-- Bulk Actions Bar (List View) -->
            <div
                v-if="currentView === 'list' && selectedTaskIds.length > 0"
                class="flex items-center justify-between bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 px-4 py-2.5 rounded-xl text-xs"
            >
                <span class="font-medium text-indigo-900 dark:text-indigo-200">
                    {{ selectedTaskIds.length }} tasks selected
                </span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="executeBulkAction('complete')"
                        class="px-2.5 py-1 bg-white dark:bg-neutral-800 rounded text-neutral-700 dark:text-neutral-300 font-medium hover:bg-neutral-100 shadow-xs"
                    >
                        Mark Completed
                    </button>
                    <button
                        type="button"
                        @click="executeBulkAction('reopen')"
                        class="px-2.5 py-1 bg-white dark:bg-neutral-800 rounded text-neutral-700 dark:text-neutral-300 font-medium hover:bg-neutral-100 shadow-xs"
                    >
                        Reopen
                    </button>
                    <button
                        type="button"
                        @click="executeBulkAction('delete')"
                        class="px-2.5 py-1 bg-rose-600 text-white rounded font-medium hover:bg-rose-700 shadow-xs"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <!-- KANBAN BOARD VIEW -->
            <TaskKanbanBoard
                v-if="currentView === 'kanban'"
                :tasks="kanbanTasks || tasks.data"
                @edit-task="openEditModal"
                @toggle-task="toggleTask"
            />

            <!-- TASKS TABLE / LIST VIEW -->
            <div v-else class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-neutral-50 dark:bg-neutral-800/50 text-neutral-500 uppercase tracking-wider text-[10px] border-b border-neutral-200 dark:border-neutral-800">
                            <tr>
                                <th class="p-3 w-8">
                                    <input
                                        type="checkbox"
                                        v-model="selectAll"
                                        @change="toggleSelectAll"
                                        class="rounded border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-indigo-600 focus:ring-indigo-500 dark:focus:ring-offset-neutral-900"
                                    />
                                </th>
                                <th class="p-3">Title</th>
                                <th class="p-3">Area</th>
                                <th class="p-3">Project</th>
                                <th class="p-3">Priority</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Due / Focus</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            <tr v-if="tasks.data.length === 0">
                                <td colspan="8" class="p-8 text-center text-neutral-400">
                                    No tasks match your filter criteria.
                                </td>
                            </tr>
                            <tr
                                v-for="task in tasks.data"
                                :key="task.id"
                                class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition group"
                            >
                                <!-- Checkbox for selection (Box 1 - dark background on dark theme) -->
                                <td class="p-3 w-8">
                                    <input
                                        type="checkbox"
                                        :value="task.id"
                                        v-model="selectedTaskIds"
                                        class="rounded border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-indigo-600 focus:ring-indigo-500 dark:focus:ring-offset-neutral-900"
                                    />
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
                                            {{ task.checklists.filter((c) => c.is_completed).length }}/{{ task.checklists.length }}
                                        </span>
                                        <span v-if="task.is_recurring" class="inline-flex items-center gap-0.5 text-[10px] text-indigo-500">
                                            <Repeat class="w-3 h-3" /> {{ task.recurrence_rule || 'Recurring' }}
                                        </span>
                                        <span
                                            v-if="task.reminder_preset && task.reminder_preset !== 'none'"
                                            class="inline-flex items-center gap-0.5 text-[10px] text-amber-600 dark:text-amber-400"
                                            title="Reminder enabled"
                                        >
                                            <Bell class="w-3 h-3" />
                                        </span>
                                        <span
                                            v-if="incompleteBlockers(task).length > 0"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900"
                                            :title="'Blocked by: ' + incompleteBlockers(task).map((b) => b.title).join(', ')"
                                        >
                                            <Lock class="w-2.5 h-2.5" />
                                            Blocked by {{ incompleteBlockers(task).length }}
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

                                <!-- Due Date & Duration -->
                                <td class="p-3 whitespace-nowrap">
                                    <div v-if="task.due_date" class="flex items-center gap-1">
                                        <AlertCircle
                                            v-if="isOverdueLocal(task.due_date, task.status, $page.props.auth.user?.setting?.timezone)"
                                            class="w-3.5 h-3.5 text-rose-500 shrink-0"
                                        />
                                        <span
                                            class="font-mono text-[11px]"
                                            :class="
                                                isOverdueLocal(task.due_date, task.status, $page.props.auth.user?.setting?.timezone)
                                                    ? 'text-rose-600 dark:text-rose-400 font-semibold'
                                                    : 'text-neutral-700 dark:text-neutral-300'
                                            "
                                        >
                                            {{ formatDateWithDay(task.due_date) }}<template v-if="task.due_time"> | {{ formatTime12Hour(task.due_time) }}</template>
                                        </span>
                                    </div>
                                    <span v-else class="text-neutral-400">-</span>
                                    <div v-if="task.estimated_duration || task.actual_duration" class="text-[10px] text-neutral-400 flex items-center gap-1 mt-0.5">
                                        <Clock class="w-2.5 h-2.5" />
                                        <span>
                                            <template v-if="task.actual_duration">{{ task.actual_duration }}m / </template>{{ task.estimated_duration || 0 }}m
                                        </span>
                                    </div>
                                </td>

                                <!-- Row Actions & Right-most Status Completion Toggle -->
                                <td class="p-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Secondary actions (Focus, Edit, Duplicate, Delete) -->
                                        <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition">
                                            <button
                                                v-if="task.status !== 'completed'"
                                                type="button"
                                                @click="launchFocusForTask(task)"
                                                class="p-1 rounded text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                                title="Start Focus Timer"
                                            >
                                                <Timer class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                @click="openEditModal(task)"
                                                class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                                title="Edit task"
                                            >
                                                <Edit3 class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                @click="duplicateTask(task)"
                                                class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                                title="Duplicate task"
                                            >
                                                <Copy class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                @click="deleteTask(task)"
                                                class="p-1 rounded text-neutral-400 hover:text-rose-500"
                                                title="Delete task"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>

                                        <!-- Right-most Status Toggle Button -->
                                        <button
                                            type="button"
                                            @click="toggleTask(task)"
                                            class="p-1.5 rounded-lg border transition flex items-center justify-center shrink-0 shadow-xs"
                                            :class="
                                                task.status === 'completed'
                                                    ? 'border-rose-300 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-900/60'
                                                    : 'border-emerald-300 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/60'
                                            "
                                            :title="task.status === 'completed' ? 'Task is done — click to mark not done' : 'Click to mark task as done'"
                                        >
                                            <!-- When task is done: false icon (X) to make not done again -->
                                            <X v-if="task.status === 'completed'" class="w-3.5 h-3.5 stroke-[2.5]" />
                                            <!-- When task is not done: right symbol (Check) to make done -->
                                            <Check v-else class="w-3.5 h-3.5 stroke-[2.5]" />
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
                            type="button"
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
                        <button type="button" @click="isEditing = false" class="p-1 text-neutral-400 hover:text-neutral-600">
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
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block font-medium text-neutral-700 dark:text-neutral-300">Area / Category</label>
                                    <button
                                        type="button"
                                        @click="openQuickCategoryModal"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition"
                                    >
                                        <Plus class="w-3 h-3" />
                                        <span>Add Area</span>
                                    </button>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <select
                                        v-model="taskForm.category_id"
                                        class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">None</option>
                                        <option v-for="cat in $page.props.categories" :key="cat.id" :value="cat.id">
                                            {{ cat.name }}
                                        </option>
                                        <option value="__new__" class="font-semibold text-indigo-600 dark:text-indigo-400">
                                            + Add New Area...
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="openQuickCategoryModal"
                                        title="Create new area"
                                        class="p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition shrink-0"
                                    >
                                        <Plus class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Project -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block font-medium text-neutral-700 dark:text-neutral-300">Project</label>
                                    <button
                                        type="button"
                                        @click="openQuickProjectModal"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition"
                                    >
                                        <Plus class="w-3 h-3" />
                                        <span>Add Project</span>
                                    </button>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <select
                                        v-model="taskForm.project_id"
                                        class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">None</option>
                                        <option v-for="p in localProjects" :key="p.id" :value="p.id">
                                            {{ p.name }}
                                        </option>
                                        <option value="__new__" class="font-semibold text-indigo-600 dark:text-indigo-400">
                                            + Add New Project...
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="openQuickProjectModal"
                                        title="Create new project"
                                        class="p-2 rounded-lg border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition shrink-0"
                                    >
                                        <Plus class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                        </div>

                        <!-- Due Date with Current Week Day Calculator -->
                        <div class="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800/80 space-y-3">
                            <WeekdaySelector
                                v-model="taskForm.due_date"
                                label="Due Date (Pick day in current week to auto-calculate)"
                            />

                            <!-- Due Time -->
                            <div class="pt-2 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-3 flex-wrap">
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 text-xs shrink-0">Due Time (Optional)</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="taskForm.due_time"
                                        type="time"
                                        class="text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white py-1"
                                    />
                                    <span v-if="taskForm.due_time" class="text-[11px] font-mono font-medium text-neutral-600 dark:text-neutral-400">
                                        {{ formatTime12Hour(taskForm.due_time) }}
                                    </span>
                                </div>
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
                                    min="1"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Logged Focus (min)</label>
                                <input
                                    v-model.number="taskForm.actual_duration"
                                    type="number"
                                    min="0"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <!-- Reminders & Alarms -->
                        <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-3">
                            <div class="flex items-center gap-2 font-semibold text-neutral-800 dark:text-neutral-200">
                                <Bell class="w-3.5 h-3.5 text-indigo-500" />
                                <span>Reminders & Deadline Alarms</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                                        Primary Reminder Preset
                                    </label>
                                    <select
                                        v-model="taskForm.reminder_preset"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    >
                                        <option value="none">None</option>
                                        <option value="at_due">At due time (default 09:00)</option>
                                        <option value="15m">15 minutes before</option>
                                        <option value="30m">30 minutes before</option>
                                        <option value="1h">1 hour before</option>
                                        <option value="1d">1 day before</option>
                                        <option value="custom">Custom date & time</option>
                                    </select>
                                </div>

                                <div v-if="taskForm.reminder_preset === 'custom'">
                                    <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                                        Custom Reminder Date/Time
                                    </label>
                                    <input
                                        v-model="taskForm.remind_at"
                                        type="datetime-local"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <!-- Additional Multiple Reminders -->
                            <div>
                                <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">
                                    Additional Custom Reminders (optional)
                                </label>
                                <div v-if="taskForm.custom_reminder_times.length > 0" class="flex flex-wrap gap-1.5 mb-2">
                                    <span
                                        v-for="(rt, idx) in taskForm.custom_reminder_times"
                                        :key="idx"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 text-[11px]"
                                    >
                                        <span>{{ rt.replace('T', ' ') }}</span>
                                        <button type="button" @click="removeCustomReminderTime(idx)" class="hover:text-rose-500">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <input
                                        v-model="newCustomReminderInput"
                                        type="datetime-local"
                                        class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    />
                                    <button
                                        type="button"
                                        @click="addCustomReminderTime"
                                        class="px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 font-medium hover:bg-neutral-100"
                                    >
                                        + Add Alarm
                                    </button>
                                </div>
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

                        <!-- Task Dependencies (Blocked By) -->
                        <div v-if="availableBlockersForModal.length > 0" class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="inline-flex items-center gap-1.5 font-semibold text-neutral-800 dark:text-neutral-200">
                                    <Lock class="w-3.5 h-3.5 text-amber-500" />
                                    <span>Blocked By (Prerequisite Tasks)</span>
                                </label>
                                <span v-if="taskForm.blocked_by_ids.length > 0" class="text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                    {{ taskForm.blocked_by_ids.length }} selected (auto-promotes when completed)
                                </span>
                            </div>
                            <p v-if="(taskForm.errors as any).blocked_by_ids" class="text-[11px] text-rose-500">
                                {{ (taskForm.errors as any).blocked_by_ids }}
                            </p>
                            <div class="max-h-32 overflow-y-auto space-y-1 pr-1">
                                <label
                                    v-for="cand in availableBlockersForModal"
                                    :key="cand.id"
                                    class="flex items-center justify-between gap-2 px-2 py-1 rounded-lg hover:bg-white dark:hover:bg-neutral-900 cursor-pointer text-xs"
                                >
                                    <span class="flex items-center gap-2 min-w-0">
                                        <input
                                            type="checkbox"
                                            :checked="taskForm.blocked_by_ids.includes(cand.id)"
                                            @change="toggleBlockerSelection(cand.id)"
                                            class="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span class="truncate text-neutral-800 dark:text-neutral-200">{{ cand.title }}</span>
                                    </span>
                                    <span class="text-[10px] uppercase text-neutral-400 shrink-0">{{ cand.status.replace('_', ' ') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div v-if="tags.length > 0">
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Tags</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="tag in tags"
                                    :key="tag.id"
                                    type="button"
                                    @click="toggleTagSelection(tag.id)"
                                    class="px-2.5 py-1 rounded-full text-[11px] font-medium border transition"
                                    :class="
                                        taskForm.tag_ids.includes(tag.id)
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'
                                    "
                                >
                                    #{{ tag.name }}
                                </button>
                            </div>
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

                        <!-- Recurrence Engine -->
                        <div class="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200/70 dark:border-neutral-800 space-y-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="taskForm.is_recurring"
                                    class="rounded text-indigo-600 border-neutral-300"
                                />
                                <Repeat class="w-3.5 h-3.5 text-indigo-500" />
                                <span class="font-semibold text-neutral-800 dark:text-neutral-200">Recurring Task</span>
                            </label>

                            <div v-if="taskForm.is_recurring" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                                <div>
                                    <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">Pattern</label>
                                    <select
                                        v-model="taskForm.recurrence_rule"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekdays">Weekdays (Mon–Fri)</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="custom_days">Every X Days</option>
                                        <option value="custom_weeks">Every X Weeks</option>
                                        <option value="custom_months">Every X Months</option>
                                    </select>
                                </div>

                                <div v-if="['custom_days', 'custom_weeks', 'custom_months'].includes(taskForm.recurrence_rule)">
                                    <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">Interval (X)</label>
                                    <input
                                        v-model.number="taskForm.recurrence_interval"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">Ends</label>
                                    <select
                                        v-model="taskForm.recurrence_end_type"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    >
                                        <option value="never">Never</option>
                                        <option value="on_date">On specific date</option>
                                        <option value="after_occurrences">After N occurrences</option>
                                    </select>
                                </div>

                                <div v-if="taskForm.recurrence_end_type === 'on_date'">
                                    <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">End Date</label>
                                    <input
                                        v-model="taskForm.recurrence_end_date"
                                        type="date"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    />
                                </div>

                                <div v-if="taskForm.recurrence_end_type === 'after_occurrences'">
                                    <label class="block text-[11px] font-medium text-neutral-600 dark:text-neutral-400 mb-1">Max Occurrences</label>
                                    <input
                                        v-model.number="taskForm.recurrence_max_occurrences"
                                        type="number"
                                        min="1"
                                        max="999"
                                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    />
                                </div>
                            </div>
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

            <!-- Quick Add Area Modal -->
            <div
                v-if="isQuickCategoryOpen"
                class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="closeQuickCategoryModal"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl max-w-md w-full border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                <Folder class="w-4 h-4" />
                            </span>
                            <h3 class="text-base font-bold text-neutral-900 dark:text-white">
                                Add New Area
                            </h3>
                        </div>
                        <button type="button" @click="closeQuickCategoryModal" class="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div v-if="quickCategoryError" class="p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-xs text-rose-600 dark:text-rose-400">
                        {{ quickCategoryError }}
                    </div>

                    <form @submit.prevent="submitQuickCategory" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Area Name *</label>
                            <input
                                v-model="quickCategoryName"
                                type="text"
                                required
                                autofocus
                                placeholder="e.g. University, ROV Sub-team, Personal"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Color</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="quickCategoryColor"
                                        type="color"
                                        class="h-8 w-11 rounded border border-neutral-200 dark:border-neutral-800 p-0.5 bg-transparent cursor-pointer"
                                    />
                                    <span class="font-mono text-[11px] text-neutral-500">{{ quickCategoryColor }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Icon</label>
                                <select
                                    v-model="quickCategoryIcon"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="Folder">Folder</option>
                                    <option value="GraduationCap">Graduation Cap</option>
                                    <option value="Code">Code</option>
                                    <option value="Zap">Zap</option>
                                    <option value="Compass">Compass</option>
                                    <option value="Briefcase">Briefcase</option>
                                    <option value="Heart">Heart</option>
                                    <option value="User">User</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="closeQuickCategoryModal"
                                class="px-3.5 py-1.5 rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="isSavingQuickCategory"
                                class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium flex items-center gap-1.5 disabled:opacity-50 transition"
                            >
                                <Loader2 v-if="isSavingQuickCategory" class="w-3.5 h-3.5 animate-spin" />
                                <span>Save Area</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Add Project Modal -->
            <div
                v-if="isQuickProjectOpen"
                class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="closeQuickProjectModal"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl max-w-md w-full border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                <FolderKanban class="w-4 h-4" />
                            </span>
                            <h3 class="text-base font-bold text-neutral-900 dark:text-white">
                                Add New Project
                            </h3>
                        </div>
                        <button type="button" @click="closeQuickProjectModal" class="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div v-if="quickProjectError" class="p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-xs text-rose-600 dark:text-rose-400">
                        {{ quickProjectError }}
                    </div>

                    <form @submit.prevent="submitQuickProject" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Project Name *</label>
                            <input
                                v-model="quickProjectName"
                                type="text"
                                required
                                autofocus
                                placeholder="e.g. ROV Stabilizer, Exam Revision"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Area / Category</label>
                                <select
                                    v-model="quickProjectCategoryId"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="">None</option>
                                    <option v-for="cat in $page.props.categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Status</label>
                                <select
                                    v-model="quickProjectStatus"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="planned">Planned</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Target Date (Optional)</label>
                            <input
                                v-model="quickProjectTargetDate"
                                type="date"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Description (Optional)</label>
                            <textarea
                                v-model="quickProjectDescription"
                                rows="2"
                                placeholder="Brief project summary..."
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="closeQuickProjectModal"
                                class="px-3.5 py-1.5 rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="isSavingQuickProject"
                                class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium flex items-center gap-1.5 disabled:opacity-50 transition"
                            >
                                <Loader2 v-if="isSavingQuickProject" class="w-3.5 h-3.5 animate-spin" />
                                <span>Save Project</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
