import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// The front-end tests only need Vue. They leave out laravel-vite-plugin,
// which serves Laravel's assets and fonts and refuses to start in CI.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
    },
});
