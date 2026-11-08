<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import QuickAddModal from '@/Components/QuickAddModal.vue';
import NotificationCenter from '@/Components/NotificationCenter.vue';
import MiniFocusTimer from '@/Components/MiniFocusTimer.vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import {
    LayoutDashboard,
    Sun,
    CheckSquare,
    FolderKanban,
    Calendar,
    HeartHandshake,
    Sparkles,
    ShieldAlert,
    BarChart3,
    Settings,
    FileText,
    ChevronLeft,
    ChevronRight,
    Menu,
    X,
    Plus,
    Moon,
    LogOut,
    User as UserIcon,
    Layers,
    CheckCircle2,
    AlertCircle,
} from 'lucide-vue-next';
import { useTheme, ThemePreference } from '@/composables/useTheme';

defineProps<{
    title?: string;
}>();

const page = usePage<PageProps>();
const isSidebarCollapsed = ref(false);
const isMobileDrawerOpen = ref(false);
const isUserMenuOpen = ref(false);
const isQuickAddOpen = ref(false);
const quickAddTab = ref<'task' | 'project' | 'category' | 'phone'>('task');

const { isDark, toggleTheme, initTheme } = useTheme();

const navItems = [
    { name: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    { name: 'My Day', route: 'my-day.index', icon: Sun },
    { name: 'Briefings', route: 'briefings.index', icon: FileText },
    { name: 'Tasks', route: 'tasks.index', icon: CheckSquare },
    { name: 'Projects', route: 'projects.index', icon: FolderKanban },
    { name: 'Calendar', route: 'calendar.index', icon: Calendar },
    { name: 'Habits', route: 'habits.index', icon: Sparkles },
    { name: 'Bad Habits', route: 'bad-habits.index', icon: ShieldAlert },
    { name: 'Analytics', route: 'analytics.index', icon: BarChart3 },
    { name: 'Weekly Review', route: 'weekly-review.index', icon: HeartHandshake },
    { name: 'Settings', route: 'settings.index', icon: Settings },
];

onMounted(() => {
    const userThemePref = (page.props.auth.user?.setting?.theme as ThemePreference) || undefined;
    initTheme(userThemePref);

    const savedSidebar = localStorage.getItem('sidebar_collapsed');
    if (savedSidebar !== null) {
        isSidebarCollapsed.value = savedSidebar === 'true';
    }
});

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem('sidebar_collapsed', String(isSidebarCollapsed.value));
};

const openQuickAdd = (tab: 'task' | 'project' | 'category' | 'phone' = 'task') => {
    quickAddTab.value = tab;
    isQuickAddOpen.value = true;
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 dark:bg-neutral-950 text-neutral-900 dark:text-neutral-100 flex flex-col font-sans transition-colors duration-200">
        <!-- Top Flash Alerts -->
        <div v-if="page.props.flash?.success" class="bg-emerald-500/10 border-b border-emerald-500/20 text-emerald-800 dark:text-emerald-300 px-4 py-2 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2 max-w-7xl mx-auto w-full">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ page.props.flash.success }}</span>
            </div>
        </div>
        <div v-if="page.props.flash?.error" class="bg-rose-500/10 border-b border-rose-500/20 text-rose-800 dark:text-rose-300 px-4 py-2 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2 max-w-7xl mx-auto w-full">
                <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                <span>{{ page.props.flash.error }}</span>
            </div>
        </div>

        <div class="flex flex-1 overflow-hidden">
            <!-- Desktop Sidebar -->
            <aside
                class="hidden md:flex flex-col border-r border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 transition-all duration-200 select-none"
                :class="isSidebarCollapsed ? 'w-16' : 'w-64'"
            >
                <!-- Brand / Logo -->
                <div class="h-16 flex items-center justify-between px-4 border-b border-neutral-200 dark:border-neutral-800">
                    <Link :href="route('dashboard')" class="flex items-center gap-2.5 overflow-hidden">
                        <ApplicationLogo class="h-8 w-8 shrink-0" />
                        <span v-if="!isSidebarCollapsed" class="font-semibold text-sm tracking-tight text-neutral-900 dark:text-white truncate">
                            Operations Hub
                        </span>
                    </Link>
                    <button
                        @click="toggleSidebar"
                        class="p-1 rounded text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                        title="Toggle sidebar"
                    >
                        <ChevronLeft v-if="!isSidebarCollapsed" class="w-4 h-4" />
                        <ChevronRight v-else class="w-4 h-4" />
                    </button>
                </div>

                <!-- Navigation List -->
                <nav class="flex-1 overflow-y-auto px-2 py-4 space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.route"
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition duration-150"
                        :class="
                            route().current(item.route) || (item.route !== 'dashboard' && route().current(item.route.split('.')[0] + '.*'))
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 font-semibold'
                                : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200'
                        "
                        :title="isSidebarCollapsed ? item.name : undefined"
                    >
                        <component :is="item.icon" class="w-4 h-4 shrink-0" />
                        <span v-if="!isSidebarCollapsed" class="truncate">{{ item.name }}</span>
                    </Link>

                    <!-- Categories / Areas Section (if not collapsed) -->
                    <div v-if="!isSidebarCollapsed" class="pt-5 pb-1 px-3">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-neutral-400 uppercase tracking-wider mb-2">
                            <span>Areas</span>
                            <Link :href="route('categories.index')" class="hover:text-indigo-600 transition">
                                <Layers class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                        <div class="space-y-1">
                            <Link
                                v-for="cat in page.props.categories"
                                :key="cat.id"
                                :href="route('tasks.index', { category_id: cat.id })"
                                class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-neutral-200 transition group"
                            >
                                <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: cat.color || '#6366f1' }"></span>
                                <span class="truncate">{{ cat.name }}</span>
                            </Link>
                        </div>
                    </div>
                </nav>

                <!-- Footer / User preview in sidebar -->
                <div class="p-3 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center gap-3 px-2 py-1.5 rounded-lg bg-neutral-100/50 dark:bg-neutral-800/40">
                        <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-medium text-xs flex items-center justify-center shrink-0">
                            {{ page.props.auth.user.name.charAt(0) }}
                        </div>
                        <div v-if="!isSidebarCollapsed" class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-neutral-900 dark:text-neutral-100 truncate">
                                {{ page.props.auth.user.setting?.display_name || page.props.auth.user.name }}
                            </p>
                            <p class="text-[11px] text-neutral-400 truncate">
                                {{ page.props.auth.user.email }}
                            </p>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Mobile Drawer -->
            <div v-if="isMobileDrawerOpen" class="fixed inset-0 z-50 md:hidden flex">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="isMobileDrawerOpen = false"></div>
                <div class="relative w-72 max-w-[80vw] bg-white dark:bg-neutral-900 h-full shadow-2xl flex flex-col z-10">
                    <div class="h-16 flex items-center justify-between px-4 border-b border-neutral-200 dark:border-neutral-800">
                        <div class="flex items-center gap-2.5">
                            <ApplicationLogo class="h-8 w-8 shrink-0" />
                            <span class="font-semibold text-sm text-neutral-900 dark:text-white">Operations Hub</span>
                        </div>
                        <button @click="isMobileDrawerOpen = false" class="p-1 rounded text-neutral-400 hover:text-neutral-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.route"
                            :href="route(item.route)"
                            @click="isMobileDrawerOpen = false"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition"
                            :class="
                                route().current(item.route)
                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400'
                                    : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                            "
                        >
                            <component :is="item.icon" class="w-5 h-5 shrink-0" />
                            <span>{{ item.name }}</span>
                        </Link>

                        <!-- Areas / Categories Section on Mobile -->
                        <div v-if="page.props.categories?.length" class="pt-5 pb-1 px-1">
                            <div class="flex items-center justify-between text-[11px] font-semibold text-neutral-400 uppercase tracking-wider mb-2">
                                <span>Areas</span>
                                <Link :href="route('categories.index')" @click="isMobileDrawerOpen = false" class="hover:text-indigo-600 transition">
                                    <Layers class="w-3.5 h-3.5" />
                                </Link>
                            </div>
                            <div class="space-y-1">
                                <Link
                                    v-for="cat in page.props.categories"
                                    :key="'mob-cat-' + cat.id"
                                    :href="route('tasks.index', { category_id: cat.id })"
                                    @click="isMobileDrawerOpen = false"
                                    class="flex items-center gap-2.5 px-2 py-2 rounded-md text-xs text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-neutral-200 transition"
                                >
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: cat.color || '#6366f1' }"></span>
                                    <span class="truncate">{{ cat.name }}</span>
                                </Link>
                            </div>
                        </div>
                    </nav>

                    <div class="p-4 border-t border-neutral-200 dark:border-neutral-800">
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                        >
                            <LogOut class="w-4 h-4" /> Log Out
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Main Content Container -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                <!-- Top Header Bar -->
                <header class="h-16 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between px-4 sm:px-6 z-20 shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            @click="isMobileDrawerOpen = true"
                            class="md:hidden p-2 rounded-lg text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 shrink-0"
                        >
                            <Menu class="w-5 h-5" />
                        </button>
                        <h1 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-white truncate">
                            {{ title || 'Personal Operations Hub' }}
                        </h1>
                    </div>

                    <!-- Right Controls -->
                    <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                        <!-- Global Command Palette (Ctrl+K) -->
                        <CommandPalette @open-quick-add="openQuickAdd" />

                        <!-- Persistent Mini Focus Timer -->
                        <MiniFocusTimer />

                        <!-- Quick Add Button -->
                        <button
                            @click="openQuickAdd('task')"
                            class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                        >
                            <Plus class="w-4 h-4" />
                            <span class="hidden sm:inline">Quick Add</span>
                        </button>

                        <!-- Notification Bell & Drawer -->
                        <NotificationCenter />

                        <!-- Theme Toggle Button -->
                        <button
                            @click="toggleTheme"
                            class="p-2 rounded-lg text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            title="Toggle theme"
                        >
                            <Sun v-if="isDark" class="w-4 h-4 text-amber-400" />
                            <Moon v-else class="w-4 h-4 text-neutral-600" />
                        </button>

                        <!-- User Profile Dropdown Menu -->
                        <div class="relative">
                            <button
                                @click="isUserMenuOpen = !isUserMenuOpen"
                                class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                aria-label="User account menu"
                            >
                                <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-semibold text-xs flex items-center justify-center">
                                    {{ page.props.auth.user.name.charAt(0) }}
                                </div>
                            </button>

                            <div
                                v-if="isUserMenuOpen"
                                class="fixed inset-0 z-40"
                                @click="isUserMenuOpen = false"
                            ></div>

                            <div
                                v-if="isUserMenuOpen"
                                class="absolute right-0 mt-2 w-48 rounded-xl bg-white dark:bg-neutral-900 shadow-xl border border-neutral-200 dark:border-neutral-800 py-1 z-50 text-xs"
                                @click="isUserMenuOpen = false"
                            >
                                <div class="px-3 py-2 border-b border-neutral-100 dark:border-neutral-800">
                                    <p class="font-medium text-neutral-900 dark:text-white truncate">
                                        {{ page.props.auth.user.name }}
                                    </p>
                                    <p class="text-[11px] text-neutral-400 truncate">
                                        {{ page.props.auth.user.email }}
                                    </p>
                                </div>
                                <Link
                                    :href="route('settings.index')"
                                    class="flex items-center gap-2 px-3 py-2 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                >
                                    <Settings class="w-4 h-4" /> Preferences
                                </Link>
                                <Link
                                    :href="route('settings.data')"
                                    class="flex items-center gap-2 px-3 py-2 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                >
                                    <Layers class="w-4 h-4" /> Backups & Data
                                </Link>
                                <Link
                                    :href="route('profile.edit')"
                                    class="flex items-center gap-2 px-3 py-2 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                                >
                                    <UserIcon class="w-4 h-4" /> Account Security
                                </Link>
                                <div class="border-t border-neutral-100 dark:border-neutral-800 my-1"></div>
                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition text-left"
                                >
                                    <LogOut class="w-4 h-4" /> Log Out
                                </Link>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Body Content -->
                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    <slot />
                </main>
            </div>
        </div>

        <!-- Global Quick Add Modal -->
        <QuickAddModal :show="isQuickAddOpen" :initial-tab="quickAddTab" @close="isQuickAddOpen = false" />
    </div>
</template>
