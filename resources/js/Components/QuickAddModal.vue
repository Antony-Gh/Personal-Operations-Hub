<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { PageProps, TaskReminderPreset } from '@/types';
import { getLocalDateString } from '@/utils/date';
import { previewNaturalTaskTokens } from '@/utils/naturalTaskParser';
import {
    X,
    CheckSquare,
    FolderKanban,
    Tag as TagIcon,
    Smartphone,
    Plus,
    Sparkles,
} from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
    initialTab?: 'task' | 'project' | 'category' | 'phone';
}>();

const emit = defineEmits(['close']);

const page = usePage<PageProps>();
const activeTab = ref<'task' | 'project' | 'category' | 'phone'>(props.initialTab || 'task');

const userTimezone = () => page.props.auth.user?.setting?.timezone;

watch(
    () => props.show,
    (opened) => {
        if (opened) {
            activeTab.value = props.initialTab || 'task';
            const localToday = getLocalDateString(userTimezone());
            taskForm.due_date = localToday;
            taskForm.priority = page.props.auth.user?.setting?.default_priority || 'medium';
            taskForm.estimated_duration = page.props.auth.user?.setting?.default_task_duration || 30;
            taskForm.reminder_preset =
                (page.props.auth.user?.setting?.default_task_reminder_preset as TaskReminderPreset) || 'none';
            projectForm.start_date = localToday;
            phoneForm.date = localToday;
        }
    }
);

// Task Form
const taskForm = useForm({
    title: '',
    description: '',
    category_id: '' as string | number,
    project_id: '' as string | number,
    priority: (page.props.auth.user?.setting?.default_priority || 'medium') as 'high' | 'medium' | 'low',
    status: 'not_started' as 'not_started' | 'in_progress',
    due_date: getLocalDateString(userTimezone()),
    due_time: '',
    reminder_preset: ((page.props.auth.user?.setting?.default_task_reminder_preset as TaskReminderPreset) || 'none') as TaskReminderPreset,
    estimated_duration: page.props.auth.user?.setting?.default_task_duration || 30,
    next_action: '',
    smart_parse: true,
});

const smartPreview = computed(() =>
    taskForm.smart_parse ? previewNaturalTaskTokens(taskForm.title) : { cleanTitle: taskForm.title, tokens: [] }
);

const submitTask = () => {
    taskForm.post(route('tasks.store'), {
        onSuccess: () => {
            taskForm.reset();
            emit('close');
        },
    });
};

// Project Form
const projectForm = useForm({
    name: '',
    description: '',
    category_id: '' as string | number,
    status: 'in_progress',
    start_date: getLocalDateString(userTimezone()),
    target_date: '',
    reminders_enabled: true,
});

const submitProject = () => {
    projectForm.post(route('projects.store'), {
        onSuccess: () => {
            projectForm.reset();
            emit('close');
        },
    });
};

// Category Form
const categoryForm = useForm({
    name: '',
    color: '#6366f1',
    icon: 'Folder',
});

const submitCategory = () => {
    categoryForm.post(route('categories.store'), {
        onSuccess: () => {
            categoryForm.reset();
            emit('close');
        },
    });
};

// Phone Usage Form
const phoneForm = useForm({
    date: getLocalDateString(userTimezone()),
    minutes: 30,
    notes: '',
});

const submitPhone = () => {
    phoneForm.post(route('phone-usage.store'), {
        onSuccess: () => {
            phoneForm.reset();
            emit('close');
        },
    });
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div
            class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden transform transition-all"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700/60">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <Plus class="w-4 h-4" />
                    </span>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Quick Add</h3>
                </div>
                <button
                    @click="emit('close')"
                    class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="flex border-b border-gray-100 dark:border-gray-700/60 px-6 gap-2 bg-gray-50/50 dark:bg-gray-900/30 overflow-x-auto">
                <button
                    type="button"
                    @click="activeTab = 'task'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'task' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <CheckSquare class="w-4 h-4" /> Task
                </button>
                <button
                    type="button"
                    @click="activeTab = 'project'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'project' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <FolderKanban class="w-4 h-4" /> Project
                </button>
                <button
                    type="button"
                    @click="activeTab = 'category'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'category' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <TagIcon class="w-4 h-4" /> Area
                </button>
                <button
                    type="button"
                    @click="activeTab = 'phone'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition whitespace-nowrap"
                    :class="activeTab === 'phone' ? 'border-indigo-600 text-indigo-600 dark:text-gray-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <Smartphone class="w-4 h-4" /> Phone Usage
                </button>
            </div>

            <!-- Tab Content -->
            <div class="p-6 max-h-[75vh] overflow-y-auto">
                <!-- Task Form -->
                <form v-if="activeTab === 'task'" @submit.prevent="submitTask" class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Task Title</label>
                            <label class="inline-flex items-center gap-1.5 text-[11px] font-medium text-indigo-600 dark:text-indigo-400 cursor-pointer select-none">
                                <input
                                    v-model="taskForm.smart_parse"
                                    type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5"
                                />
                                <Sparkles class="w-3 h-3" />
                                Smart Shorthand
                            </label>
                        </div>
                        <input
                            v-model="taskForm.title"
                            type="text"
                            required
                            :placeholder="taskForm.smart_parse ? 'e.g. Finish OS lab tomorrow 17:00 !high #University ~90m' : 'What needs to be done?'"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div v-if="taskForm.smart_parse && smartPreview.tokens.length > 0" class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">Parsed:</span>
                            <span
                                v-for="(tok, idx) in smartPreview.tokens"
                                :key="idx"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200/70 dark:border-indigo-800/70"
                            >
                                {{ tok.label }}
                            </span>
                        </div>
                        <p v-else-if="taskForm.smart_parse" class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                            Supports <code class="font-mono">!high</code>, <code class="font-mono">~90m</code>, <code class="font-mono">#Area</code>, <code class="font-mono">+Project</code>, <code class="font-mono">tomorrow 17:00</code>
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Area / Category</label>
                            <select
                                v-model="taskForm.category_id"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">No Category</option>
                                <option v-for="cat in page.props.categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                            <select
                                v-model="taskForm.priority"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date</label>
                            <input
                                v-model="taskForm.due_date"
                                type="date"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Due Time (Optional)</label>
                            <input
                                v-model="taskForm.due_time"
                                type="time"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Alarm / Reminder</label>
                            <select
                                v-model="taskForm.reminder_preset"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="none">No Reminder</option>
                                <option value="at_due">At Due Time</option>
                                <option value="15m">15 min before</option>
                                <option value="30m">30 min before</option>
                                <option value="1h">1 hour before</option>
                                <option value="1d">1 day before</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Est. Duration (min)</label>
                            <input
                                v-model.number="taskForm.estimated_duration"
                                type="number"
                                min="5"
                                step="5"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Next Immediate Action (Optional)</label>
                        <input
                            v-model="taskForm.next_action"
                            type="text"
                            placeholder="e.g., Open textbook p. 45 or clone repo"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="taskForm.processing"
                            class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            Create Task
                        </button>
                    </div>
                </form>

                <!-- Project Form -->
                <form v-if="activeTab === 'project'" @submit.prevent="submitProject" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Project Name</label>
                        <input
                            v-model="projectForm.name"
                            type="text"
                            required
                            placeholder="e.g. ROV Stabilization"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Area / Category</label>
                            <select
                                v-model="projectForm.category_id"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">No Category</option>
                                <option v-for="cat in page.props.categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Target Deadline</label>
                            <input
                                v-model="projectForm.target_date"
                                type="date"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                        <textarea
                            v-model="projectForm.description"
                            rows="2"
                            placeholder="Brief description of project goals"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="projectForm.processing"
                            class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            Create Project
                        </button>
                    </div>
                </form>

                <!-- Category Form -->
                <form v-if="activeTab === 'category'" @submit.prevent="submitCategory" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Area / Category Name</label>
                        <input
                            v-model="categoryForm.name"
                            type="text"
                            required
                            placeholder="e.g., University, Software Team"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="categoryForm.color"
                                    type="color"
                                    class="h-9 w-12 rounded border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5 bg-transparent"
                                />
                                <span class="text-xs font-mono text-gray-600 dark:text-gray-400">{{ categoryForm.color }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Icon Style</label>
                            <select
                                v-model="categoryForm.icon"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="Folder">Folder</option>
                                <option value="GraduationCap">Graduation Cap</option>
                                <option value="Code">Code</option>
                                <option value="Briefcase">Briefcase</option>
                                <option value="Heart">Heart</option>
                                <option value="Compass">Compass</option>
                                <option value="Zap">Zap</option>
                                <option value="User">User</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="categoryForm.processing"
                            class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            Create Area
                        </button>
                    </div>
                </form>

                <!-- Phone Usage Form -->
                <form v-if="activeTab === 'phone'" @submit.prevent="submitPhone" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                        <input
                            v-model="phoneForm.date"
                            type="date"
                            required
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Leisure Phone Time (Minutes)</label>
                        <input
                            v-model.number="phoneForm.minutes"
                            type="number"
                            min="0"
                            max="1440"
                            required
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Reflection / Notes (Optional)</label>
                        <input
                            v-model="phoneForm.notes"
                            type="text"
                            placeholder="e.g. Watched documentary clips, messaging friends"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="phoneForm.processing"
                            class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            Save Phone Time
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
