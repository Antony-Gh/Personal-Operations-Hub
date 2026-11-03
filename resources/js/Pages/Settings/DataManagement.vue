<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';
import {
    Database,
    Download,
    Upload,
    FileSpreadsheet,
    FileJson,
    ChevronLeft,
    CheckCircle2,
    AlertTriangle,
    RefreshCw,
    ShieldCheck,
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
    <Head title="Data Management & Backups" />

    <AuthenticatedLayout title="Data Management">
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
            <div class="bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs flex items-center gap-3">
                <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Database class="w-6 h-6" />
                </span>
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Data Ownership & Backups</h2>
                    <p class="text-xs text-neutral-400">
                        Export full JSON archives, download spreadsheets, or safely restore from backups
                    </p>
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
                            Direct copy of the active SQLite database file for server-level backups and disaster recovery.
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

                <!-- File Input -->
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

                <!-- Error alert -->
                <div v-if="previewError" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 rounded-xl text-xs flex items-center gap-2">
                    <AlertTriangle class="w-4 h-4 shrink-0" />
                    <span>{{ previewError }}</span>
                </div>

                <!-- Preview Summary Box (if valid) -->
                <div v-if="importPreview" class="p-4 bg-neutral-50 dark:bg-neutral-800/40 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            <ShieldCheck class="w-4 h-4" />
                            <span>Valid Backup Archive (Version {{ importPreview.version }})</span>
                        </div>
                        <span class="text-[11px] text-neutral-400">Exported: {{ importPreview.exported_at }}</span>
                    </div>

                    <!-- Statistics Matrix -->
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

                    <!-- Import Strategy Selection -->
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
        </div>
    </AuthenticatedLayout>
</template>

