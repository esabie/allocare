const OFFLINE_QUEUE_KEY = 'allocare.offline.queue.v2';

function readQueue() {
    try {
        const raw = window.localStorage.getItem(OFFLINE_QUEUE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function writeQueue(queue) {
    window.localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') || '';
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

async function sendJob(job) {
    const method = (job.method || 'POST').toUpperCase();
    const contentType = job.contentType || 'json';
    const options = {
        method,
        credentials: 'same-origin',
        headers: buildHeaders(contentType),
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
    return readQueue().length;
}

export async function flushOfflineQueue() {
    if (!navigator.onLine) return { flushed: 0, remaining: readQueue().length };

    const queue = readQueue();
    if (queue.length === 0) return { flushed: 0, remaining: 0 };

    const remaining = [];
    let flushed = 0;

    for (const job of queue) {
        try {
            const response = await sendJob(job);
            if (response.ok) {
                flushed += 1;
                continue;
            }
            remaining.push(job);
        } catch {
            remaining.push(job);
        }
    }

    writeQueue(remaining);

    if (flushed > 0) {
        window.dispatchEvent(new CustomEvent('allocare:offline-synced', { detail: { flushed, remaining: remaining.length } }));
    }

    return { flushed, remaining: remaining.length };
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

    const runOnline = async () => {
        const response = await sendJob({ url, method, payload, contentType, bodyType });
        return response.ok;
    };

    if (!navigator.onLine) {
        enqueueOfflineJob({ url, method, payload, contentType, bodyType });
        onQueued?.();
        return { queued: true };
    }

    try {
        if (await runOnline()) {
            onSuccess?.();
            return { queued: false, ok: true };
        }
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
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json, text/plain, */*',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: form,
                });
                if (response.ok) {
                    handlers.onSuccess?.();
                    return { queued: false, ok: true };
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
