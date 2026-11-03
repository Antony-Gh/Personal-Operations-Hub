<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Category, Habit, UserSetting } from '@/types';
import {
    Settings as SettingsIcon,
    Save,
    Database,
    Layers,
    Sparkles,
    Globe,
    Clock,
    Plus,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps<{
    setting: UserSetting;
    categories: Category[];
    habits: Habit[];
}>();

const settingForm = useForm({
    display_name: props.setting.display_name || '',
    theme: props.setting.theme || 'light',
    language: props.setting.language || 'en',
    week_start: props.setting.week_start ?? 1,
    date_format: props.setting.date_format || 'YYYY-MM-DD',
    time_format: props.setting.time_format || '24h',
    default_priority: props.setting.default_priority || 'medium',
    default_task_duration: props.setting.default_task_duration || 30,
    phone_usage_target_minutes: props.setting.phone_usage_target_minutes || 60,
    weekly_review_prompts: [...(props.setting.weekly_review_prompts || [])],
});

const newPromptText = ref('');

const addPrompt = () => {
    if (!newPromptText.value.trim()) return;
    settingForm.weekly_review_prompts.push(newPromptText.value.trim());
    newPromptText.value = '';
};

const removePrompt = (index: number) => {
    settingForm.weekly_review_prompts.splice(index, 1);
};

const submitSettings = () => {
    settingForm.put(route('settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Settings & Customization" />

    <AuthenticatedLayout title="Settings">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header with Data Management Quick Link -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <SettingsIcon class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">Workspace Preferences</h2>
                        <p class="text-xs text-neutral-400">
                            Configure your layout, scheduling formats, defaults, and reflection questions
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('settings.data')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-800 dark:text-neutral-200 text-xs font-medium transition"
                    >
                        <Database class="w-4 h-4" />
                        <span>Data & Backups</span>
                    </Link>
                </div>
            </div>

            <!-- Settings Form -->
            <form @submit.prevent="submitSettings" class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs space-y-6 text-xs">
                <!-- Profile & Theme -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        Profile & Appearance
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Display Name</label>
                            <input
                                v-model="settingForm.display_name"
                                type="text"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block font-medium mb-1">Theme</label>
                            <select
                                v-model="settingForm.theme"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="light">Light Theme</option>
                                <option value="dark">Dark Theme</option>
                                <option value="system">System Default</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Locale & Scheduling Defaults -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        Calendar & Scheduling Rules
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-medium mb-1">Week Starts On</label>
                            <select
                                v-model.number="settingForm.week_start"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option :value="1">Monday</option>
                                <option :value="0">Sunday</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Date Format</label>
                            <select
                                v-model="settingForm.date_format"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="YYYY-MM-DD">YYYY-MM-DD (ISO)</option>
                                <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Time Format</label>
                            <select
                                v-model="settingForm.time_format"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="24h">24-hour (14:00)</option>
                                <option value="12h">12-hour (2:00 PM)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <div>
                            <label class="block font-medium mb-1">Default Task Priority</label>
                            <select
                                v-model="settingForm.default_priority"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Default Task Duration (min)</label>
                            <input
                                v-model.number="settingForm.default_task_duration"
                                type="number"
                                min="5"
                                step="5"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Daily Phone Target (min)</label>
                            <input
                                v-model.number="settingForm.phone_usage_target_minutes"
                                type="number"
                                min="0"
                                max="1440"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- Weekly Review Prompts Customization -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white border-b border-neutral-100 dark:border-neutral-800 pb-2">
                        Weekly Review Reflection Prompts
                    </h3>
                    <p class="text-neutral-400 text-[11px]">
                        Tailor the questions that guide your end-of-week review
                    </p>

                    <div class="space-y-2">
                        <div
                            v-for="(prompt, idx) in settingForm.weekly_review_prompts"
                            :key="idx"
                            class="flex items-center gap-2"
                        >
                            <span class="w-6 text-neutral-400 font-mono">{{ idx + 1 }}.</span>
                            <input
                                v-model="settingForm.weekly_review_prompts[idx]"
                                type="text"
                                class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                            <button
                                type="button"
                                @click="removePrompt(idx)"
                                class="p-1.5 text-neutral-400 hover:text-rose-500 rounded"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <input
                            v-model="newPromptText"
                            @keydown.enter.prevent="addPrompt"
                            type="text"
                            placeholder="Add a new custom reflection question..."
                            class="flex-1 text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                        />
                        <button
                            type="button"
                            @click="addPrompt"
                            class="px-3 py-1.5 bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 rounded-lg text-neutral-700 dark:text-neutral-300 font-medium"
                        >
                            Add Question
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4 border-t border-neutral-100 dark:border-neutral-800">
                    <button
                        type="submit"
                        :disabled="settingForm.processing"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-sm transition disabled:opacity-50"
                    >
                        <Save class="w-4 h-4" />
                        <span>Save Preferences</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

