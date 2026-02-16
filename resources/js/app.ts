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

if( 'performance' in window ) {
    console.log('This browser supports performance.now()');
    console.log(window.performance.now());
    window.performance.now();
}else{
    console.log('This browser does not support performance.now()');
}
 // Get the CSRF token
window.config = {
    baseUrl: document.querySelector('meta[name="asset-base-url"]')?.getAttribute('content') || '/',
};
console.log(import.meta.env);
try {
    window.Echo = new Echo({
        broadcaster: import.meta.env.VITE_BROADCAST_DRIVER ?? 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wssHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        wsPath: '/reverb',  // CRITICAL: Use nginx proxy path for TLS termination
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        disableStats: true,
        encrypted: true,
        enabledTransports: ['ws', 'wss'],
        withCredentials: true, 
    });

    // Add connection health monitoring
    if (window.Echo?.connector?.pusher) {
        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('[Admin] ✅ WebSocket connected');
        });
        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            console.warn('[Admin] ⚠️ WebSocket disconnected');
        });
        window.Echo.connector.pusher.connection.bind('error', (err: any) => {
            console.error('[Admin] 🔴 WebSocket error:', err);
        });
        window.Echo.connector.pusher.connection.bind('failed', () => {
            console.error('[Admin] 🔴 WebSocket connection failed permanently');
        });
    }
    
} catch (error) {
    console.log( import.meta.env)
    console.log('[BOOTSTRAP] Error initializing Echo:', error);
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