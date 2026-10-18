import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Downloaded at build time and served from our own domain, so the
            // font also works offline in the installed app.
            fonts: [
                bunny('Fira Sans', {
                    weights: [400, 600, 700],
                    variable: '--font-brand',
                }),
            ],
        }),
        vue(),
    ],
    server: {
        // Listen inside the Lando node container, but tell the browser to
        // connect through the port Lando publishes on localhost.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
    },
});
