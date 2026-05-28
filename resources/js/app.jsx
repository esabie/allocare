import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { debugLogger } from './utils/debugLogger';
import { flushOfflineQueue } from './utils/offlineQueue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        debugLogger.info('Inertia', 'Resolving page component', { name });
        return resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx'));
    },
    setup({ el, App, props }) {
        debugLogger.info('Inertia', 'App setup started', {
            component: props?.initialPage?.component,
            url: props?.initialPage?.url,
        });
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

window.addEventListener('error', (event) => {
    debugLogger.error('Window', 'Unhandled error', {
        message: event.message,
        source: event.filename,
        line: event.lineno,
        column: event.colno,
    });
});

window.addEventListener('unhandledrejection', (event) => {
    debugLogger.error('Window', 'Unhandled promise rejection', {
        reason: event.reason,
    });
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
        flushOfflineQueue().catch(() => {});
    });
}

window.addEventListener('online', () => {
    flushOfflineQueue().catch(() => {});
});
