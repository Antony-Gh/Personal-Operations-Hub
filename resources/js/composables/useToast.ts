import { ref } from 'vue';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface ToastItem {
    id: string;
    type: ToastType;
    message: string;
    title?: string;
    duration: number;
    createdAt: number;
    remaining: number;
    timer?: ReturnType<typeof setTimeout> | null;
    timerStartedAt?: number;
    isHovered?: boolean;
    isFocused?: boolean;
}

const toasts = ref<ToastItem[]>([]);
const MAX_TOASTS = 4;
const DEFAULT_DURATION = 4500;

export function useToast() {
    const removeToast = (id: string) => {
        const index = toasts.value.findIndex((t) => t.id === id);
        if (index !== -1) {
            const item = toasts.value[index];
            if (item.timer) {
                clearTimeout(item.timer);
                item.timer = null;
            }
            toasts.value.splice(index, 1);
        }
    };

    const scheduleDismissal = (item: ToastItem) => {
        if (item.duration <= 0 || item.remaining <= 0) return;

        if (item.timer) {
            clearTimeout(item.timer);
            item.timer = null;
        }

        item.timerStartedAt = Date.now();
        item.timer = setTimeout(() => {
            removeToast(item.id);
        }, item.remaining);
    };

    const addToast = (
        payload: { message: string; type?: ToastType; title?: string; duration?: number } | string,
        typeOverride?: ToastType
    ): string => {
        const id = 'toast_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
        const message = typeof payload === 'string' ? payload : payload.message;
        const type: ToastType = (typeof payload === 'string' ? typeOverride : payload.type) || 'info';
        const duration = typeof payload === 'object' && payload.duration !== undefined ? payload.duration : DEFAULT_DURATION;
        const title = typeof payload === 'object' ? payload.title : undefined;

        // Prevent exact duplicate (message + type) from stacking within 1 second
        const isDuplicate = toasts.value.some(
            (t) => t.message === message && t.type === type && Date.now() - t.createdAt < 1000
        );
        if (isDuplicate) {
            return '';
        }

        // Keep maximum visible queue
        if (toasts.value.length >= MAX_TOASTS) {
            const oldest = toasts.value[0];
            if (oldest.timer) {
                clearTimeout(oldest.timer);
                oldest.timer = null;
            }
            toasts.value.shift();
        }

        const newItem: ToastItem = {
            id,
            type,
            message,
            title,
            duration,
            createdAt: Date.now(),
            remaining: duration,
            isHovered: false,
            isFocused: false,
        };

        toasts.value.push(newItem);
        const reactiveItem = toasts.value[toasts.value.length - 1];
        scheduleDismissal(reactiveItem);

        return id;
    };

    const pauseToast = (id: string, reason: 'hover' | 'focus' = 'hover') => {
        const item = toasts.value.find((t) => t.id === id);
        if (!item) return;

        if (reason === 'hover') {
            item.isHovered = true;
        } else {
            item.isFocused = true;
        }

        if (item.timer) {
            clearTimeout(item.timer);
            item.timer = null;
            if (item.timerStartedAt) {
                const elapsed = Date.now() - item.timerStartedAt;
                item.remaining = Math.max(500, item.remaining - elapsed);
            }
        }
    };

    const resumeToast = (id: string, reason: 'hover' | 'focus' = 'hover') => {
        const item = toasts.value.find((t) => t.id === id);
        if (!item) return;

        if (reason === 'hover') {
            item.isHovered = false;
        } else {
            item.isFocused = false;
        }

        if (!item.isHovered && !item.isFocused && !item.timer && item.duration > 0 && item.remaining > 0) {
            scheduleDismissal(item);
        }
    };

    const resumeAllToasts = () => {
        toasts.value.forEach((item) => {
            item.isHovered = false;
            item.isFocused = false;
            if (!item.timer && item.duration > 0 && item.remaining > 0) {
                scheduleDismissal(item);
            }
        });
    };

    const clearToasts = () => {
        toasts.value.forEach((t) => {
            if (t.timer) {
                clearTimeout(t.timer);
                t.timer = null;
            }
        });
        toasts.value = [];
    };

    return {
        toasts,
        addToast,
        removeToast,
        pauseToast,
        resumeToast,
        resumeAllToasts,
        clearToasts,
        success: (msg: string, duration?: number) => addToast({ message: msg, type: 'success', duration }),
        error: (msg: string, duration?: number) => addToast({ message: msg, type: 'error', duration }),
        warning: (msg: string, duration?: number) => addToast({ message: msg, type: 'warning', duration }),
        info: (msg: string, duration?: number) => addToast({ message: msg, type: 'info', duration }),
    };
}
