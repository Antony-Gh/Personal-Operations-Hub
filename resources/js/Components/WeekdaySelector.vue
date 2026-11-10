<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import { getCurrentWeekDays, formatDateWithDay, getLocalDateString } from '@/utils/date';
import { Calendar, CheckCircle2, X } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        label?: string;
        required?: boolean;
        showManualInput?: boolean;
    }>(),
    {
        modelValue: '',
        label: 'Due Date',
        required: false,
        showManualInput: true,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const page = usePage<PageProps>();

const weekStart = computed<number>(() => {
    return page.props.auth.user?.setting?.week_start ?? 1;
});

const todayDate = computed<string>(() => {
    return getLocalDateString(page.props.auth.user?.setting?.timezone);
});

const weekDays = computed(() => {
    return getCurrentWeekDays(todayDate.value, weekStart.value);
});

const selectDay = (dateStr: string) => {
    emit('update:modelValue', dateStr);
};

const clearDate = () => {
    emit('update:modelValue', '');
};

const handleDropdownSelect = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    if (val) {
        emit('update:modelValue', val);
    }
};
</script>

<template>
    <div class="space-y-1.5">
        <!-- Label & Calculated Display -->
        <div class="flex items-center justify-between gap-2">
            <label class="block font-medium text-neutral-700 dark:text-neutral-300 text-xs">
                {{ label }}
                <span v-if="required" class="text-rose-500">*</span>
            </label>
            <div v-if="modelValue" class="flex items-center gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                <CheckCircle2 class="w-3.5 h-3.5 shrink-0" />
                <span>{{ formatDateWithDay(modelValue) }}</span>
                <button
                    type="button"
                    @click="clearDate"
                    class="p-0.5 rounded text-neutral-400 hover:text-rose-500 ml-1 transition"
                    title="Clear date"
                >
                    <X class="w-3 h-3" />
                </button>
            </div>
        </div>

        <!-- 7 Quick Day Buttons in Current Week -->
        <div class="space-y-1">
            <div class="grid grid-cols-7 gap-1">
                <button
                    v-for="d in weekDays"
                    :key="d.dateStr"
                    type="button"
                    @click="selectDay(d.dateStr)"
                    class="flex flex-col items-center justify-center py-1.5 px-0.5 rounded-lg border text-center transition select-none group"
                    :class="
                        modelValue === d.dateStr
                            ? 'bg-indigo-600 border-indigo-600 text-white font-semibold shadow-sm'
                            : d.isToday
                            ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/70 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60'
                            : 'border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/60 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700/60'
                    "
                    :title="`${d.fullName} (${d.formatted})`"
                >
                    <span class="text-[10px] sm:text-[11px] font-medium leading-none">{{ d.dayName }}</span>
                    <span
                        class="text-[9px] mt-0.5 leading-none"
                        :class="modelValue === d.dateStr ? 'text-indigo-100' : 'text-neutral-400 dark:text-neutral-400'"
                    >
                        {{ d.dayNumber }}
                    </span>
                    <span
                        v-if="d.isToday"
                        class="w-1 h-1 rounded-full mt-0.5"
                        :class="modelValue === d.dateStr ? 'bg-white' : 'bg-indigo-500'"
                    ></span>
                </button>
            </div>

            <!-- Dropdown Option & Manual Input Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-0.5">
                <!-- Dropdown Day of Week Selector -->
                <select
                    :value="modelValue && weekDays.some(d => d.dateStr === modelValue) ? modelValue : ''"
                    @change="handleDropdownSelect"
                    class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200 py-1.5"
                >
                    <option value="">Choose day this week...</option>
                    <option
                        v-for="d in weekDays"
                        :key="'sel-' + d.dateStr"
                        :value="d.dateStr"
                    >
                        {{ d.fullName }} ({{ d.dayName }} {{ d.dayNumber }}){{ d.isToday ? ' • Today' : '' }}
                    </option>
                </select>

                <!-- Date Picker Input -->
                <div v-if="showManualInput" class="relative">
                    <input
                        :value="modelValue || ''"
                        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
                        type="date"
                        class="w-full text-xs rounded-lg border-neutral-200 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200 py-1.5"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

