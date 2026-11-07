<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { AppNotification, PageProps } from '@/types';
import { formatRelativeTime } from '@/utils/date';
import { useNotificationSound } from '@/composables/useNotificationSound';
import { useBrowserNotifications } from '@/composables/useBrowserNotifications';
import {
    Bell,
    Check,
    CheckCheck,
    Trash2,
    Clock,
    AlertTriangle,
    FolderKanban,
    Sparkles,
    Timer,
    Sun,
    X,
    CheckCircle2,
} from 'lucide-vue-next';

const page = usePage<PageProps>();
const isOpen = ref(false);
const activeFilter = ref<'all' | 'unread' | 'tasks' | 'projects' | 'briefings'>('all');
const items = ref<AppNotification[]>(page.props.notificationsSummary?.items || []);
const unreadCount = ref<number>(page.props.notificationsSummary?.unread_count || 0);
const nextPage = ref<number | null>(null);
const hasMore = ref<boolean>(false);
const isLoading = ref<boolean>(false);
const openSnoozeFor = ref<string | null>(null);
const customSnoozeMinutes = ref<number>(45);

const { playForUserSetting } = useNotificationSound();
const { sendDesktopNotification } = useBrowserNotifications();

const SEEN_KEY = 'ops_hub_last_notified_id';
let pollInterval: ReturnType<typeof setInterval> | null = null;

watch(
    () => page.props.notificationsSummary,
    (summary) => {
        if (summary && !isOpen.value) {
            items.value = summary.items || [];
            unreadCount.value = summary.unread_count || 0;
        }
    }
);

const filteredItems = computed(() => {
    return items.value.filter((n) => {
        if (activeFilter.value === 'unread') {
            return !n.read_at;
        }
        if (activeFilter.value === 'tasks') {
            return ['task_reminders', 'overdue_tasks'].includes(n.data.category);
        }
        if (activeFilter.value === 'projects') {
            return ['project_deadlines', 'milestone_deadlines'].includes(n.data.category);
        }
        if (activeFilter.value === 'briefings') {
            return ['daily_briefing', 'weekly_digest'].includes(n.data.category);
        }
        return true;
    });
});

const fetchNotifications = async (pageNum: number = 1, append: boolean = false, silent: boolean = false) => {
    if (!silent) {
        isLoading.value = true;
    }
    try {
        const res = await axios.get(route('notifications.index'), {
            params: { page: pageNum, per_page: 20 },
        });
        const prevSeen = sessionStorage.getItem(SEEN_KEY);
        const latestUnreadId: string | null = res.data.latest_unread_id || null;

        if (latestUnreadId && latestUnreadId !== prevSeen) {
            sessionStorage.setItem(SEEN_KEY, latestUnreadId);
            if (prevSeen !== null) {
                const newest = (res.data.items as AppNotification[]).find((i) => i.id === latestUnreadId);
                if (newest) {
                    const setting = page.props.auth.user?.setting;
                    playForUserSetting(setting);
                    void sendDesktopNotification(
                        newest.data.title,
                        newest.data.message,
                        newest.data.action_url || '/dashboard',
                        setting,
                        `notif-${newest.id}`
                    );
                }
            }
        } else if (latestUnreadId && prevSeen === null) {
            sessionStorage.setItem(SEEN_KEY, latestUnreadId);
        }

        unreadCount.value = res.data.unread_count ?? 0;
        hasMore.value = Boolean(res.data.has_more);
        nextPage.value = res.data.next_page ?? null;

        if (append) {
            const existingIds = new Set(items.value.map((i) => i.id));
            const fresh = (res.data.items as AppNotification[]).filter((i) => !existingIds.has(i.id));
            items.value = [...items.value, ...fresh];
        } else {
            items.value = res.data.items || [];
        }
    } catch {
        // Ignore transient network errors when server restarts
    } finally {
        if (!silent) {
            isLoading.value = false;
        }
    }
};

const toggleDrawer = () => {
    isOpen.value = !isOpen.value;
    openSnoozeFor.value = null;
    if (isOpen.value) {
        void fetchNotifications(1, false, false);
    }
};

const markRead = async (n: AppNotification) => {
    if (n.read_at) {
        return;
    }
    n.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
    await axios.post(route('notifications.read', n.id));
};

const markAllRead = async () => {
    const nowIso = new Date().toISOString();
    items.value = items.value.map((i) => ({ ...i, read_at: i.read_at || nowIso }));
    unreadCount.value = 0;
    await axios.post(route('notifications.read-all'));
};

const removeNotification = async (n: AppNotification) => {
    if (!n.read_at) {
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    items.value = items.value.filter((i) => i.id !== n.id);
    await axios.delete(route('notifications.destroy', n.id));
};

const clearAll = async () => {
    items.value = [];
    unreadCount.value = 0;
    await axios.delete(route('notifications.clear-all'));
};

const openItemTarget = async (n: AppNotification) => {
    await markRead(n);
    isOpen.value = false;
    if (n.data.action_url) {
        router.visit(n.data.action_url);
    }
};

const completeLinkedTask = async (n: AppNotification) => {
    try {
        const res = await axios.post(route('notifications.complete-task', n.id));
        n.read_at = new Date().toISOString();
        unreadCount.value = res.data.unread_count ?? Math.max(0, unreadCount.value - 1);
        router.reload({ preserveUrl: true });
    } catch {
        // Ignore if task was already deleted
    }
};

const snoozeReminder = async (
    n: AppNotification,
    preset: '15m' | '30m' | '1h' | 'tomorrow' | 'custom'
) => {
    if (!n.data.reminder_id) {
        return;
    }
    const res = await axios.post(route('reminders.snooze', n.data.reminder_id), {
        preset,
        custom_minutes: preset === 'custom' ? customSnoozeMinutes.value : undefined,
        notification_id: n.id,
    });
    n.read_at = new Date().toISOString();
    unreadCount.value = res.data.unread_count ?? Math.max(0, unreadCount.value - 1);
    openSnoozeFor.value = null;
};

const dismissReminder = async (n: AppNotification) => {
    if (!n.data.reminder_id) {
        return;
    }
    const res = await axios.post(route('reminders.dismiss', n.data.reminder_id), {
        notification_id: n.id,
    });
    n.read_at = new Date().toISOString();
    unreadCount.value = res.data.unread_count ?? Math.max(0, unreadCount.value - 1);
};

const iconForCategory = (cat: string) => {
    switch (cat) {
        case 'overdue_tasks':
            return AlertTriangle;
        case 'project_deadlines':
        case 'milestone_deadlines':
            return FolderKanban;
        case 'habit_reminders':
            return Sparkles;
        case 'focus_timer':
            return Timer;
        case 'daily_briefing':
        case 'weekly_digest':
            return Sun;
        default:
            return Clock;
    }
};

onMounted(() => {
    const initialLatest = items.value.find((i) => !i.read_at)?.id;
    if (initialLatest && !sessionStorage.getItem(SEEN_KEY)) {
        sessionStorage.setItem(SEEN_KEY, initialLatest);
    }

    pollInterval = setInterval(() => {
        if (document.visibilityState === 'visible') {
            void fetchNotifications(1, false, true);
        }
    }, 30000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            @click="toggleDrawer"
            class="relative p-2 rounded-lg text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
            aria-label="Notifications"
            title="Notification Center"
        >
            <Bell class="w-4 h-4" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[17px] h-[17px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold flex items-center justify-center shadow-sm"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Click-outside Backdrop -->
        <div
            v-if="isOpen"
            class="fixed inset-0 z-40 bg-black/20 sm:bg-transparent"
            @click="isOpen = false"
        ></div>

        <!-- Notification Panel -->
        <div
            v-if="isOpen"
            class="fixed inset-x-3 top-16 sm:inset-auto sm:absolute sm:right-0 sm:mt-2 sm:w-[26rem] max-h-[80vh] rounded-2xl bg-white dark:bg-neutral-900 shadow-2xl border border-neutral-200 dark:border-neutral-800 z-50 flex flex-col overflow-hidden"
        >
            <!-- Header -->
            <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">Notifications</h3>
                    <span
                        v-if="unreadCount > 0"
                        class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-semibold"
                    >
                        {{ unreadCount }} unread
                    </span>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        @click="markAllRead"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition"
                        title="Mark all as read"
                    >
                        <CheckCheck class="w-3.5 h-3.5" /> Mark all
                    </button>
                    <button
                        v-if="items.length > 0"
                        type="button"
                        @click="clearAll"
                        class="p-1.5 rounded-md text-neutral-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                        title="Clear all notifications"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                    </button>
                    <button
                        type="button"
                        @click="isOpen = false"
                        class="p-1.5 rounded-md text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="px-3 py-2 border-b border-neutral-100 dark:border-neutral-800 flex items-center gap-1 overflow-x-auto bg-neutral-50/70 dark:bg-neutral-950/50">
                <button
                    v-for="tab in [
                        { id: 'all', label: 'All' },
                        { id: 'unread', label: 'Unread' },
                        { id: 'tasks', label: 'Tasks' },
                        { id: 'projects', label: 'Projects' },
                        { id: 'briefings', label: 'Briefings' },
                    ]"
                    :key="tab.id"
                    type="button"
                    @click="activeFilter = tab.id as any"
                    class="px-2.5 py-1 rounded-lg text-[11px] font-medium whitespace-nowrap transition"
                    :class="
                        activeFilter === tab.id
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200/60 dark:hover:bg-neutral-800'
                    "
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- List -->
            <div class="flex-1 overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800">
                <div v-if="isLoading && items.length === 0" class="p-8 text-center text-xs text-neutral-400">
                    Loading notifications...
                </div>

                <div v-else-if="filteredItems.length === 0" class="p-8 text-center">
                    <Bell class="w-8 h-8 text-neutral-300 dark:text-neutral-700 mx-auto mb-2" />
                    <p class="text-xs font-medium text-neutral-600 dark:text-neutral-400">All caught up</p>
                    <p class="text-[11px] text-neutral-400 mt-0.5">
                        Scheduled reminders and briefings will appear here automatically.
                    </p>
                </div>

                <div
                    v-for="n in filteredItems"
                    :key="n.id"
                    class="p-3.5 transition hover:bg-neutral-50 dark:hover:bg-neutral-800/50"
                    :class="!n.read_at ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="p-2 rounded-xl shrink-0 mt-0.5"
                            :class="
                                n.data.category === 'overdue_tasks' || n.data.is_critical
                                    ? 'bg-rose-100 text-rose-600 dark:bg-rose-950/70 dark:text-rose-400'
                                    : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-950/70 dark:text-indigo-400'
                            "
                        >
                            <component :is="iconForCategory(n.data.category)" class="w-4 h-4" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <button
                                    type="button"
                                    @click="openItemTarget(n)"
                                    class="text-left text-xs font-semibold text-neutral-900 dark:text-neutral-100 hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-2"
                                >
                                    {{ n.data.title }}
                                </button>
                                <span class="text-[10px] text-neutral-400 shrink-0">
                                    {{ formatRelativeTime(n.created_at) }}
                                </span>
                            </div>

                            <p class="text-xs text-neutral-600 dark:text-neutral-400 mt-0.5 leading-relaxed">
                                {{ n.data.message }}
                            </p>

                            <!-- Direct Operational Actions -->
                            <div class="flex flex-wrap items-center gap-1.5 mt-2.5">
                                <button
                                    v-if="n.data.entity_type === 'task' && n.data.entity_id"
                                    type="button"
                                    @click="completeLinkedTask(n)"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 transition"
                                >
                                    <CheckCircle2 class="w-3 h-3" /> Complete Task
                                </button>

                                <button
                                    v-if="n.data.reminder_id"
                                    type="button"
                                    @click="openSnoozeFor = openSnoozeFor === n.id ? null : n.id"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300 transition"
                                >
                                    <Clock class="w-3 h-3" /> Snooze
                                </button>

                                <button
                                    v-if="n.data.reminder_id"
                                    type="button"
                                    @click="dismissReminder(n)"
                                    class="px-2 py-1 rounded-md text-[11px] font-medium text-neutral-500 hover:bg-neutral-200/70 dark:hover:bg-neutral-800 transition"
                                >
                                    Dismiss
                                </button>

                                <button
                                    v-if="!n.read_at"
                                    type="button"
                                    @click="markRead(n)"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] text-neutral-500 hover:text-indigo-600 transition ml-auto"
                                    title="Mark read"
                                >
                                    <Check class="w-3 h-3" />
                                </button>

                                <button
                                    type="button"
                                    @click="removeNotification(n)"
                                    class="p-1 rounded-md text-neutral-400 hover:text-rose-600 transition"
                                    :class="n.read_at ? 'ml-auto' : ''"
                                    title="Remove"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>

                            <!-- Snooze Presets Drawer -->
                            <div
                                v-if="openSnoozeFor === n.id"
                                class="mt-2 p-2 rounded-lg bg-neutral-100 dark:bg-neutral-800/80 space-y-2"
                            >
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="preset in [
                                            { key: '15m', label: '15m' },
                                            { key: '30m', label: '30m' },
                                            { key: '1h', label: '1 hour' },
                                            { key: 'tomorrow', label: 'Tomorrow 9:00' },
                                        ]"
                                        :key="preset.key"
                                        type="button"
                                        @click="snoozeReminder(n, preset.key as any)"
                                        class="px-2 py-1 rounded bg-white dark:bg-neutral-900 text-[11px] font-medium text-neutral-700 dark:text-neutral-200 hover:bg-indigo-600 hover:text-white transition shadow-sm"
                                    >
                                        {{ preset.label }}
                                    </button>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <input
                                        v-model.number="customSnoozeMinutes"
                                        type="number"
                                        min="1"
                                        max="1440"
                                        class="w-20 px-2 py-1 text-xs rounded border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                                    />
                                    <span class="text-[11px] text-neutral-500">min</span>
                                    <button
                                        type="button"
                                        @click="snoozeReminder(n, 'custom')"
                                        class="px-2.5 py-1 rounded bg-indigo-600 text-white text-[11px] font-medium hover:bg-indigo-700 transition ml-auto"
                                    >
                                        Set Custom
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="hasMore && nextPage" class="p-3 text-center">
                    <button
                        type="button"
                        @click="fetchNotifications(nextPage, true, false)"
                        class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                    >
                        Load older notifications
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

