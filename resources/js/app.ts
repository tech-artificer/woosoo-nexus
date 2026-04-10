// Register service worker only in production. In dev, unregister stale workers
// so Vite HMR requests are never intercepted or cached.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        if (import.meta.env.DEV) {
            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                await Promise.all(registrations.map(registration => registration.unregister()));

                if ('caches' in window) {
                    const cacheKeys = await caches.keys();
                    await Promise.all(
                        cacheKeys
                            .filter(key => key.startsWith('woosoo-nexus-'))
                            .map(key => caches.delete(key))
                    );
                }
            } catch (err) {
                console.warn('Service worker cleanup in dev failed:', err);
            }
            return;
        }

        navigator.serviceWorker.register('/service-worker.js').catch(err => {
            console.warn('Service worker registration failed:', err);
        });
    });
}
import '../css/app.css';
// import './bootstrap';
import axios from 'axios'

declare global {
  interface Window {
    axios: typeof axios;
  }
}

// Configure Axios CSRF + credentials globally
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token
} else {
    console.warn('⚠️ CSRF token not found in <meta> tag!')
}

// Important if you’re using cookies/sessions
axios.defaults.withCredentials = true

// Make Axios globally accessible if needed
window.axios = axios

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
// Make Pusher globally available for Echo
window.Pusher = Pusher;

 // Get the CSRF token
window.config = {
    baseUrl: document.querySelector('meta[name="asset-base-url"]')?.getAttribute('content') || '/',
};
try {
    window.Echo = new Echo({
        broadcaster: import.meta.env.VITE_BROADCAST_DRIVER ?? 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wssHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        forceTLS: true, // Always use secure WebSocket when certificate is present
        disableStats: true,
        encrypted: true,
        enabledTransports: ['wss'], // Only allow secure transport
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
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();  