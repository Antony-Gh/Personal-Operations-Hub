<script setup lang="ts">
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import {
    X,
    CheckSquare,
    FolderKanban,
    Tag as TagIcon,
    Smartphone,
    Plus,
} from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits(['close']);

const page = usePage<PageProps>();
const activeTab = ref<'task' | 'project' | 'category' | 'phone'>('task');

// Task Form
const taskForm = useForm({
    title: '',
    description: '',
    category_id: '' as string | number,
    project_id: '' as string | number,
    priority: 'medium' as 'high' | 'medium' | 'low',
    status: 'not_started' as 'not_started' | 'in_progress',
    due_date: new Date().toISOString().split('T')[0],
    estimated_duration: 30,
    next_action: '',
});

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
    start_date: new Date().toISOString().split('T')[0],
    target_date: '',
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
    date: new Date().toISOString().split('T')[0],
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
            <div class="flex border-b border-gray-100 dark:border-gray-700/60 px-6 gap-2 bg-gray-50/50 dark:bg-gray-900/30">
                <button
                    type="button"
                    @click="activeTab = 'task'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition"
                    :class="activeTab === 'task' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <CheckSquare class="w-4 h-4" /> Task
                </button>
                <button
                    type="button"
                    @click="activeTab = 'project'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition"
                    :class="activeTab === 'project' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <FolderKanban class="w-4 h-4" /> Project
                </button>
                <button
                    type="button"
                    @click="activeTab = 'category'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition"
                    :class="activeTab === 'category' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <TagIcon class="w-4 h-4" /> Area
                </button>
                <button
                    type="button"
                    @click="activeTab = 'phone'"
                    class="flex items-center gap-1.5 py-3 px-3 text-xs font-medium border-b-2 transition"
                    :class="activeTab === 'phone' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400'"
                >
                    <Smartphone class="w-4 h-4" /> Phone Usage
                </button>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Task Form -->
                <form v-if="activeTab === 'task'" @submit.prevent="submitTask" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Task Title</label>
                        <input
                            v-model="taskForm.title"
                            type="text"
                            required
                            placeholder="What needs to be done?"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
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
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select
                                v-model="projectForm.status"
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="planned">Planned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="on_hold">On Hold</option>
                            </select>
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

