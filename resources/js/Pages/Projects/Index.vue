<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Category, Project } from '@/types';
import {
    FolderKanban,
    Plus,
    LayoutList,
    Kanban,
    Calendar,
    CheckCircle2,
    Clock,
    ArrowUpRight,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    projects: Project[];
    filters: {
        status?: string;
        category_id?: string;
    };
    categories: Category[];
}>();

const viewMode = ref<'grid' | 'kanban'>('grid');
const isCreateOpen = ref(false);

const projectForm = useForm({
    name: '',
    category_id: '' as string | number,
    description: '',
    status: 'in_progress',
    start_date: new Date().toISOString().split('T')[0],
    target_date: '',
    notes: '',
    milestones: [] as { title: string; due_date: string }[],
});

const newMilestoneTitle = ref('');
const newMilestoneDate = ref('');

const addMilestone = () => {
    if (!newMilestoneTitle.value.trim()) return;
    projectForm.milestones.push({
        title: newMilestoneTitle.value.trim(),
        due_date: newMilestoneDate.value || '',
    });
    newMilestoneTitle.value = '';
    newMilestoneDate.value = '';
};

const removeMilestone = (idx: number) => {
    projectForm.milestones.splice(idx, 1);
};

const submitProject = () => {
    projectForm.post(route('projects.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            projectForm.reset();
        },
    });
};

const kanbanColumns = [
    { key: 'planned', label: 'Planned', color: 'border-blue-400' },
    { key: 'in_progress', label: 'In Progress', color: 'border-indigo-500' },
    { key: 'on_hold', label: 'On Hold', color: 'border-amber-400' },
    { key: 'completed', label: 'Completed', color: 'border-emerald-500' },
];
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout title="Projects">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <FolderKanban class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Active Projects</h2>
                        <p class="text-xs text-neutral-400">
                            Engineering initiatives, academic research, and company deliveries
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- View Mode Toggle -->
                    <div class="flex items-center border border-neutral-200 dark:border-neutral-800 rounded-xl p-0.5 bg-neutral-100/50 dark:bg-neutral-800/50">
                        <button
                            @click="viewMode = 'grid'"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-medium transition"
                            :class="viewMode === 'grid' ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs' : 'text-neutral-500 hover:text-neutral-800'"
                        >
                            <LayoutList class="w-3.5 h-3.5 inline mr-1" /> Cards
                        </button>
                        <button
                            @click="viewMode = 'kanban'"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-medium transition"
                            :class="viewMode === 'kanban' ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white shadow-xs' : 'text-neutral-500 hover:text-neutral-800'"
                        >
                            <Kanban class="w-3.5 h-3.5 inline mr-1" /> Kanban
                        </button>
                    </div>

                    <button
                        @click="isCreateOpen = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>New Project</span>
                    </button>
                </div>
            </div>

            <!-- Grid / Cards View -->
            <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <Link
                    v-for="project in projects"
                    :key="project.id"
                    :href="route('projects.show', project.id)"
                    class="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 transition shadow-xs flex flex-col justify-between group"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white group-hover:text-indigo-600 transition">
                                {{ project.name }}
                            </h3>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider shrink-0"
                                :class="{
                                    'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400': project.status === 'in_progress',
                                    'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400': project.status === 'planned',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400': project.status === 'completed',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400': project.status === 'on_hold',
                                }"
                            >
                                {{ project.status.replace('_', ' ') }}
                            </span>
                        </div>

                        <p v-if="project.category" class="text-[11px] font-medium mb-3" :style="{ color: project.category.color }">
                            {{ project.category.name }}
                        </p>

                        <p class="text-xs text-neutral-600 dark:text-neutral-400 line-clamp-2 mb-4">
                            {{ project.description || 'No description provided.' }}
                        </p>
                    </div>

                    <div>
                        <!-- Progress -->
                        <div class="space-y-1 mb-3">
                            <div class="flex justify-between text-[11px] text-neutral-400 font-medium">
                                <span>Progress</span>
                                <span>{{ project.progress_percent }}%</span>
                            </div>
                            <div class="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-indigo-600 h-1.5 rounded-full transition-all" :style="{ width: `${project.progress_percent}%` }"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-neutral-400 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                            <span>{{ project.completed_tasks_count }} / {{ project.total_tasks_count }} tasks</span>
                            <span v-if="project.target_date">Target: {{ project.target_date }}</span>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Kanban Board View -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="col in kanbanColumns"
                    :key="col.key"
                    class="bg-neutral-100/50 dark:bg-neutral-900/60 rounded-2xl p-4 border border-neutral-200 dark:border-neutral-800"
                >
                    <div class="flex items-center justify-between mb-3 pb-2 border-b-2" :class="col.color">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300">
                            {{ col.label }}
                        </h4>
                        <span class="text-xs text-neutral-400 font-mono">
                            {{ projects.filter(p => p.status === col.key).length }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <Link
                            v-for="project in projects.filter(p => p.status === col.key)"
                            :key="project.id"
                            :href="route('projects.show', project.id)"
                            class="block bg-white dark:bg-neutral-900 p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700 shadow-xs transition"
                        >
                            <h5 class="text-xs font-bold text-neutral-900 dark:text-white mb-1">
                                {{ project.name }}
                            </h5>
                            <p v-if="project.category" class="text-[10px] font-medium mb-2" :style="{ color: project.category.color }">
                                {{ project.category.name }}
                            </p>
                            <div class="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-1 mb-2 overflow-hidden">
                                <div class="bg-indigo-600 h-1 rounded-full" :style="{ width: `${project.progress_percent}%` }"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-neutral-400">
                                <span>{{ project.total_tasks_count }} tasks</span>
                                <span>{{ project.milestones?.length || 0 }} milestones</span>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Create Project Modal -->
            <div
                v-if="isCreateOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isCreateOpen = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">Create Project</h3>
                        <button @click="isCreateOpen = false" class="p-1 text-neutral-400 hover:text-neutral-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submitProject" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Project Name *</label>
                            <input
                                v-model="projectForm.name"
                                type="text"
                                required
                                placeholder="e.g. ROV Stabilizer, Minesweeper Sensor Fusion"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Area / Category</label>
                                <select
                                    v-model="projectForm.category_id"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                >
                                    <option value="">None</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Status</label>
                                <select
                                    v-model="projectForm.status"
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
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Description</label>
                            <textarea
                                v-model="projectForm.description"
                                rows="2"
                                placeholder="Core objectives and scope"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Start Date</label>
                                <input
                                    v-model="projectForm.start_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Target Date</label>
                                <input
                                    v-model="projectForm.target_date"
                                    type="date"
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <!-- Milestones -->
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Initial Milestones</label>
                            <div class="space-y-1.5 mb-2">
                                <div
                                    v-for="(m, idx) in projectForm.milestones"
                                    :key="idx"
                                    class="flex items-center justify-between p-2 rounded-lg bg-neutral-50 dark:bg-neutral-800"
                                >
                                    <div>
                                        <p class="font-medium text-neutral-900 dark:text-white">{{ m.title }}</p>
                                        <p v-if="m.due_date" class="text-[10px] text-neutral-400">Due: {{ m.due_date }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeMilestone(idx)"
                                        class="text-neutral-400 hover:text-rose-500"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    v-model="newMilestoneTitle"
                                    type="text"
                                    placeholder="Milestone title"
                                    class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                                <input
                                    v-model="newMilestoneDate"
                                    type="date"
                                    class="text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                                <button
                                    type="button"
                                    @click="addMilestone"
                                    class="px-3 py-1.5 bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 rounded-lg text-neutral-700 dark:text-neutral-300 font-medium"
                                >
                                    Add
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                            <button
                                type="button"
                                @click="isCreateOpen = false"
                                class="px-4 py-2 rounded-lg text-neutral-600 hover:bg-neutral-100"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="projectForm.processing"
                                class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium"
                            >
                                Save Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

