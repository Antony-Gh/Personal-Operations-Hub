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
            }
            toasts.value.splice(index, 1);
        }
    };

    const scheduleDismissal = (item: ToastItem) => {
        if (item.duration <= 0) return;

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
        const duration = (typeof payload === 'object' && payload.duration !== undefined) ? payload.duration : DEFAULT_DURATION;
        const title = typeof payload === 'object' ? payload.title : undefined;

        // Prevent exact duplicate message from stacking within 1 second
        const isDuplicate = toasts.value.some(
            (t) => t.message === message && t.type === type && Date.now() - t.createdAt < 1000
        );
        if (isDuplicate) {
            return '';
        }

        const newItem: ToastItem = {
            id,
            type,
            message,
            title,
            duration,
            createdAt: Date.now(),
            remaining: duration,
        };

        scheduleDismissal(newItem);

        // Keep maximum visible queue
        if (toasts.value.length >= MAX_TOASTS) {
            const oldest = toasts.value[0];
            if (oldest.timer) clearTimeout(oldest.timer);
            toasts.value.shift();
        }

        toasts.value.push(newItem);
        return id;
    };

    const pauseToast = (id: string) => {
        const item = toasts.value.find((t) => t.id === id);
        if (item && item.timer) {
            clearTimeout(item.timer);
            item.timer = null;
            if (item.timerStartedAt) {
                const elapsed = Date.now() - item.timerStartedAt;
                item.remaining = Math.max(500, item.remaining - elapsed);
            }
        }
    };

    const resumeToast = (id: string) => {
        const item = toasts.value.find((t) => t.id === id);
        if (item && !item.timer && item.remaining > 0) {
            scheduleDismissal(item);
        }
    };

    const clearToasts = () => {
        toasts.value.forEach((t) => {
            if (t.timer) clearTimeout(t.timer);
        });
        toasts.value = [];
    };

    return {
        toasts,
        addToast,
        removeToast,
        pauseToast,
        resumeToast,
        clearToasts,
        success: (msg: string, duration?: number) => addToast({ message: msg, type: 'success', duration }),
        error: (msg: string, duration?: number) => addToast({ message: msg, type: 'error', duration }),
        warning: (msg: string, duration?: number) => addToast({ message: msg, type: 'warning', duration }),
        info: (msg: string, duration?: number) => addToast({ message: msg, type: 'info', duration }),
    };
}

