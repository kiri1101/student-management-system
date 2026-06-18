import { createInertiaApp } from '@inertiajs/vue3';
import Aura from '@primeuix/themes/aura';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import { createApp, defineAsyncComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

// AppLayout is the default for nearly every authenticated page, so it stays in
// the entry bundle. The auth + settings layouts are only ever needed on their
// own route groups, so they are code-split out of the entry chunk.
const AuthLayout = defineAsyncComponent(
    () => import('@/layouts/AuthLayout.vue'),
);
const SettingsLayout = defineAsyncComponent(
    () => import('@/layouts/settings/Layout.vue'),
);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'receipts/Verify':
            case name === 'student/payments/Receipt':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#10b981',
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App as DefineComponent, props) })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.dark',
                        cssLayer: {
                            name: 'primevue',
                            order: 'theme, base, primevue, utilities',
                        },
                    },
                },
            })
            // PrimeVue components are imported per page for tree-shaking
            // (AUDIT.md AUD-020); only the ToastService plugin and the
            // tooltip directive are app-level.
            .use(ToastService)
            .directive('tooltip', Tooltip)
            .mount(el as Element);
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
