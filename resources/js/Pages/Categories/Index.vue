<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Category } from '@/types';
import {
    Layers,
    Plus,
    Archive,
    RotateCcw,
    Trash2,
    Edit3,
    ArrowUp,
    ArrowDown,
    X,
    AlertTriangle,
    CheckSquare,
    Folder,
} from 'lucide-vue-next';

const props = defineProps<{
    categories: Category[];
}>();

const showArchived = ref(false);
const isCreateOpen = ref(false);
const editingCategory = ref<Category | null>(null);

// Category Form
const catForm = useForm({
    name: '',
    color: '#6366f1',
    icon: 'Folder',
});

const openCreate = () => {
    editingCategory.value = null;
    catForm.reset();
    catForm.color = '#6366f1';
    catForm.icon = 'Folder';
    isCreateOpen.value = true;
};

const openEdit = (cat: Category) => {
    editingCategory.value = cat;
    catForm.name = cat.name;
    catForm.color = cat.color || '#6366f1';
    catForm.icon = cat.icon || 'Folder';
    isCreateOpen.value = true;
};

const saveCategory = () => {
    if (editingCategory.value) {
        catForm.put(route('categories.update', editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                isCreateOpen.value = false;
            },
        });
    } else {
        catForm.post(route('categories.store'), {
            preserveScroll: true,
            onSuccess: () => {
                isCreateOpen.value = false;
            },
        });
    }
};

const toggleArchive = (cat: Category) => {
    router.post(route('categories.archive', cat.id), {}, { preserveScroll: true });
};

// Safe Deletion Modal
const categoryToDelete = ref<Category | null>(null);
const deleteAction = ref<'reassign' | 'keep_unassigned' | 'delete_tasks'>('keep_unassigned');
const reassignCategoryId = ref<number | ''>('');

const openDeleteModal = (cat: Category) => {
    categoryToDelete.value = cat;
    deleteAction.value = 'keep_unassigned';
    const otherCats = props.categories.filter((c) => c.id !== cat.id && !c.is_archived);
    reassignCategoryId.value = otherCats.length > 0 ? otherCats[0].id : '';
};

const confirmDelete = () => {
    if (!categoryToDelete.value) return;
    router.delete(route('categories.destroy', categoryToDelete.value.id), {
        data: {
            task_action: deleteAction.value,
            reassign_to_category_id: deleteAction.value === 'reassign' ? reassignCategoryId.value : null,
        },
        preserveScroll: true,
        onSuccess: () => {
            categoryToDelete.value = null;
        },
    });
};

// Reorder
const moveCategory = (index: number, direction: 'up' | 'down') => {
    const list = [...props.categories.filter((c) => !c.is_archived)];
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= list.length) return;

    const temp = list[index];
    list[index] = list[target];
    list[target] = temp;

    const order = list.map((c) => c.id);
    router.post(route('categories.reorder'), { order }, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout title="Areas & Categories">
        <Head title="Areas & Categories" />

        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <Layers class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Life & Work Areas</h2>
                        <p class="text-xs text-neutral-400">
                            Customizable domains for organizing your university, projects, team, and personal responsibilities
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="showArchived = !showArchived"
                        class="px-3 py-2 text-xs font-medium rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                    >
                        {{ showArchived ? 'View Active' : 'View Archived' }}
                    </button>
                    <button
                        @click="openCreate"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add Area</span>
                    </button>
                </div>
            </div>

            <!-- Categories List -->
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
                <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    <div
                        v-for="(cat, idx) in categories.filter(c => showArchived ? c.is_archived : !c.is_archived)"
                        :key="cat.id"
                        class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40 transition group"
                    >
                        <div class="flex items-center gap-3.5">
                            <span class="w-3.5 h-3.5 rounded-full shrink-0 shadow-xs" :style="{ backgroundColor: cat.color || '#6366f1' }"></span>
                            <div>
                                <h4 class="text-sm font-semibold text-neutral-900 dark:text-white flex items-center gap-2">
                                    {{ cat.name }}
                                    <span v-if="cat.is_archived" class="px-1.5 py-0.2 rounded text-[9px] uppercase font-bold bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300">
                                        Archived
                                    </span>
                                </h4>
                                <p class="text-xs text-neutral-400 mt-0.5">
                                    {{ cat.tasks_count || 0 }} tasks &bull; {{ cat.projects_count || 0 }} projects
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1">
                            <!-- Move Up/Down (only active) -->
                            <template v-if="!cat.is_archived">
                                <button
                                    v-if="idx > 0"
                                    @click="moveCategory(idx, 'up')"
                                    class="p-1.5 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                    title="Move up"
                                >
                                    <ArrowUp class="w-4 h-4" />
                                </button>
                                <button
                                    v-if="idx < categories.filter(c => !c.is_archived).length - 1"
                                    @click="moveCategory(idx, 'down')"
                                    class="p-1.5 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                    title="Move down"
                                >
                                    <ArrowDown class="w-4 h-4" />
                                </button>
                            </template>

                            <button
                                @click="openEdit(cat)"
                                class="p-1.5 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                                title="Edit Area"
                            >
                                <Edit3 class="w-4 h-4" />
                            </button>

                            <button
                                @click="toggleArchive(cat)"
                                class="p-1.5 rounded text-neutral-400 hover:text-amber-500"
                                :title="cat.is_archived ? 'Restore Area' : 'Archive Area'"
                            >
                                <RotateCcw v-if="cat.is_archived" class="w-4 h-4" />
                                <Archive v-else class="w-4 h-4" />
                            </button>

                            <button
                                @click="openDeleteModal(cat)"
                                class="p-1.5 rounded text-neutral-400 hover:text-rose-500"
                                title="Delete Area"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create / Edit Modal -->
            <div
                v-if="isCreateOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isCreateOpen = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl max-w-md w-full border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-white">
                            {{ editingCategory ? 'Edit Area' : 'Add New Area' }}
                        </h3>
                        <button @click="isCreateOpen = false" class="p-1 text-neutral-400 hover:text-neutral-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="saveCategory" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Area Name *</label>
                            <input
                                v-model="catForm.name"
                                type="text"
                                required
                                placeholder="e.g. University, ROV Sub-team, Personal"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Color</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="catForm.color"
                                        type="color"
                                        class="h-9 w-12 rounded border border-neutral-200 dark:border-neutral-800 p-0.5 bg-transparent cursor-pointer"
                                    />
                                    <span class="font-mono text-neutral-500">{{ catForm.color }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block font-medium text-neutral-700 dark:text-neutral-300 mb-1">Icon</label>
                                <select
                                    v-model="catForm.icon"
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
                                @click="isCreateOpen = false"
                                class="px-4 py-2 rounded-lg text-neutral-600 hover:bg-neutral-100"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="catForm.processing"
                                class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium"
                            >
                                Save Area
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Safe Deletion Confirmation Modal -->
            <div
                v-if="categoryToDelete"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="categoryToDelete = null"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl max-w-md w-full border border-neutral-200 dark:border-neutral-800 shadow-2xl p-6 space-y-4">
                    <div class="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                        <span class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/60">
                            <AlertTriangle class="w-6 h-6" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-neutral-900 dark:text-white">Delete Area</h3>
                            <p class="text-xs text-neutral-400">"{{ categoryToDelete.name }}"</p>
                        </div>
                    </div>

                    <p class="text-xs text-neutral-600 dark:text-neutral-300">
                        What should be done with tasks currently assigned to this area?
                    </p>

                    <div class="space-y-2 text-xs">
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-800 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800">
                            <input
                                type="radio"
                                v-model="deleteAction"
                                value="keep_unassigned"
                                class="text-indigo-600"
                            />
                            <span>Keep tasks but remove area assignment (Unassigned)</span>
                        </label>

                        <label class="flex items-center gap-2 p-2.5 rounded-lg border border-neutral-200 dark:border-neutral-800 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800">
                            <input
                                type="radio"
                                v-model="deleteAction"
                                value="reassign"
                                class="text-indigo-600"
                            />
                            <div class="flex-1">
                                <span>Reassign tasks to another area:</span>
                                <select
                                    v-if="deleteAction === 'reassign'"
                                    v-model="reassignCategoryId"
                                    class="mt-1.5 w-full text-xs rounded-md border-neutral-200 dark:border-neutral-700 dark:bg-neutral-900"
                                >
                                    <option
                                        v-for="cat in categories.filter(c => c.id !== categoryToDelete?.id && !c.is_archived)"
                                        :key="cat.id"
                                        :value="cat.id"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </div>
                        </label>

                        <label class="flex items-center gap-2 p-2.5 rounded-lg border border-rose-200 dark:border-rose-900/60 bg-rose-50/20 text-rose-700 dark:text-rose-400 cursor-pointer">
                            <input
                                type="radio"
                                v-model="deleteAction"
                                value="delete_tasks"
                                class="text-rose-600"
                            />
                            <span>Delete associated tasks along with this area</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                        <button
                            type="button"
                            @click="categoryToDelete = null"
                            class="px-4 py-2 rounded-lg text-neutral-600 hover:bg-neutral-100"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="confirmDelete"
                            class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-medium"
                        >
                            Confirm Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

