<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import {
    LayoutDashboard,
    Sun,
    FolderKanban,
    Sparkles,
    HeartHandshake,
    ArrowRight,
    CheckCircle2,
} from 'lucide-vue-next';

defineProps<{
    canLogin?: boolean;
}>();
</script>

<template>
    <Head title="Personal Operations Hub" />

    <div class="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 flex flex-col justify-between selection:bg-indigo-600 selection:text-white transition-colors duration-200">
        <!-- Header -->
        <header class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <ApplicationLogo class="h-9 w-9" />
                <span class="text-base font-bold tracking-tight text-neutral-900 dark:text-white">
                    Personal Operations Hub
                </span>
            </div>

            <nav v-if="canLogin !== false" class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth?.user"
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition"
                >
                    <span>Open Dashboard</span>
                    <ArrowRight class="w-4 h-4" />
                </Link>
                <Link
                    v-else
                    :href="route('login')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition"
                >
                    <span>Sign In</span>
                    <ArrowRight class="w-4 h-4" />
                </Link>
            </nav>
        </header>

        <!-- Hero Section -->
        <main class="flex-1 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex flex-col items-center justify-center text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-medium mb-6">
                <Sparkles class="w-3.5 h-3.5" />
                <span>Tailored for Engineering, Academia, and Leadership</span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-neutral-900 dark:text-white max-w-3xl leading-tight">
                A calm, centralized system for your responsibilities, goals, and recovery.
            </h1>

            <p class="mt-4 text-sm sm:text-base text-neutral-600 dark:text-neutral-400 max-w-2xl">
                Instead of hunting through scattered spreadsheets or endless to-do lists, manage your university coursework, engineering teams, and spiritual practices with intentional daily planning.
            </p>

            <div class="mt-8 flex items-center gap-3">
                <Link
                    :href="$page.props.auth?.user ? route('dashboard') : route('login')"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-semibold shadow-sm transition"
                >
                    <span>{{ $page.props.auth?.user ? 'Go to Dashboard' : 'Enter Workspace' }}</span>
                    <ArrowRight class="w-4 h-4" />
                </Link>
                <Link
                    v-if="$page.props.auth?.user"
                    :href="route('my-day.index')"
                    class="inline-flex items-center gap-2 px-5 py-3 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-900 text-neutral-700 dark:text-neutral-300 text-xs sm:text-sm font-medium transition"
                >
                    <span>Plan Today</span>
                </Link>
            </div>

            <!-- Core Pillar Cards -->
            <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-6 text-left w-full">
                <!-- Card 1 -->
                <div class="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 w-fit mb-4">
                        <Sun class="w-5 h-5" />
                    </div>
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white mb-1">
                        Intentional Daily Planning
                    </h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        Pick up to three main priorities for today. Keep fixed commitments, fallback contingency plans, and recovery time in focus.
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 w-fit mb-4">
                        <FolderKanban class="w-5 h-5" />
                    </div>
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white mb-1">
                        Flexible Areas & Projects
                    </h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        Organize by life domains (University, Software, ROV, Minesweeper). Track deliverables with milestones and Kanban boards.
                    </p>
                </div>

                <!-- Card 3 -->
                <div class="p-6 rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-xs">
                    <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 w-fit mb-4">
                        <HeartHandshake class="w-5 h-5" />
                    </div>
                    <h3 class="text-sm font-bold text-neutral-900 dark:text-white mb-1">
                        Habits & Weekly Review
                    </h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        Gentle check-ins for prayer, quiet reflection, and reading. Guided weekly reviews to calibrate workload without guilt.
                    </p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full max-w-7xl mx-auto px-4 py-6 text-center text-xs text-neutral-400 dark:text-neutral-500 border-t border-neutral-200/60 dark:border-neutral-800/60">
            <span>Personal Operations Hub &bull; Built with Laravel, Vue 3, Inertia, and SQLite</span>
        </footer>
    </div>
</template>
