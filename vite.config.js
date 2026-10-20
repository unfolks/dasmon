import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import inertia from '@inertiajs/vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        svelte(),
        inertia(),
        tailwindcss(),
    ],
    server: {
        // host: '127.0.0.1',
        host: '192.168.30.78',
        port: 5173,
        strictPort: true,
        watch: {
            ignored: ['**/.tools/**', '**/storage/framework/views/**'],
        },
    },
});
