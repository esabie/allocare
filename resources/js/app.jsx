import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { debugLogger } from './utils/debugLogger';
import { flushOfflineQueue } from './utils/offlineQueue';

createInertiaApp({
    title: () => 'AlloCare',
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

window.addEventListener('allocare:offline-synced', (event) => {
    const flushed = event.detail?.flushed ?? 0;
    const dropped = event.detail?.dropped ?? 0;
    if (flushed < 1 && dropped < 1) {
        return;
    }

    const remaining = event.detail?.remaining ?? 0;
    const banner = document.createElement('div');
    if (dropped > 0 && flushed > 0) {
        banner.textContent = remaining > 0
            ? `Synced ${flushed} offline action(s), dropped ${dropped} invalid request(s). ${remaining} still waiting.`
            : `Synced ${flushed} offline action(s), dropped ${dropped} invalid request(s).`;
    } else if (dropped > 0) {
        banner.textContent = remaining > 0
            ? `Dropped ${dropped} invalid offline request(s). ${remaining} still waiting.`
            : `Dropped ${dropped} invalid offline request(s).`;
    } else {
        banner.textContent = remaining > 0
            ? `Synced ${flushed} offline action(s). ${remaining} still waiting.`
            : `Synced ${flushed} offline action(s).`;
    }
    banner.setAttribute('role', 'status');
    banner.className = 'fixed bottom-4 right-4 z-[9999] max-w-sm rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-lg';
    document.body.appendChild(banner);
    window.setTimeout(() => banner.remove(), 5000);
});

window.addEventListener('allocare:request-error', (event) => {
    const message = event.detail?.message || 'Request failed. Please try again.';
    const status = event.detail?.status;
    const banner = document.createElement('div');
    banner.textContent = status ? `${message} (HTTP ${status})` : message;
    banner.setAttribute('role', 'alert');
    banner.className = 'fixed bottom-4 right-4 z-[9999] max-w-sm rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900 shadow-lg';
    document.body.appendChild(banner);
    window.setTimeout(() => banner.remove(), 6000);
});
