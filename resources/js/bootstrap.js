/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
import { debugLogger } from './utils/debugLogger';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

let requestCounter = 0;

window.axios.interceptors.request.use(
    (config) => {
        requestCounter += 1;
        const requestId = `req-${requestCounter}`;
        config.headers['X-Debug-Request-Id'] = requestId;
        config.metadata = { requestId, startedAt: Date.now() };

        debugLogger.info('HTTP', 'Request started', {
            requestId,
            method: config.method,
            url: config.url,
            params: config.params,
        });

        return config;
    },
    (error) => {
        debugLogger.error('HTTP', 'Request setup failed', { message: error?.message });
        return Promise.reject(error);
    },
);

window.axios.interceptors.response.use(
    (response) => {
        const requestId = response?.config?.metadata?.requestId;
        const startedAt = response?.config?.metadata?.startedAt || Date.now();
        debugLogger.info('HTTP', 'Request completed', {
            requestId,
            status: response.status,
            url: response?.config?.url,
            durationMs: Date.now() - startedAt,
        });
        return response;
    },
    (error) => {
        const requestId = error?.config?.metadata?.requestId;
        const startedAt = error?.config?.metadata?.startedAt || Date.now();
        debugLogger.error('HTTP', 'Request failed', {
            requestId,
            status: error?.response?.status,
            url: error?.config?.url,
            durationMs: Date.now() - startedAt,
            message: error?.message,
        });
        return Promise.reject(error);
    },
);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//     wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });
