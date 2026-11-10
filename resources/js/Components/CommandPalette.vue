<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { PageProps } from '@/types';
import { useTheme } from '@/composables/useTheme';
import { useFocusTimer } from '@/composables/useFocusTimer';
import { formatTime12Hour } from '@/utils/date';
import { previewNaturalTaskTokens } from '@/utils/naturalTaskParser';
import {
    Search,
    CheckSquare,
    FolderKanban,
    Flag,
    Sparkles,
    ShieldAlert,
    BarChart3,
    Plus,
    Timer,
    Sun,
    Moon,
    LayoutDashboard,
    Calendar,
    Settings,
    Database,
    FileText,
    ArrowRight,
} from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'open-quick-add', tab: 'task' | 'project'): void;
}>();

const page = usePage<PageProps>();
const { toggleTheme, isDark } = useTheme();
const { startFocusSession } = useFocusTimer();

const isOpen = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const searchInputRef = ref<HTMLInputElement | null>(null);
const isSearching = ref(false);

const remoteResults = ref<{
    tasks: any[];
    projects: any[];
    milestones: any[];
    habits: any[];
    bad_habits: any[];
}>({
    tasks: [],
    projects: [],
    milestones: [],
    habits: [],
    bad_habits: [],
});

let debounceHandle: ReturnType<typeof setTimeout> | null = null;

const quickActions = computed(() => [
    {
        id: 'act-new-task',
        group: 'Quick Actions',
        title: 'Create New Task',
        subtitle: 'Open Quick Add modal',
        icon: Plus,
        run: () => emit('open-quick-add', 'task'),
    },
    {
        id: 'act-new-project',
        group: 'Quick Actions',
        title: 'Create New Project',
        subtitle: 'Start tracking a multi-step project',
        icon: FolderKanban,
        run: () => emit('open-quick-add', 'project'),
    },
    {
        id: 'act-start-focus',
        group: 'Quick Actions',
        title: `Start ${page.props.auth.user?.setting?.focus_work_minutes || 25}m Focus Timer`,
        subtitle: 'Pomodoro deep-work session',
        icon: Timer,
        run: () => {
            void startFocusSession({
                mode: 'focus',
                planned_minutes: page.props.auth.user?.setting?.focus_work_minutes || 25,
            });
        },
    },
    {
        id: 'act-toggle-theme',
        group: 'Quick Actions',
        title: isDark.value ? 'Switch to Light Mode' : 'Switch to Dark Mode',
        subtitle: 'Toggle visual appearance',
        icon: isDark.value ? Sun : Moon,
        run: () => toggleTheme(),
    },
    {
        id: 'nav-dashboard',
        group: 'Navigation',
        title: 'Go to Dashboard',
        subtitle: 'Overview & daily priorities',
        icon: LayoutDashboard,
        run: () => router.visit(route('dashboard')),
    },
    {
        id: 'nav-my-day',
        group: 'Navigation',
        title: 'Go to My Day',
        subtitle: 'Daily planning & top 3 priorities',
        icon: Sun,
        run: () => router.visit(route('my-day.index')),
    },
    {
        id: 'nav-briefings',
        group: 'Navigation',
        title: 'Open Daily Briefing & Weekly Digest',
        subtitle: 'Morning briefing & weekly progress summary',
        icon: FileText,
        run: () => router.visit(route('briefings.index')),
    },
    {
        id: 'nav-tasks-kanban',
        group: 'Navigation',
        title: 'Open Tasks Kanban Board',
        subtitle: 'Visual drag-and-drop task columns',
        icon: CheckSquare,
        run: () => router.visit(route('tasks.index', { view: 'kanban' })),
    },
    {
        id: 'nav-calendar',
        group: 'Navigation',
        title: 'Go to Calendar & .ics Feed',
        subtitle: 'Schedule & local calendar subscription',
        icon: Calendar,
        run: () => router.visit(route('calendar.index')),
    },
    {
        id: 'nav-bad-habits',
        group: 'Navigation',
        title: 'Go to Bad Habits & Recovery',
        subtitle: 'Clean streaks, trigger cues, daily caps & slip reflections',
        icon: ShieldAlert,
        run: () => router.visit(route('bad-habits.index')),
    },
    {
        id: 'nav-analytics',
        group: 'Navigation',
        title: 'Go to Personal Analytics & Insights',
        subtitle: 'Estimation accuracy, deep work by area & screen-time correlations',
        icon: BarChart3,
        run: () => router.visit(route('analytics.index')),
    },
    {
        id: 'nav-backups',
        group: 'Navigation',
        title: 'Manage Automated SQLite Backups',
        subtitle: 'Create, download, or restore database snapshots',
        icon: Database,
        run: () => router.visit(route('settings.data')),
    },
    {
        id: 'nav-settings',
        group: 'Navigation',
        title: 'Notification & Timezone Settings',
        subtitle: 'Quiet hours, sounds, reminders, focus defaults',
        icon: Settings,
        run: () => router.visit(route('settings.index')),
    },
]);

const flattenedItems = computed(() => {
    const rawTrimmed = query.value.trim();
    const q = rawTrimmed.toLowerCase();
    const list: {
        id: string;
        group: string;
        title: string;
        subtitle?: string;
        badge?: string;
        icon: any;
        run: () => void;
    }[] = [];

    if (rawTrimmed.length > 2) {
        const parsed = previewNaturalTaskTokens(rawTrimmed);
        const tokenHints = parsed.tokens.map((t) => t.label).join(' • ');
        list.push({
            id: 'smart-quick-capture',
            group: 'Smart Quick Capture',
            title: `Capture Task: "${parsed.cleanTitle || rawTrimmed}"`,
            subtitle: tokenHints || 'Press Enter to instant-create task (supports !high ~45m #Area +Project tomorrow 17:00)',
            badge: parsed.tokens.length > 0 ? 'Smart' : undefined,
            icon: Plus,
            run: () => {
                router.post(route('tasks.quick-capture'), { input: rawTrimmed }, { preserveScroll: true });
            },
        });
    }

    const matchingStatic = quickActions.value.filter(
        (a) => !q || a.title.toLowerCase().includes(q) || a.subtitle.toLowerCase().includes(q)
    );
    list.push(...matchingStatic);

    for (const t of remoteResults.value.tasks) {
        const timePart = t.due_time ? ` ${formatTime12Hour(t.due_time)}` : '';
        const duePart = t.due_date ? `Due ${t.due_date}${timePart}` : (t.due_time ? `Due ${formatTime12Hour(t.due_time)}` : null);
        list.push({
            id: `task-${t.id}`,
            group: 'Tasks',
            title: t.title,
            subtitle: [t.project, t.category, duePart].filter(Boolean).join(' • '),
            badge: t.priority,
            icon: CheckSquare,
            run: () => router.visit(t.url),
        });
    }

    for (const p of remoteResults.value.projects) {
        list.push({
            id: `project-${p.id}`,
            group: 'Projects',
            title: p.name,
            subtitle: [p.category, p.target_date ? `Target ${p.target_date}` : p.status].filter(Boolean).join(' • '),
            icon: FolderKanban,
            run: () => router.visit(p.url),
        });
    }

    for (const m of remoteResults.value.milestones) {
        const mTimePart = m.due_time ? ` ${formatTime12Hour(m.due_time)}` : '';
        const mDuePart = m.due_date ? `Due ${m.due_date}${mTimePart}` : (m.due_time ? `Due ${formatTime12Hour(m.due_time)}` : null);
        list.push({
            id: `milestone-${m.id}`,
            group: 'Milestones',
            title: m.title,
            subtitle: [m.project_name, mDuePart].filter(Boolean).join(' • '),
            icon: Flag,
            run: () => router.visit(m.url),
        });
    }

    for (const h of remoteResults.value.habits) {
        list.push({
            id: `habit-${h.id}`,
            group: 'Habits',
            title: h.name,
            subtitle: `${h.category} • ${h.frequency}`,
            icon: Sparkles,
            run: () => router.visit(h.url),
        });
    }

    for (const bh of remoteResults.value.bad_habits) {
        list.push({
            id: `bad-habit-${bh.id}`,
            group: 'Bad Habits',
            title: bh.name,
            subtitle: [bh.category, bh.replacement_action ? `Instead: ${bh.replacement_action}` : null].filter(Boolean).join(' • '),
            icon: ShieldAlert,
            run: () => router.visit(bh.url),
        });
    }

    return list;
});

const groupedItems = computed(() => {
    const groups: { name: string; items: { entry: (typeof flattenedItems.value)[number]; flatIndex: number }[] }[] = [];
    const map = new Map<string, { entry: (typeof flattenedItems.value)[number]; flatIndex: number }[]>();

    flattenedItems.value.forEach((entry, idx) => {
        if (!map.has(entry.group)) {
            const arr: { entry: (typeof flattenedItems.value)[number]; flatIndex: number }[] = [];
            map.set(entry.group, arr);
            groups.push({ name: entry.group, items: arr });
        }
        map.get(entry.group)!.push({ entry, flatIndex: idx });
    });

    return groups;
});

const fetchRemote = async (q: string) => {
    isSearching.value = true;
    try {
        const res = await axios.get(route('command-palette.search'), { params: { q } });
        remoteResults.value = {
            tasks: res.data.tasks || [],
            projects: res.data.projects || [],
            milestones: res.data.milestones || [],
            habits: res.data.habits || [],
            bad_habits: res.data.bad_habits || [],
        };
    } catch {
        // Ignore transient errors
    } finally {
        isSearching.value = false;
    }
};

const openPalette = () => {
    isOpen.value = true;
    query.value = '';
    selectedIndex.value = 0;
    void fetchRemote('');
    nextTick(() => {
        searchInputRef.value?.focus();
    });
};

const closePalette = () => {
    isOpen.value = false;
};

watch(query, (nextQ) => {
    selectedIndex.value = 0;
    if (debounceHandle) {
        clearTimeout(debounceHandle);
    }
    debounceHandle = setTimeout(() => {
        void fetchRemote(nextQ);
    }, 160);
});

const executeItem = (idx: number) => {
    const item = flattenedItems.value[idx];
    if (!item) {
        return;
    }
    closePalette();
    item.run();
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (isOpen.value) {
            closePalette();
        } else {
            openPalette();
        }
        return;
    }

    if (!isOpen.value) {
        return;
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        closePalette();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (flattenedItems.value.length > 0) {
            selectedIndex.value = (selectedIndex.value + 1) % flattenedItems.value.length;
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (flattenedItems.value.length > 0) {
            selectedIndex.value =
                (selectedIndex.value - 1 + flattenedItems.value.length) % flattenedItems.value.length;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        executeItem(selectedIndex.value);
    }
};

const highlightParts = (text: string): { text: string; match: boolean }[] => {
    const q = query.value.trim();
    if (!q) {
        return [{ text, match: false }];
    }
    const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escaped})`, 'ig');
    return text
        .split(regex)
        .filter((part) => part.length > 0)
        .map((part) => ({
            text: part,
            match: part.toLowerCase() === q.toLowerCase(),
        }));
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <div>
        <!-- Header Trigger Button -->
        <button
            type="button"
            @click="openPalette"
            class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-neutral-100 hover:bg-neutral-200/80 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-500 dark:text-neutral-400 text-xs transition border border-neutral-200/80 dark:border-neutral-700/80"
            title="Command Palette (Ctrl+K)"
        >
            <Search class="w-3.5 h-3.5" />
            <span class="hidden lg:inline">Search or jump...</span>
            <kbd class="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-mono bg-white dark:bg-neutral-900 rounded border border-neutral-200 dark:border-neutral-700 text-neutral-500">
                Ctrl K
            </kbd>
        </button>

        <!-- Spotlight Modal -->
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-start justify-center pt-14 sm:pt-24 px-4 bg-black/50 backdrop-blur-sm"
            @click.self="closePalette"
        >
            <div class="w-full max-w-xl rounded-2xl bg-white dark:bg-neutral-900 shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden flex flex-col max-h-[75vh]">
                <!-- Search Input -->
                <div class="flex items-center gap-3 px-4 py-3.5 border-b border-neutral-200 dark:border-neutral-800">
                    <Search class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
                    <input
                        ref="searchInputRef"
                        v-model="query"
                        type="text"
                        placeholder="Search tasks, projects, milestones, habits, or type a command..."
                        class="flex-1 bg-transparent border-0 focus:ring-0 text-sm text-neutral-900 dark:text-white placeholder-neutral-400 p-0"
                    />
                    <span v-if="isSearching" class="text-[11px] text-neutral-400">Searching...</span>
                    <kbd class="px-1.5 py-0.5 text-[10px] font-mono rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500">
                        ESC
                    </kbd>
                </div>

                <!-- Results -->
                <div class="flex-1 overflow-y-auto p-2 space-y-3">
                    <div v-if="flattenedItems.length === 0" class="py-10 text-center text-xs text-neutral-500">
                        No matching commands or records found for "{{ query }}".
                    </div>

                    <div v-for="group in groupedItems" :key="group.name">
                        <div class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-neutral-400">
                            {{ group.name }}
                        </div>
                        <div class="space-y-0.5">
                            <button
                                v-for="{ entry, flatIndex } in group.items"
                                :key="entry.id"
                                type="button"
                                @mouseenter="selectedIndex = flatIndex"
                                @click="executeItem(flatIndex)"
                                class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-left transition"
                                :class="
                                    selectedIndex === flatIndex
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'text-neutral-700 dark:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                "
                            >
                                <component
                                    :is="entry.icon"
                                    class="w-4 h-4 shrink-0"
                                    :class="selectedIndex === flatIndex ? 'text-white' : 'text-neutral-400'"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-medium truncate">
                                        <template v-for="(part, pIdx) in highlightParts(entry.title)" :key="pIdx">
                                            <mark
                                                v-if="part.match"
                                                class="rounded px-0.5"
                                                :class="
                                                    selectedIndex === flatIndex
                                                        ? 'bg-white/25 text-white'
                                                        : 'bg-amber-200/80 dark:bg-amber-500/30 text-inherit'
                                                "
                                            >
                                                {{ part.text }}
                                            </mark>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </div>
                                    <div
                                        v-if="entry.subtitle"
                                        class="text-[11px] truncate"
                                        :class="selectedIndex === flatIndex ? 'text-indigo-100' : 'text-neutral-400'"
                                    >
                                        {{ entry.subtitle }}
                                    </div>
                                </div>

                                <span
                                    v-if="entry.badge"
                                    class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded"
                                    :class="
                                        selectedIndex === flatIndex
                                            ? 'bg-white/20 text-white'
                                            : entry.badge === 'high'
                                              ? 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                                              : entry.badge === 'medium'
                                                ? 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
                                    "
                                >
                                    {{ entry.badge }}
                                </span>

                                <ArrowRight
                                    class="w-3.5 h-3.5 shrink-0 opacity-70"
                                    :class="selectedIndex === flatIndex ? 'text-white' : 'text-neutral-300'"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer Hints -->
                <div class="px-4 py-2 bg-neutral-50 dark:bg-neutral-950/70 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between text-[11px] text-neutral-400">
                    <span>↑↓ Navigate • Enter Select • Esc Close</span>
                    <span>Local Workspace Search</span>
                </div>
            </div>
        </div>
    </div>
</template>

