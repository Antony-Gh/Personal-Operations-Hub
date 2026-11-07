<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BackupEntry } from '@/types';
import { formatRelativeTime } from '@/utils/date';
import axios from 'axios';
import {
    Database,
    Download,
    Upload,
    FileSpreadsheet,
    FileJson,
    ChevronLeft,
    AlertTriangle,
    ShieldCheck,
    Plus,
    Trash2,
    RotateCcw,
    CheckCircle2,
    HardDrive,
    X,
} from 'lucide-vue-next';

interface ImportSummary {
    version: string;
    exported_at: string;
    categories_count: number;
    projects_count: number;
    tasks_count: number;
    habits_count: number;
    calendar_events_count: number;
    weekly_reviews_count: number;
}

const props = defineProps<{
    backups?: BackupEntry[];
    lastBackup?: BackupEntry | null;
    backupConfig?: {
        backup_enabled: boolean;
        backup_retention_days: number;
        directory: string;
    };
}>();

// Managed SQLite Backup Actions
const isCreatingBackup = ref(false);
const createManagedBackup = () => {
    isCreatingBackup.value = true;
    router.post(
        route('settings.backups.create'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isCreatingBackup.value = false;
            },
        }
    );
};

const deleteManagedBackup = (entry: BackupEntry) => {
    if (confirm(`Delete backup snapshot "${entry.filename}"?`)) {
        router.delete(route('settings.backups.delete', { filename: entry.filename }), {
            preserveScroll: true,
        });
    }
};

// Safe Restore Modal
const restoreTarget = ref<BackupEntry | null>(null);
const restoreForm = useForm({
    filename: '',
    confirmation: '',
});

const openRestoreModal = (entry: BackupEntry) => {
    restoreTarget.value = entry;
    restoreForm.filename = entry.filename;
    restoreForm.confirmation = '';
};

const submitRestore = () => {
    if (restoreForm.confirmation !== 'RESTORE') return;
    restoreForm.post(route('settings.backups.restore'), {
        preserveScroll: true,
        onSuccess: () => {
            restoreTarget.value = null;
            restoreForm.reset();
        },
    });
};

// JSON Import Preview Workflow
const importFile = ref<File | null>(null);
const importPreview = ref<ImportSummary | null>(null);
const importRaw = ref<any>(null);
const isPreviewLoading = ref(false);
const previewError = ref<string | null>(null);
const importMode = ref<'merge' | 'replace'>('merge');
const isImporting = ref(false);

const onFileSelected = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    importFile.value = input.files[0];
    previewError.value = null;
    isPreviewLoading.value = true;

    const formData = new FormData();
    formData.append('backup_file', importFile.value);

    try {
        const response = await axios.post(route('settings.import.preview'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (response.data.valid) {
            importPreview.value = response.data.summary;
            importRaw.value = response.data.raw;
        }
    } catch (err: any) {
        previewError.value = err.response?.data?.message || 'Could not parse the selected backup file.';
        importPreview.value = null;
    } finally {
        isPreviewLoading.value = false;
    }
};

const executeImport = () => {
    if (!importRaw.value) return;

    if (importMode.value === 'replace') {
        const confirmed = confirm(
            'WARNING: Replace mode will overwrite your current tasks, projects, categories, and reviews with the backup contents. Are you completely sure?'
        );
        if (!confirmed) return;
    }

    isImporting.value = true;
    router.post(
        route('settings.import.process'),
        {
            payload: importRaw.value,
            mode: importMode.value,
        },
        {
            onFinish: () => {
                isImporting.value = false;
                importPreview.value = null;
                importFile.value = null;
            },
        }
    );
};
</script>

<template>
    <AuthenticatedLayout title="Data Management">
        <Head title="Data Management & Backups" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Back to Settings -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('settings.index')"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-neutral-900 dark:hover:text-white transition"
                >
                    <ChevronLeft class="w-4 h-4" />
                    <span>Back to Settings</span>
                </Link>
            </div>

            <!-- Header -->
            <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <Database class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Data Ownership & Verified Backups</h2>
                        <p class="text-xs text-neutral-400">
                            Automated local SQLite snapshots with integrity verification, JSON/CSV exports, and safe restore
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="isCreatingBackup"
                    @click="createManagedBackup"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition disabled:opacity-50"
                >
                    <Plus class="w-4 h-4" />
                    <span>{{ isCreatingBackup ? 'Backing up...' : 'Create Verified Backup Now' }}</span>
                </button>
            </div>

            <!-- Automated Local SQLite Backups Section -->
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-100 dark:border-neutral-800 pb-3">
                    <div class="flex items-center gap-2">
                        <HardDrive class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <h3 class="text-sm font-bold text-neutral-900 dark:text-white">Managed Local SQLite Snapshots</h3>
                            <p class="text-[11px] text-neutral-400">
                                Automatic schedule: {{ backupConfig?.backup_enabled ? 'Enabled' : 'Paused' }} • Retention: {{ backupConfig?.backup_retention_days ?? 14 }} days
                            </p>
                        </div>
                    </div>

                    <div v-if="lastBackup" class="text-xs flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-medium">
                        <CheckCircle2 class="w-4 h-4" />
                        <span>Last verified: {{ formatRelativeTime(lastBackup.created_at) }}</span>
                    </div>
                </div>

                <p v-if="backupConfig?.directory" class="text-[11px] text-neutral-400 font-mono truncate">
                    Local path: {{ backupConfig.directory }}
                </p>

                <div v-if="!backups || backups.length === 0" class="py-8 text-center text-xs text-neutral-400 border border-dashed border-neutral-200 dark:border-neutral-800 rounded-xl">
                    No managed SQLite snapshots created yet. Click <strong>Create Verified Backup Now</strong> above to capture an integrity-checked snapshot.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-neutral-400 border-b border-neutral-100 dark:border-neutral-800">
                            <tr>
                                <th class="py-2 pr-3">Snapshot File</th>
                                <th class="py-2 px-3">Created</th>
                                <th class="py-2 px-3">Trigger</th>
                                <th class="py-2 px-3">Size</th>
                                <th class="py-2 px-3">Integrity</th>
                                <th class="py-2 pl-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            <tr v-for="b in backups" :key="b.filename" class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40">
                                <td class="py-2.5 pr-3 font-mono text-[11px] text-neutral-800 dark:text-neutral-200">
                                    {{ b.filename }}
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap text-neutral-500">
                                    {{ new Date(b.created_at).toLocaleString() }}
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap capitalize text-neutral-500">
                                    {{ b.trigger.replace('_', ' ') }}
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap text-neutral-500">
                                    {{ b.size_human }}
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase"
                                        :class="
                                            b.integrity_status === 'ok'
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                                        "
                                    >
                                        {{ b.integrity_status }}
                                    </span>
                                </td>
                                <td class="py-2.5 pl-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <a
                                            :href="route('settings.backups.download', { filename: b.filename })"
                                            class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                                        >
                                            Download
                                        </a>
                                        <button
                                            type="button"
                                            @click="openRestoreModal(b)"
                                            class="inline-flex items-center gap-0.5 text-amber-600 dark:text-amber-400 hover:underline font-medium"
                                        >
                                            <RotateCcw class="w-3 h-3" />
                                            <span>Restore</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteManagedBackup(b)"
                                            class="text-neutral-400 hover:text-rose-500"
                                            title="Delete snapshot"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Export Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Full JSON Backup -->
                <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 w-fit mb-3">
                            <FileJson class="w-5 h-5" />
                        </div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white mb-1">Full System Archive</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-4">
                            All your tasks, projects, categories, habits, reviews, and settings in standard JSON format.
                        </p>
                    </div>
                    <a
                        :href="route('settings.export.json')"
                        class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>Download JSON</span>
                    </a>
                </div>

                <!-- CSV Tasks Export -->
                <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 w-fit mb-3">
                            <FileSpreadsheet class="w-5 h-5" />
                        </div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white mb-1">Tasks Spreadsheet (CSV)</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-4">
                            Formatted table of all tasks with dates, durations, priorities, and categories for Excel/Sheets.
                        </p>
                    </div>
                    <a
                        :href="route('settings.export.tasks-csv')"
                        class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium transition"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>Download CSV</span>
                    </a>
                </div>

                <!-- Raw SQLite Backup -->
                <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 w-fit mb-3">
                            <Database class="w-5 h-5" />
                        </div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white mb-1">Raw Database File</h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-4">
                            Direct copy of the active SQLite database file for offline storage and portable backups.
                        </p>
                    </div>
                    <a
                        :href="route('settings.backup.database')"
                        class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-900 dark:bg-neutral-700 dark:hover:bg-neutral-600 text-white text-xs font-medium transition"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>Download SQLite</span>
                    </a>
                </div>
            </div>

            <!-- Import Workflow with Preview -->
            <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs space-y-5">
                <div class="flex items-center gap-2 border-b border-neutral-100 dark:border-neutral-800 pb-3">
                    <Upload class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900 dark:text-white">Import Backup Archive</h3>
                        <p class="text-xs text-neutral-400">
                            Upload a JSON backup file to preview contents before committing to the database
                        </p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                        Select Backup JSON File
                    </label>
                    <input
                        type="file"
                        accept=".json,.txt"
                        @change="onFileSelected"
                        class="block w-full text-xs text-neutral-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                    />
                </div>

                <div v-if="previewError" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 rounded-xl text-xs flex items-center gap-2">
                    <AlertTriangle class="w-4 h-4 shrink-0" />
                    <span>{{ previewError }}</span>
                </div>

                <div v-if="importPreview" class="p-4 bg-neutral-50 dark:bg-neutral-800/40 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            <ShieldCheck class="w-4 h-4" />
                            <span>Valid Backup Archive (Version {{ importPreview.version }})</span>
                        </div>
                        <span class="text-[11px] text-neutral-400">Exported: {{ importPreview.exported_at }}</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div class="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <span class="text-neutral-400 block text-[10px]">Areas / Categories</span>
                            <span class="text-base font-bold text-neutral-900 dark:text-white">{{ importPreview.categories_count }}</span>
                        </div>
                        <div class="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <span class="text-neutral-400 block text-[10px]">Projects</span>
                            <span class="text-base font-bold text-neutral-900 dark:text-white">{{ importPreview.projects_count }}</span>
                        </div>
                        <div class="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <span class="text-neutral-400 block text-[10px]">Tasks</span>
                            <span class="text-base font-bold text-neutral-900 dark:text-white">{{ importPreview.tasks_count }}</span>
                        </div>
                        <div class="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <span class="text-neutral-400 block text-[10px]">Habits</span>
                            <span class="text-base font-bold text-neutral-900 dark:text-white">{{ importPreview.habits_count }}</span>
                        </div>
                        <div class="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <span class="text-neutral-400 block text-[10px]">Events</span>
                            <span class="text-base font-bold text-neutral-900 dark:text-white">{{ importPreview.calendar_events_count }}</span>
                        </div>
                        <div class="p-2.5 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <span class="text-neutral-400 block text-[10px]">Reviews</span>
                            <span class="text-base font-bold text-neutral-900 dark:text-white">{{ importPreview.weekly_reviews_count }}</span>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-neutral-200 dark:border-neutral-700 text-xs">
                        <label class="block font-semibold text-neutral-800 dark:text-neutral-200">Import Mode</label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <label class="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer">
                                <input type="radio" v-model="importMode" value="merge" class="text-indigo-600" />
                                <div>
                                    <span class="font-medium text-neutral-900 dark:text-white">Merge Mode (Safe)</span>
                                    <p class="text-[10px] text-neutral-400">Adds new records without deleting existing ones</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer">
                                <input type="radio" v-model="importMode" value="replace" class="text-indigo-600" />
                                <div>
                                    <span class="font-medium text-neutral-900 dark:text-white">Replace Mode (Fresh Restore)</span>
                                    <p class="text-[10px] text-neutral-400">Replaces current records with this backup</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="importPreview = null"
                            class="px-4 py-2 rounded-lg text-neutral-600 hover:bg-neutral-200/50 text-xs"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="executeImport"
                            :disabled="isImporting"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition disabled:opacity-50"
                        >
                            Confirm & Process Import
                        </button>
                    </div>
                </div>
            </div>

            <!-- Safe Restore Confirmation Modal -->
            <div
                v-if="restoreTarget"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="restoreTarget = null"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-2xl p-6 max-w-md w-full border border-neutral-200 dark:border-neutral-800 shadow-2xl space-y-4 text-xs">
                    <div class="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-3">
                        <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                            <AlertTriangle class="w-5 h-5" />
                            <h4 class="text-sm font-bold text-neutral-900 dark:text-white">Safe Database Restore</h4>
                        </div>
                        <button type="button" @click="restoreTarget = null" class="p-1 text-neutral-400 hover:text-neutral-600">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <p class="text-neutral-600 dark:text-neutral-400 leading-relaxed">
                        You are about to restore your local SQLite database from <strong class="font-mono text-neutral-900 dark:text-white">{{ restoreTarget.filename }}</strong>.
                    </p>

                    <div class="p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-300 text-[11px]">
                        <strong>Zero Data Loss Safeguard:</strong> Before replacing <code class="font-mono">database.sqlite</code>, the system automatically creates a verified <code class="font-mono">pre_restore</code> snapshot of your current database.
                    </div>

                    <form @submit.prevent="submitRestore" class="space-y-3">
                        <div>
                            <label class="block font-semibold text-neutral-800 dark:text-neutral-200 mb-1">
                                Type <code class="font-mono text-rose-600">RESTORE</code> to confirm:
                            </label>
                            <input
                                v-model="restoreForm.confirmation"
                                type="text"
                                placeholder="RESTORE"
                                class="w-full text-xs font-mono rounded-lg border-neutral-200 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                            />
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="restoreTarget = null"
                                class="px-3 py-1.5 rounded-lg text-neutral-600 hover:bg-neutral-100"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="restoreForm.confirmation !== 'RESTORE' || restoreForm.processing"
                                class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold disabled:opacity-40"
                            >
                                Restore Snapshot
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
