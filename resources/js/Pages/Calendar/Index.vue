<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CalendarEvent, Project } from '@/types';
import {
    Calendar as CalendarIcon,
    ChevronLeft,
    ChevronRight,
    Plus,
    Clock,
    MapPin,
    Flag,
    CheckSquare,
    X,
    Filter,
    LayoutGrid,
    CalendarDays,
} from 'lucide-vue-next';

const props = defineProps<{
    currentMonth: string; // 'YYYY-MM'
    items: CalendarEvent[];
    projects: Project[];
    filters: {
        category_id?: string;
        project_id?: string;
        month: string;
    };
}>();

const viewMode = ref<'month' | 'week'>('month');
const viewType = ref<'grid' | 'agenda'>('grid');
const selectedDate = ref<string>(new Date().toISOString().split('T')[0]);
const selectedCategoryId = ref(props.filters.category_id || '');
const selectedProjectId = ref(props.filters.project_id || '');

const applyFilters = () => {
    router.get(
        route('calendar.index'),
        {
            month: props.currentMonth,
            category_id: selectedCategoryId.value || undefined,
            project_id: selectedProjectId.value || undefined,
        },
        { preserveState: true }
    );
};

const changeMonth = (delta: number) => {
    const [year, month] = props.currentMonth.split('-').map(Number);
    const date = new Date(year, month - 1 + delta, 1);
    const newMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    router.get(
        route('calendar.index'),
        {
            month: newMonth,
            category_id: selectedCategoryId.value || undefined,
            project_id: selectedProjectId.value || undefined,
        },
        { preserveState: true }
    );
};

// Calendar Grid Calculations
const monthDays = computed(() => {
    const [year, month] = props.currentMonth.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const lastDay = new Date(year, month, 0);

    const days = [];
    const startOffset = (firstDay.getDay() + 6) % 7; // Monday = 0
    const totalDays = lastDay.getDate();

    // Previous month padding
    const prevLastDay = new Date(year, month - 1, 0).getDate();
    for (let i = startOffset - 1; i >= 0; i--) {
        const d = prevLastDay - i;
        const prevMonth = month - 1 === 0 ? 12 : month - 1;
        const prevYear = month - 1 === 0 ? year - 1 : year;
        const dateStr = `${prevYear}-${String(prevMonth).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        days.push({ dayNumber: d, dateStr, isCurrentMonth: false });
    }

    // Current month days
    for (let d = 1; d <= totalDays; d++) {
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        days.push({ dayNumber: d, dateStr, isCurrentMonth: true });
    }

    // Trailing padding to make 35 or 42 cells
    const remaining = 35 - days.length >= 0 ? 35 - days.length : 42 - days.length;
    for (let d = 1; d <= remaining; d++) {
        const nextMonth = month === 12 ? 1 : month + 1;
        const nextYear = month === 12 ? year + 1 : year;
        const dateStr = `${nextYear}-${String(nextMonth).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        days.push({ dayNumber: d, dateStr, isCurrentMonth: false });
    }

    return days;
});

const getItemsForDate = (dateStr: string) => {
    return props.items.filter((item) => item.date === dateStr);
};

const itemsForSelectedDate = computed(() => {
    return getItemsForDate(selectedDate.value);
});

const agendaGroups = computed(() => {
    const datesMap: Record<string, CalendarEvent[]> = {};
    props.items.forEach((item) => {
        if (!item.date) return;
        if (!datesMap[item.date]) {
            datesMap[item.date] = [];
        }
        datesMap[item.date].push(item);
    });

    return Object.keys(datesMap)
        .sort()
        .map((date) => ({
            date,
            items: datesMap[date],
        }));
});

const isToday = (dateStr: string) => {
    return dateStr === new Date().toISOString().split('T')[0];
};

// Event Creation Modal
const isCreateEventOpen = ref(false);
const eventForm = useForm({
    title: '',
    category_id: '' as string | number,
    start_time: '',
    end_time: '',
    all_day: false,
    location: '',
    description: '',
    color: '#6366f1',
});

const openCreateEvent = (dateStr?: string) => {
    const defaultDate = dateStr || new Date().toISOString().split('T')[0];
    eventForm.start_time = `${defaultDate}T10:00`;
    eventForm.end_time = `${defaultDate}T11:00`;
    isCreateEventOpen.value = true;
};

const submitEvent = () => {
    eventForm.post(route('calendar.events.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateEventOpen.value = false;
            eventForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Calendar" />

    <AuthenticatedLayout title="Calendar">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header with Month Switcher -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-neutral-900 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <CalendarIcon class="w-6 h-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">
                            {{ new Date(props.currentMonth + '-01').toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) }}
                        </h2>
                        <p class="text-xs text-neutral-400">Tasks, milestones, and fixed commitments</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1">
                        <button
                            @click="changeMonth(-1)"
                            class="p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>
                        <button
                            @click="changeMonth(1)"
                            class="p-2 rounded-xl border border-neutral-200 dark:border-neutral-800 hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300 transition"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>

                    <button
                        @click="openCreateEvent()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add Event</span>
                    </button>
                </div>
            </div>

            <!-- Filters & View Switcher Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-neutral-900 p-3 rounded-xl border border-neutral-200 dark:border-neutral-800 text-xs">
                <div class="flex items-center gap-3 flex-wrap">
                    <Filter class="w-4 h-4 text-neutral-400 shrink-0" />
                    <select
                        v-model="selectedCategoryId"
                        @change="applyFilters"
                        class="text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                    >
                        <option value="">All Areas</option>
                        <option v-for="cat in $page.props.categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>

                    <select
                        v-model="selectedProjectId"
                        @change="applyFilters"
                        class="text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                    >
                        <option value="">All Projects</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select>
                </div>

                <!-- Grid vs Agenda View Toggle -->
                <div class="flex items-center self-end sm:self-auto border border-neutral-200 dark:border-neutral-800 rounded-lg p-0.5 bg-neutral-100/60 dark:bg-neutral-800/60">
                    <button
                        @click="viewType = 'grid'"
                        type="button"
                        class="px-2.5 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                        :class="viewType === 'grid' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs font-semibold' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    >
                        <LayoutGrid class="w-3.5 h-3.5" />
                        <span>Month</span>
                    </button>
                    <button
                        @click="viewType = 'agenda'"
                        type="button"
                        class="px-2.5 py-1 rounded-md text-xs font-medium flex items-center gap-1.5 transition"
                        :class="viewType === 'agenda' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-xs font-semibold' : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                    >
                        <CalendarDays class="w-3.5 h-3.5" />
                        <span>Agenda</span>
                    </button>
                </div>
            </div>

            <!-- VIEW 1: MONTH GRID (Desktop Large + Mobile Compact with Day Detail) -->
            <div v-if="viewType === 'grid'" class="space-y-4">
                <!-- Desktop Large Grid (Visible on md and up) -->
                <div class="hidden md:block bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden shadow-xs">
                    <div class="grid grid-cols-7 border-b border-neutral-200 dark:border-neutral-800 text-center text-[11px] font-semibold text-neutral-400 py-2.5 uppercase tracking-wider bg-neutral-50 dark:bg-neutral-800/40">
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                        <div>Sun</div>
                    </div>

                    <div class="grid grid-cols-7 divide-x divide-y divide-neutral-100 dark:divide-neutral-800">
                        <div
                            v-for="day in monthDays"
                            :key="'desk-' + day.dateStr"
                            class="min-h-[110px] p-2 flex flex-col justify-between transition hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 group"
                            :class="!day.isCurrentMonth ? 'bg-neutral-50/30 dark:bg-neutral-900/40 text-neutral-400 opacity-60' : ''"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span
                                    class="text-xs font-semibold rounded-full w-5 h-5 flex items-center justify-center"
                                    :class="isToday(day.dateStr) ? 'bg-indigo-600 text-white' : 'text-neutral-700 dark:text-neutral-300'"
                                >
                                    {{ day.dayNumber }}
                                </span>
                                <button
                                    @click="openCreateEvent(day.dateStr)"
                                    class="opacity-0 group-hover:opacity-100 p-0.5 text-neutral-400 hover:text-indigo-600"
                                    title="Add Event"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div class="space-y-1 overflow-y-auto max-h-[85px]">
                                <div
                                    v-for="item in getItemsForDate(day.dateStr)"
                                    :key="item.id"
                                    class="p-1 rounded text-[10px] leading-tight truncate flex items-center gap-1 shadow-xs font-medium"
                                    :style="{
                                        backgroundColor: (item.color || '#6366f1') + '20',
                                        color: item.color || '#6366f1',
                                    }"
                                >
                                    <CheckSquare v-if="item.type === 'task'" class="w-2.5 h-2.5 shrink-0" />
                                    <Flag v-else-if="item.type === 'milestone'" class="w-2.5 h-2.5 shrink-0" />
                                    <Clock v-else class="w-2.5 h-2.5 shrink-0" />
                                    <span class="truncate" :class="{ 'line-through opacity-60': item.is_completed }">
                                        {{ item.title }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Compact Grid (Visible on screens < md) -->
                <div class="block md:hidden space-y-4">
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-3 shadow-xs">
                        <!-- Compact Weekday Headers -->
                        <div class="grid grid-cols-7 text-center text-[10px] font-bold text-neutral-400 mb-2 uppercase">
                            <div>M</div>
                            <div>T</div>
                            <div>W</div>
                            <div>T</div>
                            <div>F</div>
                            <div>S</div>
                            <div>S</div>
                        </div>

                        <!-- Compact Day Tiles -->
                        <div class="grid grid-cols-7 gap-1">
                            <button
                                v-for="day in monthDays"
                                :key="'mob-' + day.dateStr"
                                @click="selectedDate = day.dateStr"
                                type="button"
                                class="h-11 rounded-xl p-1 flex flex-col items-center justify-between transition relative border"
                                :class="[
                                    selectedDate === day.dateStr
                                        ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 shadow-xs'
                                        : isToday(day.dateStr)
                                        ? 'border-indigo-300 dark:border-indigo-800 bg-white dark:bg-neutral-900'
                                        : 'border-transparent hover:bg-neutral-100/60 dark:hover:bg-neutral-800/60',
                                    !day.isCurrentMonth ? 'opacity-30' : ''
                                ]"
                            >
                                <span
                                    class="text-[11px] font-semibold w-5 h-5 flex items-center justify-center rounded-full"
                                    :class="isToday(day.dateStr) ? 'bg-indigo-600 text-white' : 'text-neutral-800 dark:text-neutral-200'"
                                >
                                    {{ day.dayNumber }}
                                </span>

                                <!-- Event indicator dots -->
                                <div class="flex items-center gap-0.5 h-1.5">
                                    <span
                                        v-for="(it, dotIdx) in getItemsForDate(day.dateStr).slice(0, 3)"
                                        :key="dotIdx"
                                        class="w-1.5 h-1.5 rounded-full"
                                        :style="{ backgroundColor: it.color || '#6366f1' }"
                                    ></span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Selected Day Detail Card for Mobile -->
                    <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-2">
                            <div>
                                <h4 class="text-sm font-bold text-neutral-900 dark:text-white">
                                    {{ new Date(selectedDate + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) }}
                                </h4>
                                <p class="text-[11px] text-neutral-400">
                                    {{ itemsForSelectedDate.length }} item{{ itemsForSelectedDate.length === 1 ? '' : 's' }} scheduled
                                </p>
                            </div>
                            <button
                                @click="openCreateEvent(selectedDate)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 transition"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add Event</span>
                            </button>
                        </div>

                        <div v-if="itemsForSelectedDate.length === 0" class="py-6 text-center text-xs text-neutral-400">
                            No events, milestones, or due tasks for this day.
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="item in itemsForSelectedDate"
                                :key="item.id"
                                class="p-3 rounded-xl border border-neutral-200/70 dark:border-neutral-800 flex items-start gap-2.5 bg-neutral-50/50 dark:bg-neutral-800/30"
                            >
                                <span
                                    class="w-2 h-2 rounded-full mt-1.5 shrink-0"
                                    :style="{ backgroundColor: item.color || '#6366f1' }"
                                ></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h5
                                            class="text-xs font-semibold text-neutral-900 dark:text-white truncate"
                                            :class="{ 'line-through opacity-60': item.is_completed }"
                                        >
                                            {{ item.title }}
                                        </h5>
                                        <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded shrink-0 bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                                            {{ item.type }}
                                        </span>
                                    </div>
                                    <p v-if="item.start_time" class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                                        {{ new Date(item.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        <span v-if="item.end_time"> - {{ new Date(item.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                    </p>
                                    <p v-if="item.location" class="text-[10px] text-neutral-400 flex items-center gap-1 mt-0.5">
                                        <MapPin class="w-3 h-3" /> {{ item.location }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW 2: AGENDA / SCHEDULE FEED VIEW -->
            <div v-else-if="viewType === 'agenda'" class="space-y-4">
                <div v-if="agendaGroups.length === 0" class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-8 text-center text-xs text-neutral-400">
                    <CalendarDays class="w-8 h-8 mx-auto mb-2 text-neutral-300 dark:text-neutral-700" />
                    No scheduled events or tasks found for {{ new Date(props.currentMonth + '-01').toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) }}.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="group in agendaGroups"
                        :key="group.date"
                        class="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4 sm:p-5 shadow-xs space-y-3"
                    >
                        <div class="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-800 pb-2">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-xs font-bold px-2 py-0.5 rounded-lg"
                                    :class="isToday(group.date) ? 'bg-indigo-600 text-white' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200'"
                                >
                                    {{ new Date(group.date + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) }}
                                </span>
                                <span v-if="isToday(group.date)" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wide">
                                    Today
                                </span>
                            </div>
                            <button
                                @click="openCreateEvent(group.date)"
                                class="p-1 rounded text-neutral-400 hover:text-indigo-600"
                                title="Add Event to this day"
                            >
                                <Plus class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="item in group.items"
                                :key="item.id"
                                class="p-3 rounded-xl border border-neutral-100 dark:border-neutral-800/80 hover:border-neutral-200 dark:hover:border-neutral-700 flex items-start gap-3 transition bg-neutral-50/50 dark:bg-neutral-800/20"
                            >
                                <span
                                    class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0"
                                    :style="{ backgroundColor: item.color || '#6366f1' }"
                                ></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h5
                                            class="text-xs font-semibold text-neutral-900 dark:text-white truncate"
                                            :class="{ 'line-through opacity-60': item.is_completed }"
                                        >
                                            {{ item.title }}
                                        </h5>
                                        <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded shrink-0 bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400">
                                            {{ item.type }}
                                        </span>
                                    </div>
                                    <p v-if="item.start_time" class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5">
                                        {{ new Date(item.start_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        <span v-if="item.end_time"> - {{ new Date(item.end_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                    </p>
                                    <p v-if="item.location" class="text-[10px] text-neutral-400 flex items-center gap-1 mt-0.5">
                                        <MapPin class="w-3 h-3" /> {{ item.location }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Event Modal -->
            <div
                v-if="isCreateEventOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="isCreateEventOpen = false"
            >
                <div class="bg-white dark:bg-neutral-900 rounded-xl p-5 max-w-md w-full border border-neutral-200 dark:border-neutral-800 shadow-xl space-y-4">
                    <h4 class="text-sm font-semibold text-neutral-900 dark:text-white">Schedule Commitment / Event</h4>
                    <form @submit.prevent="submitEvent" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-medium mb-1">Event Title *</label>
                            <input
                                v-model="eventForm.title"
                                type="text"
                                required
                                placeholder="e.g. Signals Lecture, Software Standup"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-medium mb-1">Start Time *</label>
                                <input
                                    v-model="eventForm.start_time"
                                    type="datetime-local"
                                    required
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-medium mb-1">End Time *</label>
                                <input
                                    v-model="eventForm.end_time"
                                    type="datetime-local"
                                    required
                                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Location / Link</label>
                            <input
                                v-model="eventForm.location"
                                type="text"
                                placeholder="e.g. Lab 302 or Google Meet link"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Area / Category</label>
                            <select
                                v-model="eventForm.category_id"
                                class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-white"
                            >
                                <option value="">None</option>
                                <option v-for="cat in $page.props.categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="isCreateEventOpen = false"
                                class="px-3 py-1.5 text-neutral-600 hover:bg-neutral-100 rounded"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="px-3 py-1.5 bg-indigo-600 text-white rounded font-medium hover:bg-indigo-700"
                            >
                                Save Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

