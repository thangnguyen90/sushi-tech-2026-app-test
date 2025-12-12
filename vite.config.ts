import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import eslint from 'vite-plugin-eslint';

export default defineConfig({
    plugins: [
        vue(),

        laravel({
            input: ['resources/css/app.scss', 'resources/js/app.ts'],
            refresh: true,
        }),
        eslint({
            fix: true,
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
            '@/stores': '/resources/js/stores',
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['color-functions', 'global-builtin', 'import']
            },
        }
    },
});
