const OFFLINE_QUEUE_KEY = 'allocare.offline.queue.v3';
const LEGACY_OFFLINE_QUEUE_KEYS = [
    'allocare.offline.queue.v1',
    'allocare.offline.queue.v2',
];
let csrfTokenOverride = null;

function isLegacyInvalidJob(job) {
    const method = String(job?.method || '').toUpperCase();
    const rawUrl = String(job?.url || '');
    if (!rawUrl) return false;

    let path = rawUrl;
    try {
        const parsed = new URL(rawUrl, window.location.origin);
        path = parsed.pathname || rawUrl;
    } catch {
        // ignore URL parse errors and fallback to raw string checks
    }

    // Legacy bad payloads were PATCH /schedules (missing schedule id).
    return method === 'PATCH' && (path === '/schedules' || path.endsWith('/schedules'));
}

function readQueue() {
    try {
        const raw = window.localStorage.getItem(OFFLINE_QUEUE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];

        const filtered = parsed.filter((job) => !isLegacyInvalidJob(job));
        if (filtered.length !== parsed.length) {
            writeQueue(filtered);
        }

        return filtered;
    } catch {
        return [];
    }
}

function writeQueue(queue) {
    window.localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
}

function clearLegacyQueues() {
    for (const key of LEGACY_OFFLINE_QUEUE_KEYS) {
        try {
            window.localStorage.removeItem(key);
        } catch {
            // no-op
        }
    }
}

function csrfToken() {
    if (csrfTokenOverride) {
        return csrfTokenOverride;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') || '';
}

async function refreshCsrfToken() {
    try {
        const response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json, text/plain, */*',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return false;
        }

        const data = await response.json();
        const nextToken = typeof data?.token === 'string' ? data.token : '';
        if (!nextToken) {
            return false;
        }

        csrfTokenOverride = nextToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', nextToken);
        }

        return true;
    } catch {
        return false;
    }
}

function buildHeaders(contentType) {
    const headers = {
        Accept: 'application/json, text/plain, */*',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };
    if (contentType === 'json') {
        headers['Content-Type'] = 'application/json';
    }
    return headers;
}

function isSuccessfulFetchResponse(response) {
    if (!response) return false;
    if (response.ok) return true;
    // Laravel redirect()->back() after PATCH must not be followed — that replays PATCH on the profile URL (405).
    if (response.status === 302 || response.status === 303) return true;
    // Opaque redirect responses (some browsers) still mean the server accepted the request.
    if (response.type === 'opaqueredirect') return true;
    return false;
}

async function sendJob(job) {
    const method = (job.method || 'POST').toUpperCase();
    const contentType = job.contentType || 'json';
    const options = {
        method,
        credentials: 'same-origin',
        headers: buildHeaders(contentType),
        redirect: 'manual',
    };

    if (job.bodyType === 'form') {
        delete options.headers['Content-Type'];
        options.body = job.formFields
            ? objectToFormData(job.formFields)
            : objectToFormData(job.payload || {});
    } else if (method !== 'GET' && job.payload !== undefined) {
        options.body = JSON.stringify(job.payload);
    }

    return fetch(job.url, options);
}

function shouldQueueResponse(response) {
    if (!response) return true;
    if (response.ok) return false;

    // Client-side/request issues (validation, auth, not found, etc.) should not be queued.
    // They need user action, not retry.
    if (response.status >= 400 && response.status < 500 && response.status !== 408 && response.status !== 429) {
        return false;
    }

    // Retryable/transient cases.
    return response.status >= 500 || response.status === 408 || response.status === 429;
}

async function parseErrorPayload(response) {
    if (!response) return null;

    try {
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return await response.json();
        }

        const text = await response.text();
        return text ? { message: text } : null;
    } catch {
        return null;
    }
}

function firstErrorMessage(error) {
    if (!error) return '';
    if (typeof error?.message === 'string' && error.message.trim() !== '') {
        return error.message;
    }
    if (error?.errors && typeof error.errors === 'object') {
        for (const key of Object.keys(error.errors)) {
            const value = error.errors[key];
            if (Array.isArray(value) && value.length > 0) {
                return String(value[0]);
            }
            if (typeof value === 'string' && value.trim() !== '') {
                return value;
            }
        }
    }
    return '';
}

function notifyRequestError(errorPayload, response) {
    const message = firstErrorMessage(errorPayload)
        || (response?.status === 422
            ? 'Please check the form details and try again.'
            : response?.status === 403
                ? 'You do not have permission to perform this action.'
                : response?.status === 401
                    ? 'Your session expired. Please sign in and try again.'
                    : 'Request failed. Please try again.');

    window.dispatchEvent(new CustomEvent('allocare:request-error', {
        detail: {
            message,
            status: response?.status ?? null,
            error: errorPayload ?? null,
        },
    }));
}

function objectToFormData(obj) {
    const form = new FormData();
    Object.entries(obj || {}).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        if (typeof value === 'object' && !(value instanceof File) && !(value instanceof Blob)) {
            form.append(key, JSON.stringify(value));
            return;
        }
        form.append(key, value);
    });
    return form;
}

export function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const result = String(reader.result || '');
            const base64 = result.includes(',') ? result.split(',')[1] : result;
            resolve(base64);
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

export function enqueueOfflineJob(job) {
    const queue = readQueue();
    queue.push({
        method: 'POST',
        contentType: 'json',
        ...job,
        created_at: new Date().toISOString(),
    });
    writeQueue(queue);
}

/** @deprecated use enqueueOfflineJob */
export function enqueueOfflinePost(url, payload) {
    enqueueOfflineJob({ url, payload, method: 'POST', contentType: 'json' });
}

export function getOfflineQueueCount() {
    clearLegacyQueues();
    return readQueue().length;
}

export async function flushOfflineQueue() {
    clearLegacyQueues();
    if (!navigator.onLine) return { flushed: 0, remaining: readQueue().length };

    const queue = readQueue();
    if (queue.length === 0) return { flushed: 0, remaining: 0 };

    const remaining = [];
    let flushed = 0;
    let dropped = 0;

    for (const job of queue) {
        try {
            const response = await sendJob(job);
            if (isSuccessfulFetchResponse(response)) {
                flushed += 1;
                continue;
            }

            if (shouldQueueResponse(response)) {
                remaining.push(job);
                continue;
            }

            // Drop non-retryable client/request errors (e.g. 422 validation).
            dropped += 1;
        } catch {
            remaining.push(job);
        }
    }

    writeQueue(remaining);

    if (flushed > 0 || dropped > 0) {
        window.dispatchEvent(new CustomEvent('allocare:offline-synced', {
            detail: { flushed, remaining: remaining.length, dropped },
        }));
    }

    return { flushed, remaining: remaining.length, dropped };
}

export async function requestWithOfflineQueue({
    url,
    method = 'POST',
    payload,
    contentType = 'json',
    bodyType = 'json',
    handlers = {},
}) {
    const { onSuccess, onQueued, onError } = handlers;

    if (!navigator.onLine) {
        enqueueOfflineJob({ url, method, payload, contentType, bodyType });
        onQueued?.();
        return { queued: true };
    }

    try {
        let response = await sendJob({ url, method, payload, contentType, bodyType });
        if (response.status === 419) {
            const refreshed = await refreshCsrfToken();
            if (refreshed) {
                response = await sendJob({ url, method, payload, contentType, bodyType });
            }
        }
        if (isSuccessfulFetchResponse(response)) {
            onSuccess?.();
            return { queued: false, ok: true };
        }

        const errorPayload = await parseErrorPayload(response);
        if (shouldQueueResponse(response)) {
            enqueueOfflineJob({ url, method, payload, contentType, bodyType });
            onError?.(errorPayload, response);
            return { queued: true, ok: false, status: response.status, error: errorPayload };
        }

        if (!onError) {
            notifyRequestError(errorPayload, response);
        }
        onError?.(errorPayload, response);
        return { queued: false, ok: false, status: response.status, error: errorPayload };
    } catch {
        // queue on failure
    }

    enqueueOfflineJob({ url, method, payload, contentType, bodyType });
    onError?.();
    return { queued: true };
}

export async function postWithOfflineQueue(url, payload, handlers = {}) {
    return requestWithOfflineQueue({ url, method: 'POST', payload, handlers });
}

export async function patchWithOfflineQueue(url, payload, handlers = {}) {
    return requestWithOfflineQueue({ url, method: 'PATCH', payload, handlers });
}

/**
 * POST with optional file — queues base64 photo in JSON when offline.
 */
export async function postFormWithOfflineQueue(url, fields, { file, fileField = 'photo', handlers = {} } = {}) {
    let payload = { ...fields };

    if (file) {
        if (navigator.onLine) {
            const form = new FormData();
            Object.entries(fields).forEach(([key, value]) => {
                if (value === undefined || value === null || value === '') return;
                if (typeof value === 'boolean') {
                    form.append(key, value ? '1' : '0');
                    return;
                }
                form.append(key, value);
            });
            form.append(fileField, file);

            try {
                let response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json, text/plain, */*',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: form,
                });
                if (response.status === 419) {
                    const refreshed = await refreshCsrfToken();
                    if (refreshed) {
                        response = await fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json, text/plain, */*',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken(),
                            },
                            body: form,
                        });
                    }
                }
            if (isSuccessfulFetchResponse(response)) {
                handlers.onSuccess?.();
                return { queued: false, ok: true };
            }

                const errorPayload = await parseErrorPayload(response);
                if (!shouldQueueResponse(response)) {
                    if (!handlers.onError) {
                        notifyRequestError(errorPayload, response);
                    }
                    handlers.onError?.(errorPayload, response);
                    return { queued: false, ok: false, status: response.status, error: errorPayload };
                }
            } catch {
                // fall through to queue
            }
        }

        const base64 = await fileToBase64(file);
        payload = {
            ...fields,
            photo_base64: base64,
            photo_filename: file.name,
        };
    }

    return requestWithOfflineQueue({
        url,
        method: 'POST',
        payload,
        handlers,
    });
}

/**
 * Inertia-compatible offline POST (JSON body). Reloads page on successful sync from queue.
 */
export async function routerPostWithOffline(url, payload, { onSuccess, onQueued, onError } = {}) {
    return requestWithOfflineQueue({
        url,
        method: 'POST',
        payload,
        handlers: {
            onSuccess: () => {
                onSuccess?.();
                window.location.reload();
            },
            onQueued,
            onError,
        },
    });
}

export async function routerPatchWithOffline(url, payload, { onSuccess, onQueued, onError } = {}) {
    return requestWithOfflineQueue({
        url,
        method: 'PATCH',
        payload,
        handlers: {
            onSuccess: () => {
                onSuccess?.();
                window.location.reload();
            },
            onQueued,
            onError,
        },
    });
}
