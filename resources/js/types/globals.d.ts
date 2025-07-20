import { AppPageProps } from '@/types/index';
import Echo from 'laravel-echo'; // Import the Echo type from the laravel-echo package
import Pusher from 'pusher-js'; // Import Pusher if you use it globally or need its type

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

declare global {
    interface Window {
        Pusher: typeof Pusher, // Declare Pusher if you're assigning it to window.Pusher
        Echo: Echo, 
        Axios: any // Or import Axios types if available
        config: {
            baseUrl: string,
        }
    }
}