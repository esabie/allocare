import { Head, Link } from '@inertiajs/react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

function formatDateTime(value) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';
    return parsed.toLocaleString([], {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function MetricCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <article className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-2 text-2xl font-semibold ${accent}`}>{value}</p>
        </article>
    );
}

export default function Analytics({
    summary = {},
    careAlerts = [],
    dailyVisitTrend = [],
    recentMissedShifts = [],
    recentActivity = [],
}) {
    return (
        <>
            <Head title="Analytics" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar active="analytics" subtitle="Admin Console" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <span className="text-slate-900">Analytics</span>
                        </div>

                        <section className="mb-4 rounded-2xl bg-white px-5 py-4">
                            <h1 className="text-2xl font-semibold text-slate-900">Operational Analytics</h1>
                            <p className="text-sm text-slate-500">
                                Live view of visits, missed shifts, open care alerts, and recent user activity.
                            </p>
                        </section>

                        <section className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <MetricCard label="Visits This Week" value={summary.visitsTotal ?? 0} />
                            <MetricCard label="Completed" value={summary.visitsCompleted ?? 0} accent="text-emerald-700" />
                            <MetricCard label="Missed" value={summary.visitsMissed ?? 0} accent="text-rose-700" />
                            <MetricCard label="Open Care Alerts" value={summary.totalCareAlerts ?? 0} accent="text-amber-700" />
                        </section>

                        <section className="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl border border-slate-200 bg-white p-5 xl:col-span-2">
                                <h2 className="mb-4 text-lg font-semibold text-slate-900">7-Day Visit Trend</h2>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[520px] border-collapse text-left text-sm">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="border border-slate-200 px-3 py-2">Day</th>
                                                <th className="border border-slate-200 px-3 py-2">Total</th>
                                                <th className="border border-slate-200 px-3 py-2">Completed</th>
                                                <th className="border border-slate-200 px-3 py-2">Missed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {dailyVisitTrend.length === 0 ? (
                                                <tr>
                                                    <td colSpan={4} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                        No visit trend data available.
                                                    </td>
                                                </tr>
                                            ) : (
                                                dailyVisitTrend.map((row) => (
                                                    <tr key={row.label} className="odd:bg-white even:bg-slate-50/30">
                                                        <td className="border border-slate-200 px-3 py-2">{row.label}</td>
                                                        <td className="border border-slate-200 px-3 py-2 font-semibold text-slate-800">{row.total}</td>
                                                        <td className="border border-slate-200 px-3 py-2 text-emerald-700">{row.completed}</td>
                                                        <td className="border border-slate-200 px-3 py-2 text-rose-700">{row.missed}</td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </article>

                            <article className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="mb-4 text-lg font-semibold text-slate-900">Care Alert Breakdown</h2>
                                <ul className="space-y-3">
                                    {careAlerts.map((item) => (
                                        <li key={item.label} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
                                            <span className="text-sm text-slate-600">{item.label}</span>
                                            <span className="text-lg font-semibold text-slate-900">{item.value}</span>
                                        </li>
                                    ))}
                                </ul>
                            </article>
                        </section>

                        <section className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                            <article className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="mb-4 text-lg font-semibold text-slate-900">Recently Missed Shifts</h2>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[540px] border-collapse text-left text-sm">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="border border-slate-200 px-3 py-2">Patient</th>
                                                <th className="border border-slate-200 px-3 py-2">Staff</th>
                                                <th className="border border-slate-200 px-3 py-2">Window</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {recentMissedShifts.length === 0 ? (
                                                <tr>
                                                    <td colSpan={3} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                        No missed shifts found.
                                                    </td>
                                                </tr>
                                            ) : (
                                                recentMissedShifts.map((shift) => (
                                                    <tr key={shift.id} className="odd:bg-white even:bg-slate-50/30">
                                                        <td className="border border-slate-200 px-3 py-2">{shift.patient}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{shift.staff}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{shift.window}</td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </article>

                            <article className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="mb-4 text-lg font-semibold text-slate-900">Recent Activity</h2>
                                <ul className="space-y-3">
                                    {recentActivity.length === 0 ? (
                                        <li className="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                                            No activity records found.
                                        </li>
                                    ) : (
                                        recentActivity.map((item, index) => (
                                            <li key={item.id ?? `${item.createdAt ?? 'row'}-${index}`} className="rounded-xl border border-slate-200 px-3 py-2">
                                                <p className="text-xs font-medium text-slate-500">{formatDateTime(item.createdAt)}</p>
                                                <p className="mt-1 text-sm font-semibold text-slate-800">{item.user || 'System user'}</p>
                                                <p className="text-sm text-slate-600">{item.description}</p>
                                                <p className="mt-1 text-xs font-mono text-slate-400">{item.path || '-'}</p>
                                            </li>
                                        ))
                                    )}
                                </ul>
                            </article>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
