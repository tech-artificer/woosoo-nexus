import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Pusher from 'pusher-js';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeNexusTheme } from './composables/useNexusTheme';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
        config: { baseUrl: string };
    }
}

// Make Pusher globally available for Echo
window.Pusher = Pusher;

window.config = {
    baseUrl: document.querySelector('meta[name="asset-base-url"]')?.getAttribute('content') || '/',
};

window.addEventListener('error', (event) => {
    console.error('[GLOBAL ERROR]', event.error ?? event.message, event);
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('[UNHANDLED REJECTION]', event.reason);
});

try {
    window.Echo = new Echo({
        broadcaster: import.meta.env.VITE_BROADCAST_DRIVER ?? 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wssHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: true,
        disableStats: true,
        encrypted: true,
        enabledTransports: ['wss'],
        withCredentials: true,
    });
} catch (error) {
    console.warn('[BOOTSTRAP] Error initializing Echo:', error);
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, props.initialPage.props.ziggy)
            .mount(el);
    },
    progress: {
        color: '#B08047',
    },
});

// Sync data-theme + .dark from nexus-theme (blade inline script handles first paint)
initializeNexusTheme();
