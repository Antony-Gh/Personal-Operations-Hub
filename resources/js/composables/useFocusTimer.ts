import { ref, computed } from 'vue';
import axios from 'axios';
import { ActiveFocusSession, UserSetting } from '@/types';
import { useNotificationSound } from '@/composables/useNotificationSound';
import { useBrowserNotifications } from '@/composables/useBrowserNotifications';

const STORAGE_KEY = 'ops_hub_active_focus_timer_v1';

const activeSession = ref<ActiveFocusSession | null>(null);
const displayRemainingSeconds = ref<number>(0);
const displayElapsedSeconds = ref<number>(0);
const isBusy = ref<boolean>(false);
const isExpanded = ref<boolean>(false);

let anchorClientMs = 0;
let baseElapsedAtAnchor = 0;
let tickIntervalId: ReturnType<typeof setInterval> | null = null;
let autoCompletionInFlight = false;
let currentSettingGetter: (() => UserSetting | undefined) | null = null;

function persistLocalSnapshot(session: ActiveFocusSession | null): void {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        if (!session) {
            localStorage.removeItem(STORAGE_KEY);
            return;
        }
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                session,
                anchorClientMs,
                baseElapsedAtAnchor,
            })
        );
    } catch {
        // Ignore storage quota errors
    }
}

function hydrateSession(session: ActiveFocusSession | null): void {
    activeSession.value = session;

    if (!session || (session.status !== 'running' && session.status !== 'paused')) {
        activeSession.value = null;
        displayRemainingSeconds.value = 0;
        displayElapsedSeconds.value = 0;
        stopTicker();
        persistLocalSnapshot(null);
        return;
    }

    anchorClientMs = Date.now();
    baseElapsedAtAnchor = Math.max(0, session.elapsed_seconds || 0);
    recalculateDisplay();
    persistLocalSnapshot(session);

    if (session.status === 'running') {
        startTicker();
    } else {
        stopTicker();
    }
}

function recalculateDisplay(): void {
    const s = activeSession.value;
    if (!s) {
        displayElapsedSeconds.value = 0;
        displayRemainingSeconds.value = 0;
        return;
    }

    const totalPlanned = Math.max(60, (s.planned_minutes || 25) * 60);
    let currentElapsed = baseElapsedAtAnchor;

    if (s.status === 'running') {
        const deltaSec = Math.max(0, Math.floor((Date.now() - anchorClientMs) / 1000));
        currentElapsed = baseElapsedAtAnchor + deltaSec;
    }

    displayElapsedSeconds.value = currentElapsed;
    displayRemainingSeconds.value = Math.max(0, totalPlanned - currentElapsed);

    if (s.status === 'running' && displayRemainingSeconds.value <= 0 && !autoCompletionInFlight) {
        void finishAutomatically();
    }
}

function startTicker(): void {
    if (tickIntervalId !== null) {
        return;
    }
    tickIntervalId = setInterval(() => {
        recalculateDisplay();
    }, 500);
}

function stopTicker(): void {
    if (tickIntervalId !== null) {
        clearInterval(tickIntervalId);
        tickIntervalId = null;
    }
}

async function finishAutomatically(): Promise<void> {
    if (!activeSession.value || autoCompletionInFlight) {
        return;
    }

    autoCompletionInFlight = true;
    const finishedMode = activeSession.value.mode;
    const finishedTaskTitle = activeSession.value.task?.title;
    const sessionId = activeSession.value.id;

    try {
        await axios.post(route('focus.complete', sessionId), {
            reached_target: true,
        });

        const setting = currentSettingGetter ? currentSettingGetter() : undefined;
        const { playForUserSetting } = useNotificationSound();
        const { sendDesktopNotification } = useBrowserNotifications();

        playForUserSetting(setting);

        const notifTitle =
            finishedMode === 'focus'
                ? 'Focus Session Complete!'
                : finishedMode === 'short_break'
                  ? 'Short Break Finished'
                  : 'Long Break Finished';
        const notifBody = finishedTaskTitle
            ? `Logged focus time on "${finishedTaskTitle}".`
            : 'Ready for your next block.';

        await sendDesktopNotification(notifTitle, notifBody, '/tasks', setting, `focus-${sessionId}`);

        hydrateSession(null);

        if (finishedMode === 'focus' && setting?.focus_auto_start_breaks) {
            const breakMin = setting.focus_short_break_minutes || 5;
            const res = await axios.post(route('focus.start'), {
                mode: 'short_break',
                planned_minutes: breakMin,
            });
            hydrateSession(res.data.session || null);
        }
    } catch {
        stopTicker();
    } finally {
        autoCompletionInFlight = false;
    }
}

export function useFocusTimer() {
    const syncFromServerProps = (
        serverSession: ActiveFocusSession | null | undefined,
        settingGetter?: () => UserSetting | undefined
    ): void => {
        if (settingGetter) {
            currentSettingGetter = settingGetter;
        }

        if (serverSession) {
            hydrateSession(serverSession);
            return;
        }

        if (serverSession === null) {
            hydrateSession(null);
            return;
        }

        if (typeof window !== 'undefined' && !activeSession.value) {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (parsed?.session) {
                        activeSession.value = parsed.session;
                        anchorClientMs = parsed.anchorClientMs || Date.now();
                        baseElapsedAtAnchor = parsed.baseElapsedAtAnchor || 0;
                        recalculateDisplay();
                        if (parsed.session.status === 'running') {
                            startTicker();
                        }
                    }
                }
            } catch {
                // Ignore corrupted snapshot
            }
        }
    };

    const startFocusSession = async (payload: {
        mode: 'focus' | 'short_break' | 'long_break';
        planned_minutes?: number;
        task_id?: number | null;
    }): Promise<void> => {
        isBusy.value = true;
        try {
            const response = await axios.post(route('focus.start'), payload);
            hydrateSession(response.data.session || null);
            isExpanded.value = true;
        } finally {
            isBusy.value = false;
        }
    };

    const pauseSession = async (): Promise<void> => {
        if (!activeSession.value || isBusy.value) {
            return;
        }
        isBusy.value = true;
        try {
            const response = await axios.post(route('focus.pause', activeSession.value.id));
            hydrateSession(response.data.session || null);
        } finally {
            isBusy.value = false;
        }
    };

    const resumeSession = async (): Promise<void> => {
        if (!activeSession.value || isBusy.value) {
            return;
        }
        isBusy.value = true;
        try {
            const response = await axios.post(route('focus.resume', activeSession.value.id));
            hydrateSession(response.data.session || null);
        } finally {
            isBusy.value = false;
        }
    };

    const resetSession = async (): Promise<void> => {
        if (!activeSession.value || isBusy.value) {
            return;
        }
        isBusy.value = true;
        try {
            const response = await axios.post(route('focus.reset', activeSession.value.id));
            hydrateSession(response.data.session || null);
        } finally {
            isBusy.value = false;
        }
    };

    const completeEarly = async (): Promise<void> => {
        if (!activeSession.value || isBusy.value) {
            return;
        }
        isBusy.value = true;
        try {
            await axios.post(route('focus.complete', activeSession.value.id), {
                reached_target: false,
            });
            hydrateSession(null);
        } finally {
            isBusy.value = false;
        }
    };

    const stopSession = async (): Promise<void> => {
        if (!activeSession.value || isBusy.value) {
            return;
        }
        isBusy.value = true;
        try {
            await axios.post(route('focus.stop', activeSession.value.id));
            hydrateSession(null);
        } finally {
            isBusy.value = false;
        }
    };

    const formattedRemaining = computed((): string => {
        const total = Math.max(0, displayRemainingSeconds.value);
        const mins = Math.floor(total / 60);
        const secs = total % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    });

    const progressPercent = computed((): number => {
        if (!activeSession.value) {
            return 0;
        }
        const total = Math.max(60, activeSession.value.planned_minutes * 60);
        return Math.min(100, Math.round((displayElapsedSeconds.value / total) * 100));
    });

    return {
        activeSession,
        displayRemainingSeconds,
        displayElapsedSeconds,
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
    };
}

