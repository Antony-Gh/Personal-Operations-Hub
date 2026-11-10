import { ref, computed } from 'vue';

export type ThemePreference = 'light' | 'dark' | 'system';

const theme = ref<ThemePreference>('system');
const isDark = ref(false);
let isListenerAttached = false;

function applyThemeToDom(targetTheme: ThemePreference) {
    if (typeof window === 'undefined') return;

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const shouldBeDark = targetTheme === 'dark' || (targetTheme === 'system' && prefersDark);

    isDark.value = shouldBeDark;

    if (shouldBeDark) {
        document.documentElement.classList.add('dark');
        document.documentElement.style.colorScheme = 'dark';
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
    }
}

function handleMediaChange() {
    if (theme.value === 'system') {
        applyThemeToDom('system');
    }
}

export function useTheme() {
    const initTheme = (serverPreference?: ThemePreference) => {
        if (typeof window === 'undefined') return;

        const stored = localStorage.getItem('theme') as ThemePreference | null;
        if (stored && ['light', 'dark', 'system'].includes(stored)) {
            theme.value = stored;
        } else if (serverPreference && ['light', 'dark', 'system'].includes(serverPreference)) {
            theme.value = serverPreference;
            localStorage.setItem('theme', serverPreference);
        } else {
            theme.value = 'system';
        }

        applyThemeToDom(theme.value);

        if (!isListenerAttached && window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleMediaChange);
            isListenerAttached = true;
        }
    };

    const setTheme = (newTheme: ThemePreference) => {
        theme.value = newTheme;
        if (typeof window !== 'undefined') {
            localStorage.setItem('theme', newTheme);
            applyThemeToDom(newTheme);
        }
    };

    const toggleTheme = () => {
        // If currently dark (either through 'dark' or 'system' with OS dark), toggle to 'light'.
        // If currently light, toggle to 'dark'.
        const nextTheme = isDark.value ? 'light' : 'dark';
        setTheme(nextTheme);
    };

    return {
        theme,
        isDark,
        initTheme,
        setTheme,
        toggleTheme,
    };
}

