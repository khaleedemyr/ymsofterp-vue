import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    optimizeDeps: {
        include: ['firebase/app', 'firebase/messaging'],
        exclude: [],
    },
    build: {
        commonjsOptions: {
            include: [/firebase/, /node_modules/],
        },
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue': ['vue', 'vue-router', '@inertiajs/vue3'],
                    'vendor-charts': ['apexcharts', 'vue3-apexcharts'],
                    'vendor-ui': ['element-plus', '@headlessui/vue'],
                    'vendor-utils': ['axios', 'dayjs', 'date-fns'],
                },
            },
        },
    },
});
