<script setup lang="ts">
import { onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { Sun, Moon } from 'lucide-vue-next';
import { useTheme } from '@/composables/useTheme';

const { isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme();
});
</script>

<template>
    <div
        class="min-h-screen flex flex-col justify-between bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 transition-colors duration-200"
    >
        <!-- Top Navigation with Brand & Theme Toggle -->
        <header class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <Link :href="route('login')" class="flex items-center gap-2.5 group">
                <ApplicationLogo class="h-8 w-8 transition-transform group-hover:scale-105" />
                <span class="text-sm font-bold tracking-tight text-neutral-900 dark:text-white">
                    Personal Operations Hub
                </span>
            </Link>

            <button
                @click="toggleTheme"
                class="p-2 rounded-xl text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-900 transition"
                title="Toggle Theme"
                type="button"
                aria-label="Toggle dark/light theme"
            >
                <Sun v-if="isDark" class="w-4 h-4 text-amber-400" />
                <Moon v-else class="w-4 h-4 text-neutral-600" />
            </button>
        </header>

        <!-- Main Form Container -->
        <main class="flex-1 flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-md">
                <div
                    class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200/80 dark:border-neutral-800 shadow-xl shadow-neutral-900/5 p-6 sm:p-8 transition-all"
                >
                    <slot />
                </div>
            </div>
        </main>

        <!-- Minimalist Footer -->
        <footer class="w-full max-w-7xl mx-auto px-4 py-4 text-center text-xs text-neutral-400 dark:text-neutral-500">
            <span>Personal Operations Hub &bull; Private Productivity Workspace</span>
        </footer>
    </div>
</template>
