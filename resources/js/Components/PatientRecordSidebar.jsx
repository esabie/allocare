import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';

const tabs = [
    { key: 'overview', label: 'Overview', route: 'patients.show' },
    { key: 'care_plans', label: 'Care Plans', route: 'patients.careplans' },
    { key: 'risk_assessment', label: 'Risk Assessment', route: 'patients.risks' },
    { key: 'medication', label: 'eMAR', route: 'patients.mar' },
    { key: 'observations', label: 'Observations', route: 'patients.observations' },
    { key: 'handovers', label: 'Handovers', route: 'patients.handovers' },
    { key: 'wound_care', label: 'Wound care', route: 'patients.wound-care' },
    { key: 'documents', label: 'Documents', route: 'patients.documents' },
    { key: 'notes', label: 'Notes' },
    { key: 'logs', label: 'Logs', route: 'patients.logs' },
    { key: 'contacts', label: 'Contacts', route: 'patients.contacts' },
];

export default function PatientRecordSidebar({ patientSlug, active = 'overview' }) {
    const authUser = usePage().props?.auth?.user;
    const canViewReports = Boolean(authUser?.canViewReports);
    const [mobileOpen, setMobileOpen] = useState(false);

    const linkClass = (key) =>
        `block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium ${
            active === key ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'
        }`;

    const sidebarContent = (
        <>
            <div className="mb-5">
                <Link href={route('dashboard')}>
                    <ApplicationLogo className="mb-3 block w-full" />
                </Link>
                <div className="rounded-xl border border-slate-200 bg-white p-3">
                    <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Patient Record</p>
                </div>
            </div>

            <nav className="space-y-1.5">
                {tabs.map((tab) => {
                    if (tab.key === 'logs' && !canViewReports) {
                        return null;
                    }

                    if (tab.route) {
                        return (
                            <Link
                                key={tab.key}
                                href={route(tab.route, patientSlug)}
                                className={linkClass(tab.key)}
                                onClick={() => setMobileOpen(false)}
                            >
                                {tab.label}
                            </Link>
                        );
                    }

                    return (
                        <button key={tab.key} type="button" className={linkClass(tab.key)}>
                            {tab.label}
                        </button>
                    );
                })}
            </nav>
        </>
    );

    return (
        <>
            {/* Mobile hamburger */}
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

            {/* Mobile drawer */}
            {mobileOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} />
                    <aside className="relative flex h-full w-72 flex-col border-r border-slate-200 bg-slate-50 px-5 py-6 shadow-xl">
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
            <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:flex lg:flex-col">
                {sidebarContent}
            </aside>
        </>
    );
}
