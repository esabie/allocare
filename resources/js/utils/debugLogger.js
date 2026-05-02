const DEBUG_STORAGE_KEY = 'careos.debug';
const DEBUG_NAMESPACE = 'CareOS';

function isEnabled() {
    try {
        const localOverride = window?.localStorage?.getItem(DEBUG_STORAGE_KEY);
        if (localOverride === 'true') return true;
        if (localOverride === 'false') return false;
    } catch {
        // Ignore storage errors and fall back to environment checks.
    }

    return import.meta.env.DEV || import.meta.env.VITE_DEBUG_LOGS === 'true';
}

function makePrefix(scope) {
    const timestamp = new Date().toISOString();
    return `[${DEBUG_NAMESPACE}] [${scope}] [${timestamp}]`;
}

function toConsole(method, scope, message, meta) {
    if (!isEnabled()) return;
    const prefix = makePrefix(scope);
    if (meta === undefined) {
        console[method](`${prefix} ${message}`);
        return;
    }
    console[method](`${prefix} ${message}`, meta);
}

export const debugLogger = {
    enable() {
        window?.localStorage?.setItem(DEBUG_STORAGE_KEY, 'true');
    },
    disable() {
        window?.localStorage?.setItem(DEBUG_STORAGE_KEY, 'false');
    },
    clearPreference() {
        window?.localStorage?.removeItem(DEBUG_STORAGE_KEY);
    },
    info(scope, message, meta) {
        toConsole('info', scope, message, meta);
    },
    warn(scope, message, meta) {
        toConsole('warn', scope, message, meta);
    },
    error(scope, message, meta) {
        toConsole('error', scope, message, meta);
    },
};

