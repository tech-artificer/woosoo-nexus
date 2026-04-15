import fs from 'fs';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

// Work around intermittent oxide UTF-8 panic on Windows during template scanning.
process.env.TAILWIND_DISABLE_OXIDE = '1';

const devPort = Number(process.env.VITE_DEV_PORT) || 5173;
const devHost = process.env.VITE_DEV_HOST || 'localhost';
const devHttpsEnabled = (process.env.VITE_DEV_HTTPS ?? 'true') === 'true';
const devSslKeyPath = process.env.VITE_DEV_SSL_KEY || path.resolve(__dirname, '../../certs/legacy/server-key.pem');
const devSslCertPath = process.env.VITE_DEV_SSL_CERT || path.resolve(__dirname, '../../certs/legacy/server.pem');

const canUseHttps = devHttpsEnabled && fs.existsSync(devSslKeyPath) && fs.existsSync(devSslCertPath);
const httpsConfig = canUseHttps
    ? {
          key: fs.readFileSync(devSslKeyPath),
          cert: fs.readFileSync(devSslCertPath),
      }
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    optimizeDeps: {
        include: [
            '@tiptap/vue-3',
            '@tiptap/starter-kit',
            '@tiptap/extension-image',
            '@tiptap/extension-link',
            'dompurify',
        ],
    },
    server: {
        host: '0.0.0.0',
        port: devPort,
        strictPort: true,
        https: httpsConfig,
        hmr: {
            host: devHost,
            port: devPort,
            protocol: canUseHttps ? 'wss' : 'ws',
        },
    },
});
