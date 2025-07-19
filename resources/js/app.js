import '../css/app.css';
import './bootstrap';

// Set timezone to Asia/Jakarta
Intl.DateTimeFormat().resolvedOptions().timeZone = 'Asia/Jakarta';

// Import SweetAlert2
import Swal from 'sweetalert2';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createI18n } from 'vue-i18n';
import id from './lang/id.js';
import en from './lang/en.js';
import VueApexCharts from 'vue3-apexcharts';

const appName = import.meta.env.VITE_APP_NAME || 'YMSoft';

const messages = { id, en };
const currentLang = localStorage.getItem('currentLang') || 'id';
const i18n = createI18n({
    legacy: false,
    locale: currentLang,
    fallbackLocale: 'id',
    messages,
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        
        // Make Swal available globally
        app.config.globalProperties.$swal = Swal;
        window.Swal = Swal;
        
        return app
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .use(VueApexCharts)
            .component('apexchart', VueApexCharts)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
