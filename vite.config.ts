import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import fs from 'fs';

export default defineConfig(({ command }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    build: {
        chunkSizeWarningLimit: 1000,
    },
    ...(command === 'serve'
        ? {
              server: {
                  host: 'student-management-system.test',
                  hmr: {
                      host: 'student-management-system.test',
                      protocol: 'wss',
                  },
                  cors: true,
                  https: {
                      key: fs.readFileSync('D:/laragon/etc/ssl/laragon.key'),
                      cert: fs.readFileSync('D:/laragon/etc/ssl/laragon.crt'),
                  },
              },
          }
        : {}),
}));
