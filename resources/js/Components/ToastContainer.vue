<script setup lang="ts">
import { useToast, ToastType } from '@/composables/useToast';
import {
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Info,
    X,
} from 'lucide-vue-next';

const { toasts, removeToast, pauseToast, resumeToast } = useToast();

const getToastClasses = (type: ToastType): string => {
    switch (type) {
        case 'success':
            return 'bg-white/95 dark:bg-neutral-900/95 border-emerald-500/40 text-emerald-950 dark:text-emerald-100 shadow-emerald-500/10';
        case 'error':
            return 'bg-white/95 dark:bg-neutral-900/95 border-rose-500/40 text-rose-950 dark:text-rose-100 shadow-rose-500/10';
        case 'warning':
            return 'bg-white/95 dark:bg-neutral-900/95 border-amber-500/40 text-amber-950 dark:text-amber-100 shadow-amber-500/10';
        case 'info':
        default:
            return 'bg-white/95 dark:bg-neutral-900/95 border-indigo-500/40 text-indigo-950 dark:text-indigo-100 shadow-indigo-500/10';
    }
};

const getIconClasses = (type: ToastType): string => {
    switch (type) {
        case 'success':
            return 'text-emerald-500 dark:text-emerald-400';
        case 'error':
            return 'text-rose-500 dark:text-rose-400';
        case 'warning':
            return 'text-amber-500 dark:text-amber-400';
        case 'info':
        default:
            return 'text-indigo-500 dark:text-indigo-400';
    }
};
</script>

<template>
    <div
        aria-live="polite"
        class="fixed top-4 right-4 left-4 sm:left-auto sm:w-96 z-50 flex flex-col gap-2.5 pointer-events-none select-none"
    >
        <TransitionGroup
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-2 sm:-translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 sm:-translate-y-2 scale-95"
            move-class="transition duration-200 ease-out"
        >
            <div
                v-for="item in toasts"
                :key="item.id"
                role="status"
                @mouseenter="pauseToast(item.id)"
                @mouseleave="resumeToast(item.id)"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-xl backdrop-blur-md border flex items-start justify-between gap-3 text-xs transition-all duration-200"
                :class="getToastClasses(item.type)"
            >
                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                    <CheckCircle2 v-if="item.type === 'success'" class="w-4 h-4 shrink-0 mt-0.5" :class="getIconClasses(item.type)" />
                    <AlertCircle v-else-if="item.type === 'error'" class="w-4 h-4 shrink-0 mt-0.5" :class="getIconClasses(item.type)" />
                    <AlertTriangle v-else-if="item.type === 'warning'" class="w-4 h-4 shrink-0 mt-0.5" :class="getIconClasses(item.type)" />
                    <Info v-else class="w-4 h-4 shrink-0 mt-0.5" :class="getIconClasses(item.type)" />

                    <div class="min-w-0 flex-1">
                        <p v-if="item.title" class="font-semibold text-xs leading-snug mb-0.5">{{ item.title }}</p>
                        <p class="font-medium text-xs leading-relaxed break-words">{{ item.message }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="removeToast(item.id)"
                    class="p-1 -mr-1 -mt-0.5 rounded-md text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition shrink-0"
                    title="Dismiss alert"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

