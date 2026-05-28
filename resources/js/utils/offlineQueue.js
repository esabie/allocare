const OFFLINE_QUEUE_KEY = 'allocare.offline.queue.v1';

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

async function sendPost(url, payload) {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json, text/plain, */*',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    });
}

export function enqueueOfflinePost(url, payload) {
    const queue = readQueue();
    queue.push({
        url,
        payload,
        created_at: new Date().toISOString(),
    });
    writeQueue(queue);
}

export async function flushOfflineQueue() {
    if (!navigator.onLine) return { flushed: 0, remaining: readQueue().length };

    const queue = readQueue();
    if (queue.length === 0) return { flushed: 0, remaining: 0 };

    const remaining = [];
    let flushed = 0;

    for (const job of queue) {
        try {
            const response = await sendPost(job.url, job.payload);
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
    return { flushed, remaining: remaining.length };
}

export async function postWithOfflineQueue(url, payload, handlers = {}) {
    const { onSuccess, onQueued, onError } = handlers;

    if (!navigator.onLine) {
        enqueueOfflinePost(url, payload);
        onQueued?.();
        return { queued: true };
    }

    try {
        const response = await sendPost(url, payload);
        if (response.ok) {
            onSuccess?.();
            return { queued: false, ok: true };
        }
    } catch {
        // Fall through to queue-on-failure behavior.
    }

    enqueueOfflinePost(url, payload);
    onError?.();
    return { queued: true };
}
