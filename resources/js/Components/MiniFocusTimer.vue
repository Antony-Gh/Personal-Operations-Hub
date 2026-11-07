<script setup lang="ts">
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';
import { useFocusTimer } from '@/composables/useFocusTimer';
import {
    Timer,
    Play,
    Pause,
    RotateCcw,
    CheckCircle2,
    Square,
    Coffee,
    ChevronDown,
} from 'lucide-vue-next';

const page = usePage<PageProps>();
const customMinutes = ref<number>(page.props.auth.user?.setting?.focus_work_minutes || 25);

const {
    activeSession,
    formattedRemaining,
    progressPercent,
    isBusy,
    isExpanded,
    syncFromServerProps,
    startFocusSession,
    pauseSession,
    resumeSession,
    resetSession,
    completeEarly,
    stopSession,
} = useFocusTimer();

syncFromServerProps(
    page.props.activeFocusSession,
    () => page.props.auth.user?.setting
);

watch(
    () => page.props.activeFocusSession,
    (nextSession) => {
        syncFromServerProps(nextSession, () => page.props.auth.user?.setting);
    }
);

const quickStart = async (mode: 'focus' | 'short_break' | 'long_break', minutes?: number) => {
    await startFocusSession({
        mode,
        planned_minutes: minutes,
    });
};
</script>

<template>
    <div class="relative">
        <!-- Header Trigger Pill -->
        <button
            type="button"
            @click="isExpanded = !isExpanded"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition border"
            :class="
                activeSession
                    ? activeSession.status === 'running'
                        ? 'bg-indigo-50 dark:bg-indigo-950/70 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300'
                        : 'bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300'
                    : 'border-neutral-200 dark:border-neutral-800 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800'
            "
            title="Focus / Pomodoro Timer"
        >
            <Timer class="w-4 h-4 shrink-0" :class="activeSession?.status === 'running' ? 'animate-pulse text-indigo-600 dark:text-indigo-400' : ''" />
            <span v-if="activeSession" class="font-mono font-semibold">{{ formattedRemaining }}</span>
            <span v-else class="hidden lg:inline">Focus</span>
            <ChevronDown class="w-3 h-3 opacity-60 hidden sm:inline" />
        </button>

        <!-- Click-outside Backdrop -->
        <div v-if="isExpanded" class="fixed inset-0 z-40" @click="isExpanded = false"></div>

        <!-- Dropdown Focus Widget -->
        <div
            v-if="isExpanded"
            class="fixed inset-x-3 top-16 sm:inset-auto sm:absolute sm:right-0 sm:mt-2 sm:w-80 rounded-2xl bg-white dark:bg-neutral-900 shadow-2xl border border-neutral-200 dark:border-neutral-800 p-4 z-50"
        >
            <!-- Active Timer View -->
            <div v-if="activeSession" class="space-y-3">
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full"
                        :class="
                            activeSession.mode === 'focus'
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        "
                    >
                        {{
                            activeSession.mode === 'focus'
                                ? 'Deep Focus'
                                : activeSession.mode === 'short_break'
                                  ? 'Short Break'
                                  : 'Long Break'
                        }}
                    </span>
                    <span class="text-[11px] text-neutral-400 capitalize">{{ activeSession.status }}</span>
                </div>

                <div v-if="activeSession.task" class="px-2.5 py-1.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/70 border border-neutral-100 dark:border-neutral-800">
                    <p class="text-[10px] uppercase tracking-wider text-neutral-400 font-semibold">Linked Task</p>
                    <p class="text-xs font-medium text-neutral-800 dark:text-neutral-100 truncate">
                        {{ activeSession.task.title }}
                    </p>
                </div>

                <div class="text-center py-2">
                    <div class="text-3xl font-mono font-bold tracking-tight text-neutral-900 dark:text-white">
                        {{ formattedRemaining }}
                    </div>
                    <div class="w-full h-1.5 rounded-full bg-neutral-100 dark:bg-neutral-800 overflow-hidden mt-3">
                        <div
                            class="h-full bg-indigo-600 dark:bg-indigo-500 transition-all duration-300"
                            :style="{ width: `${progressPercent}%` }"
                        ></div>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-1.5">
                    <button
                        v-if="activeSession.status === 'running'"
                        type="button"
                        :disabled="isBusy"
                        @click="pauseSession"
                        class="col-span-2 inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold transition"
                    >
                        <Pause class="w-3.5 h-3.5" /> Pause
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="isBusy"
                        @click="resumeSession"
                        class="col-span-2 inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition"
                    >
                        <Play class="w-3.5 h-3.5" /> Resume
                    </button>

                    <button
                        type="button"
                        :disabled="isBusy"
                        @click="resetSession"
                        class="inline-flex items-center justify-center py-2 rounded-lg bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 transition"
                        title="Reset countdown"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                    </button>

                    <button
                        type="button"
                        :disabled="isBusy"
                        @click="stopSession"
                        class="inline-flex items-center justify-center py-2 rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 transition"
                        title="Stop & log elapsed minutes"
                    >
                        <Square class="w-3.5 h-3.5" />
                    </button>
                </div>

                <button
                    type="button"
                    :disabled="isBusy"
                    @click="completeEarly"
                    class="w-full inline-flex items-center justify-center gap-1.5 py-1.5 rounded-lg text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/50 transition"
                >
                    <CheckCircle2 class="w-3.5 h-3.5" /> Complete Session Now
                </button>
            </div>

            <!-- Idle Start View -->
            <div v-else class="space-y-3">
                <div>
                    <h4 class="text-xs font-semibold text-neutral-900 dark:text-white">Start Focus Session</h4>
                    <p class="text-[11px] text-neutral-500 mt-0.5">
                        Accurate timestamp timer — persists across pages.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <button
                        type="button"
                        :disabled="isBusy"
                        @click="quickStart('focus', page.props.auth.user?.setting?.focus_work_minutes || 25)"
                        class="flex flex-col items-center p-2.5 rounded-xl border border-indigo-200 dark:border-indigo-900 bg-indigo-50/60 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-950 text-indigo-700 dark:text-indigo-300 transition"
                    >
                        <Timer class="w-4 h-4 mb-1" />
                        <span class="text-xs font-bold">{{ page.props.auth.user?.setting?.focus_work_minutes || 25 }}m</span>
                        <span class="text-[10px] opacity-80">Focus</span>
                    </button>

                    <button
                        type="button"
                        :disabled="isBusy"
                        @click="quickStart('short_break', page.props.auth.user?.setting?.focus_short_break_minutes || 5)"
                        class="flex flex-col items-center p-2.5 rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/60 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-950 text-emerald-700 dark:text-emerald-300 transition"
                    >
                        <Coffee class="w-4 h-4 mb-1" />
                        <span class="text-xs font-bold">{{ page.props.auth.user?.setting?.focus_short_break_minutes || 5 }}m</span>
                        <span class="text-[10px] opacity-80">Short Break</span>
                    </button>

                    <button
                        type="button"
                        :disabled="isBusy"
                        @click="quickStart('long_break', page.props.auth.user?.setting?.focus_long_break_minutes || 15)"
                        class="flex flex-col items-center p-2.5 rounded-xl border border-sky-200 dark:border-sky-900 bg-sky-50/60 dark:bg-sky-950/40 hover:bg-sky-100 dark:hover:bg-sky-950 text-sky-700 dark:text-sky-300 transition"
                    >
                        <Coffee class="w-4 h-4 mb-1" />
                        <span class="text-xs font-bold">{{ page.props.auth.user?.setting?.focus_long_break_minutes || 15 }}m</span>
                        <span class="text-[10px] opacity-80">Long Break</span>
                    </button>
                </div>

                <div class="pt-2 border-t border-neutral-100 dark:border-neutral-800 flex items-center gap-2">
                    <input
                        v-model.number="customMinutes"
                        type="number"
                        min="1"
                        max="240"
                        class="w-20 px-2.5 py-1.5 text-xs rounded-lg border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white"
                    />
                    <span class="text-xs text-neutral-500">minutes</span>
                    <button
                        type="button"
                        :disabled="isBusy"
                        @click="quickStart('focus', customMinutes)"
                        class="ml-auto px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition"
                    >
                        Start Custom
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

