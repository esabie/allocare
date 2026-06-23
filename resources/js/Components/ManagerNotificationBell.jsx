import { Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

function canReceiveManagerNotifications(user) {
    return Boolean(user?.canViewReports);
}

export default function ManagerNotificationBell() {
    const authUser = usePage().props?.auth?.user;
    const shared = usePage().props?.managerNotifications;
    const enabled = canReceiveManagerNotifications(authUser);

    const [open, setOpen] = useState(false);
    const [items, setItems] = useState(Array.isArray(shared?.items) ? shared.items : []);
    const [count, setCount] = useState(shared?.count ?? 0);
    const previousCountRef = useRef(count);

    const showBrowserPush = useCallback((notificationItems) => {
        if (typeof window === 'undefined' || !('Notification' in window)) {
            return;
        }
        if (Notification.permission !== 'granted') {
            return;
        }
        const latest = notificationItems[0];
        if (!latest) {
            return;
        }
        new Notification(latest.title, {
            body: latest.body,
            tag: latest.id,
        });
    }, []);

    const refreshNotifications = useCallback(async (triggerPush = false) => {
        if (!enabled) {
            return;
        }
        try {
            const response = await fetch(route('api.staff-notifications'), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            const nextItems = Array.isArray(payload.items) ? payload.items : [];
            const nextCount = payload.count ?? 0;

            if (triggerPush && nextCount > previousCountRef.current) {
                showBrowserPush(nextItems);
            }

            previousCountRef.current = nextCount;
            setItems(nextItems);
            setCount(nextCount);
        } catch {
            // Ignore polling errors — care alerts remain the source of truth.
        }
    }, [enabled, showBrowserPush]);

    useEffect(() => {
        if (!enabled) {
            return undefined;
        }
        if (typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }
        const interval = window.setInterval(() => refreshNotifications(true), 45000);
        return () => window.clearInterval(interval);
    }, [enabled, refreshNotifications]);

    useEffect(() => {
        setItems(Array.isArray(shared?.items) ? shared.items : []);
        setCount(shared?.count ?? 0);
        previousCountRef.current = shared?.count ?? 0;
    }, [shared]);

    const markRead = async (id, href) => {
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await fetch(route('api.staff-notifications.read', { id }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                credentials: 'same-origin',
            });
            setItems((prev) => prev.filter((item) => item.id !== id));
            setCount((prev) => Math.max(0, prev - 1));
            previousCountRef.current = Math.max(0, previousCountRef.current - 1);
            setOpen(false);
            if (href) {
                window.location.href = href;
            }
        } catch {
            // Ignore mark-read errors.
        }
    };

    if (!enabled) {
        return null;
    }

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => {
                    setOpen((value) => !value);
                    refreshNotifications(false);
                }}
                className="relative flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
                aria-label={`Manager notifications${count ? `, ${count} unread` : ''}`}
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-5 w-5">
                    <path d="M5.85 3.5a.75.75 0 00-1.117-1 9.719 9.719 0 00-2.348 4.876.75.75 0 001.479.248A8.219 8.219 0 015.85 3.5zM19.267 2.5a.75.75 0 10-1.118 1 8.22 8.22 0 011.987 4.124.75.75 0 001.48-.248A9.72 9.72 0 0019.266 2.5z" />
                    <path fillRule="evenodd" d="M12 2.25A6.75 6.75 0 005.25 9v.75a8.217 8.217 0 01-2.119 5.52.75.75 0 00.298 1.206c1.544.57 3.16.874 4.773.874s3.23-.304 4.773-.874a.75.75 0 00.298-1.206 8.217 8.217 0 01-2.119-5.52V9A6.75 6.75 0 0012 2.25zM9.75 18.75a2.25 2.25 0 004.5 0 .75.75 0 011.5 0 3.75 3.75 0 11-7.5 0 .75.75 0 011.5 0z" clipRule="evenodd" />
                </svg>
                {count > 0 && (
                    <span className="absolute -right-1 -top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
                        {count > 9 ? '9+' : count}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 z-50 mt-2 w-80 rounded-xl border border-slate-200 bg-white shadow-lg">
                    <div className="border-b border-slate-100 px-4 py-3">
                        <p className="text-sm font-semibold text-slate-900">Alerts</p>
                        <p className="text-xs text-slate-500">Medication outcomes requiring review</p>
                    </div>
                    <div className="max-h-80 overflow-y-auto">
                        {items.length === 0 ? (
                            <p className="px-4 py-6 text-center text-sm text-slate-500">No new alerts</p>
                        ) : (
                            items.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => markRead(item.id, item.href)}
                                    className="block w-full border-b border-slate-50 px-4 py-3 text-left hover:bg-slate-50"
                                >
                                    <p className="text-sm font-semibold text-slate-900">{item.title}</p>
                                    <p className="mt-1 text-xs text-slate-600">{item.body}</p>
                                </button>
                            ))
                        )}
                    </div>
                    <div className="border-t border-slate-100 px-4 py-2">
                        <Link href={route('care-alerts')} className="text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                            Open Care Alerts
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
