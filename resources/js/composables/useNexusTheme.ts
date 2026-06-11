import { onMounted, ref } from 'vue';

type NexusTheme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'nexus-theme';
const LEGACY_KEY = 'appearance';

function resolveTheme(value: NexusTheme): 'light' | 'dark' {
    if (value !== 'system') {
        return value;
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(value: NexusTheme): void {
    if (typeof window === 'undefined') return;

    const resolved = resolveTheme(value);
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.classList.toggle('dark', resolved === 'dark');
}

export function initializeNexusTheme(): void {
    if (typeof window === 'undefined') return;

    const stored =
        (localStorage.getItem(STORAGE_KEY) as NexusTheme | null) ??
        (localStorage.getItem(LEGACY_KEY) as NexusTheme | null) ??
        'system';

    applyTheme(stored);

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        const current = (localStorage.getItem(STORAGE_KEY) as NexusTheme | null) ?? 'system';
        if (current === 'system') {
            applyTheme('system');
        }
    });
}

const theme = ref<NexusTheme>('system');

export function useNexusTheme() {
    onMounted(() => {
        const stored =
            (localStorage.getItem(STORAGE_KEY) as NexusTheme | null) ??
            (localStorage.getItem(LEGACY_KEY) as NexusTheme | null) ??
            'system';
        theme.value = stored;
    });

    function setTheme(value: NexusTheme): void {
        theme.value = value;
        localStorage.setItem(STORAGE_KEY, value);
        // Keep legacy key in sync so existing useAppearance callers stay consistent
        localStorage.setItem(LEGACY_KEY, value);
        // Keep the `appearance` cookie in sync so the server-side first paint
        // (HandleAppearance middleware) matches the client theme instead of
        // going stale after a toggle.
        document.cookie = `${LEGACY_KEY}=${value};path=/;max-age=31536000;SameSite=Lax`;
        applyTheme(value);
    }

    return { theme, setTheme };
}
