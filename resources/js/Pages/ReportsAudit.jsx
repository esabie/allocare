import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

function formatTimestamp(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const reportTypes = [
    {
        id: 'audit',
        title: 'Audit Report',
        description: 'Immutable history of clinical and operational data changes.',
        status: 'ACTIVE',
        tags: ['Compliance', 'Data Changes'],
    },
    {
        id: 'gdpr',
        title: 'GDPR & Privacy Requests',
        description: 'Subject access requests, right to erasure workflow, and SAR data exports.',
        status: 'ACTIVE',
        tags: ['GDPR', 'SAR', 'Erasure', 'Breach register'],
        href: 'reports.gdpr',
    },
    {
        id: 'schedules',
        title: 'Schedule & Shifts',
        description: 'Total shifts, hours allocated, staff breakdown, and completion status.',
        status: 'ACTIVE',
        tags: ['Shifts', 'Hours'],
        href: 'reports.schedules',
    },
    {
        id: 'ecm_commissioner',
        title: 'ECM Commissioner Export',
        description: 'Attendance evidence only: check-in/out timestamps, GPS distance, and timing variance.',
        status: 'ACTIVE',
        tags: ['ECM', 'Attendance Evidence'],
        href: 'reports.ecm-commissioner',
    },
    {
        id: 'incidents',
        title: 'Incident Reports',
        description: 'Summary of reported incidents, trends, and resolution timelines.',
        status: 'ACTIVE',
        tags: ['Incidents', 'Safety'],
        href: 'reports.incidents',
    },
    {
        id: 'staff',
        title: 'Staff Performance',
        description: 'Attendance, shift completion rates, and carer allocation overview.',
        status: 'ACTIVE',
        tags: ['Shifts', 'Performance'],
        href: 'reports.staff-performance',
    },
    {
        id: 'compliance',
        title: 'Compliance & Training',
        description: 'Training certifications, expiry dates, and compliance status.',
        status: 'ACTIVE',
        tags: ['Training', 'Compliance'],
        href: 'reports.compliance-training',
    },
    {
        id: 'medications',
        title: 'Medication Audit',
        description: 'Compliance rates, refusals, omissions, controlled drug tracking, and PRN usage.',
        status: 'ACTIVE',
        tags: ['eMAR', 'Compliance'],
        href: 'reports.medications',
    },
    {
        id: 'clinical',
        title: 'Clinical Outcomes',
        description: 'Patient observations, vital trends, and health outcome metrics.',
        status: 'ACTIVE',
        tags: ['Observations', 'Outcomes'],
        href: 'reports.clinical-outcomes',
    },
];

function statusBadge(status) {
    if (status === 'ACTIVE') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if (status === 'UNDER REVIEW') return 'bg-amber-100 text-amber-700 border-amber-200';
    return 'bg-slate-100 text-slate-500 border-slate-200';
}

export default function ReportsAudit({ events = [], filters = {}, subjectTypes = [] }) {
    const [activeView, setActiveView] = useState('hub');
    const activeFilter = filters.subject_type || 'all';

    const applyFilter = (subjectType) => {
        router.get(
            route('reports'),
            subjectType === 'all' ? {} : { subject_type: subjectType },
            { preserveState: true, preserveScroll: true },
        );
    };

    const openReport = (report) => {
        if (report.href) {
            router.visit(route(report.href));
        } else if (report.id === 'audit') {
            setActiveView('audit');
        }
    };

    return (
        <>
            <Head title="Reports" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            {activeView === 'hub' ? (
                                <span className="text-slate-900">Reports</span>
                            ) : (
                                <>
                                    <button type="button" onClick={() => setActiveView('hub')} className="hover:text-slate-700">Reports</button>
                                    <span>/</span>
                                    <span className="text-slate-900">Audit Trail</span>
                                </>
                            )}
                        </div>

                        {activeView === 'hub' && (
                            <>
                                <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                                    <h1 className="text-2xl font-semibold text-slate-900">Reports</h1>
                                    <p className="mt-1 max-w-3xl text-sm text-slate-500">
                                        Select a report type to view detailed analytics and insights.
                                    </p>
                                </section>

                                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {reportTypes.map((report) => (
                                        <button
                                            key={report.id}
                                            type="button"
                                            onClick={() => openReport(report)}
                                            disabled={report.status === 'DRAFT'}
                                            className={`group relative rounded-2xl border bg-white p-5 text-left shadow-sm transition ${
                                                report.status === 'DRAFT'
                                                    ? 'cursor-not-allowed border-slate-200 opacity-60'
                                                    : 'cursor-pointer border-slate-200 hover:border-emerald-300 hover:shadow-md'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between">
                                                <h3 className="text-base font-semibold text-slate-900">{report.title}</h3>
                                                <span className={`ml-2 shrink-0 rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase ${statusBadge(report.status)}`}>
                                                    {report.status}
                                                </span>
                                            </div>

                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {report.tags.map((tag) => (
                                                    <span key={tag} className="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                        {tag}
                                                    </span>
                                                ))}
                                            </div>

                                            <p className="mt-3 text-xs text-slate-500">{report.description}</p>

                                            <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-[11px] text-slate-400">
                                                <span>LAST UPDATED</span>
                                                <span>{report.status === 'ACTIVE' ? 'Real-time' : 'Not yet available'}</span>
                                            </div>
                                        </button>
                                    ))}
                                </section>
                            </>
                        )}

                        {activeView === 'audit' && (
                            <>
                                <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h1 className="text-2xl font-semibold text-slate-900">Audit Trail</h1>
                                            <p className="mt-1 max-w-3xl text-sm text-slate-500">
                                                Immutable history of clinical and operational changes.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setActiveView('hub')}
                                            className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                                        >
                                            ← Back to Reports
                                        </button>
                                    </div>
                                </section>

                                <section className="mb-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Filter by area</p>
                                    <div className="flex flex-wrap gap-2">
                                        {subjectTypes.map((option) => (
                                            <button
                                                key={option.value}
                                                type="button"
                                                onClick={() => applyFilter(option.value)}
                                                className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
                                                    activeFilter === option.value
                                                        ? 'bg-emerald-600 text-white'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                                }`}
                                            >
                                                {option.label}
                                            </button>
                                        ))}
                                    </div>
                                </section>

                                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[700px] border-collapse text-left text-sm">
                                            <thead className="bg-slate-50">
                                                <tr>
                                                    <th className="border border-slate-200 px-3 py-2">When</th>
                                                    <th className="border border-slate-200 px-3 py-2">User</th>
                                                    <th className="border border-slate-200 px-3 py-2">Subject</th>
                                                    <th className="border border-slate-200 px-3 py-2">Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {events.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={4} className="border border-slate-200 px-3 py-8 text-center text-slate-500">
                                                            No audit events recorded yet. Changes to patients, staff, schedules, care plans, and clinical records will appear here.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    events.map((event) => (
                                                        <tr key={event.id} className="odd:bg-white even:bg-slate-50/30">
                                                            <td className="whitespace-nowrap border border-slate-200 px-3 py-2">
                                                                {formatTimestamp(event.created_at)}
                                                            </td>
                                                            <td className="border border-slate-200 px-3 py-2">
                                                                {event.user_name || (event.user_id ? `User #${event.user_id}` : 'System')}
                                                            </td>
                                                            <td className="border border-slate-200 px-3 py-2">
                                                                {event.subject_label || event.subject_key || '-'}
                                                            </td>
                                                            <td className="border border-slate-200 px-3 py-2">{event.description}</td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
