import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: '127.0.0.1',
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('resources/js/lang/buffTargets')) {
                        return 'lang-buffs';
                    }
                    if (id.includes('resources/js/lang/generated/ru.json')) {
                        return 'lang-ru';
                    }
                    if (id.includes('resources/js/lang/generated/en.json')) {
                        return 'lang-en';
                    }
                    if (id.includes('resources/js/lang/generated/uk.json')) {
                        return 'lang-uk';
                    }
                    if (id.includes('node_modules')) {
                        if (id.includes('vue') || id.includes('vue-router')) {
                            return 'vendor-vue';
                        }
                        return 'vendor';
                    }
                },
            },
        },
    },
});
