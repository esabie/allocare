import { Head, Link, router } from '@inertiajs/react';
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

function actionLabel(action) {
    if (!action) {
        return '-';
    }

    return action.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

export default function ReportsAudit({ events = [], filters = {}, subjectTypes = [] }) {
    const activeFilter = filters.subject_type || 'all';

    const applyFilter = (subjectType) => {
        router.get(
            route('reports'),
            subjectType === 'all' ? {} : { subject_type: subjectType },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Audit & Reports" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">
                                Dashboard
                            </Link>
                            <span>/</span>
                            <span className="text-slate-900">Audit & Reports</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">Audit & Reports</h1>
                            <p className="mt-1 max-w-3xl text-sm text-slate-500">
                                Immutable history of clinical and operational changes.
                            </p>
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
                                <table className="w-full min-w-[1000px] border-collapse text-left text-sm">
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
                    </main>
                </div>
            </div>
        </>
    );
}
