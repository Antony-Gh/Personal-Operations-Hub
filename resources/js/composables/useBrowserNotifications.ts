import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { UserSetting } from '@/types';

const permissionState = ref<NotificationPermission>(
    typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'denied'
);

export function useBrowserNotifications() {
    const isSupported = typeof window !== 'undefined' && 'Notification' in window;

    const refreshPermission = (): NotificationPermission => {
        if (!isSupported) {
            permissionState.value = 'denied';
            return 'denied';
        }
        permissionState.value = Notification.permission;
        return permissionState.value;
    };

    const requestPermission = async (): Promise<NotificationPermission> => {
        if (!isSupported) {
            return 'denied';
        }
        try {
            const result = await Notification.requestPermission();
            permissionState.value = result;
            return result;
        } catch {
            return refreshPermission();
        }
    };

    const sendDesktopNotification = async (
        title: string,
        body: string,
        actionUrl: string = '/dashboard',
        setting?: UserSetting | null,
        tag?: string
    ): Promise<boolean> => {
        if (!isSupported) {
            return false;
        }

        if (setting && (!setting.notifications_enabled || !setting.browser_notifications_enabled)) {
            return false;
        }

        if (Notification.permission !== 'granted') {
            permissionState.value = Notification.permission;
            return false;
        }

        try {
            if ('serviceWorker' in navigator) {
                const reg = await navigator.serviceWorker.getRegistration();
                if (reg && typeof reg.showNotification === 'function') {
                    await reg.showNotification(title, {
                        body,
                        icon: '/apple-touch-icon.png',
                        badge: '/favicon-32x32.png',
                        tag: tag || `ops-hub-${Date.now()}`,
                        data: { url: actionUrl },
                    });
                    return true;
                }
            }

            const n = new Notification(title, {
                body,
                icon: '/apple-touch-icon.png',
                tag: tag || `ops-hub-${Date.now()}`,
            });

            n.onclick = () => {
                window.focus();
                if (actionUrl) {
                    router.visit(actionUrl);
                }
                n.close();
            };

            return true;
        } catch {
            return false;
        }
    };

    return {
        isSupported,
        permissionState,
        refreshPermission,
        requestPermission,
        sendDesktopNotification,
    };
}

