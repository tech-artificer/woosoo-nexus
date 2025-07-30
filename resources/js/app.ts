import '../css/app.css';
import './bootstrap';

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


// const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content'); // Get the CSRF token
window.config = {
    baseUrl: document.querySelector('meta[name="asset-base-url"]')?.getAttribute('content') || '/',
};

try {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        // cluster: 'mt1',
        // authEndpoint: '/broadcasting/auth',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wssHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        disableStats: true,
        encrypted: true, // Also important for WSS
        enabledTransports: ['ws', 'wss'],
        withCredentials: true, 
    });
    
} catch (error) {
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