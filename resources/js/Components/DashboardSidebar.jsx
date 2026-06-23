import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';

export default function DashboardSidebar({ active = 'overview', subtitle = 'Clinical Precision' }) {
    const authUser = usePage().props?.auth?.user;
    const canViewActivityLog = Boolean(authUser?.canViewActivityLog);
    const canViewAnalytics = Boolean(authUser?.canViewAnalytics);
    const canViewStaffDirectory = Boolean(authUser?.canViewStaffDirectory);
    const [mobileOpen, setMobileOpen] = useState(false);

    const items = [
        { key: 'overview', label: 'Overview', href: route('dashboard') },
        { key: 'care-notes', label: 'Care Notes', href: route('care-notes') },
        { key: 'care_alerts', label: 'Care Alerts', href: route('care-alerts') },
        ...(canViewAnalytics ? [{ key: 'analytics', label: 'Analytics', href: route('analytics') }] : []),
        ...(canViewStaffDirectory ? [{ key: 'employees', label: 'Employees', href: route('employees') }] : []),
        ...(canViewActivityLog ? [{ key: 'activity_logs', label: 'Activity Logs', href: route('admin.activity-logs') }] : []),
    ];

    const linkClass = (key) =>
        `block w-full rounded-xl px-4 py-3 text-left text-sm font-medium ${
            active === key ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'
        }`;

    const sidebarContent = (
        <>
            <div className="mb-10">
                <div className="mb-3">
                    <Link href={route('dashboard')}>
                        <ApplicationLogo className="block w-full" />
                    </Link>
                </div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">{subtitle}</p>
            </div>

            <nav className="space-y-2">
                {items.map((item) =>
                    item.href ? (
                        <Link key={item.key} href={item.href} className={linkClass(item.key)} onClick={() => setMobileOpen(false)}>
                            {item.label}
                        </Link>
                    ) : (
                        <button key={item.key} type="button" className={linkClass(item.key)}>
                            {item.label}
                        </button>
                    ),
                )}
            </nav>

        </>
    );

    return (
        <>
            {/* Mobile hamburger button */}
            <button
                type="button"
                onClick={() => setMobileOpen(true)}
                className="fixed left-4 top-4 z-40 rounded-lg bg-white p-2 shadow-md lg:hidden"
                aria-label="Open menu"
            >
                <svg className="h-6 w-6 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            {/* Mobile overlay + drawer */}
            {mobileOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} />
                    <aside className="relative flex h-full w-72 flex-col bg-slate-50 px-5 py-8 shadow-xl">
                        <button
                            type="button"
                            onClick={() => setMobileOpen(false)}
                            className="absolute right-3 top-3 rounded-lg p-1 text-slate-500 hover:bg-slate-200"
                            aria-label="Close menu"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        {sidebarContent}
                    </aside>
                </div>
            )}

            {/* Desktop sidebar */}
            <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-8 lg:flex lg:flex-col">
                {sidebarContent}
            </aside>
        </>
    );
}
