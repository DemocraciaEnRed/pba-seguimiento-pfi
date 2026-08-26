import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue2';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/admin-app.js',
            ],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
            // App mounts with `el` on Blade markup, so use the compiler-included build.
            vue: 'vue/dist/vue.esm.js',
        },
        extensions: ['.mjs', '.js', '.json', '.vue'],
    },
});
