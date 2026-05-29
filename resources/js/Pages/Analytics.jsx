import { Head, Link } from '@inertiajs/react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

const VISIT_STATUS_SERIES = [
    { key: 'completed', label: 'Completed', bar: 'bg-emerald-500', text: 'text-emerald-700' },
    { key: 'in_progress', label: 'In progress', bar: 'bg-sky-500', text: 'text-sky-700' },
    { key: 'upcoming', label: 'Upcoming', bar: 'bg-violet-500', text: 'text-violet-700' },
    { key: 'overdue', label: 'Overdue', bar: 'bg-amber-500', text: 'text-amber-700' },
    { key: 'missed', label: 'Missed', bar: 'bg-rose-500', text: 'text-rose-700' },
];

function MetricCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <article className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`mt-2 text-2xl font-semibold ${accent}`}>{value}</p>
        </article>
    );
}

function VisitStatusLegend() {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600">
            {VISIT_STATUS_SERIES.map((status) => (
                <li key={status.key} className="flex items-center gap-2">
                    <span className={`h-2.5 w-2.5 rounded-sm ${status.bar}`} aria-hidden />
                    <span>{status.label}</span>
                </li>
            ))}
        </ul>
    );
}

function VisitStatusWeekBar({ visitStatusTotals = {} }) {
    const total = VISIT_STATUS_SERIES.reduce(
        (sum, status) => sum + (visitStatusTotals[status.key] ?? 0),
        0,
    );

    if (total === 0) {
        return (
            <p className="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">
                No scheduled visits this week.
            </p>
        );
    }

    return (
        <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                This week by status
            </p>
            <div className="flex h-3 overflow-hidden rounded-full bg-slate-100">
                {VISIT_STATUS_SERIES.map((status) => {
                    const count = visitStatusTotals[status.key] ?? 0;
                    if (!count) return null;

                    return (
                        <div
                            key={status.key}
                            className={status.bar}
                            style={{ width: `${(count / total) * 100}%` }}
                            title={`${status.label}: ${count}`}
                        />
                    );
                })}
            </div>
            <ul className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                {VISIT_STATUS_SERIES.map((status) => (
                    <li key={status.key} className="rounded-lg bg-slate-50 px-2 py-1.5 text-center">
                        <p className={`text-lg font-semibold ${status.text}`}>
                            {visitStatusTotals[status.key] ?? 0}
                        </p>
                        <p className="text-[11px] text-slate-500">{status.label}</p>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function VisitStatusTrendChart({ dailyVisitTrend = [] }) {
    const maxDayTotal = Math.max(...dailyVisitTrend.map((day) => day.total ?? 0), 1);
    const chartHeight = 180;

    if (dailyVisitTrend.length === 0) {
        return (
            <p className="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                No visit trend data available.
            </p>
        );
    }

    return (
        <div>
            <div
                className="flex items-end justify-between gap-2 sm:gap-3"
                style={{ minHeight: chartHeight }}
                role="img"
                aria-label="Seven day visit status trend chart"
            >
                {dailyVisitTrend.map((day) => {
                    const dayTotal = day.total ?? 0;
                    const columnHeight =
                        dayTotal > 0 ? Math.max(8, Math.round((dayTotal / maxDayTotal) * chartHeight)) : 0;

                    return (
                        <div
                            key={day.label}
                            className="flex min-w-0 flex-1 flex-col items-center gap-2"
                        >
                            <p className="text-xs font-semibold text-slate-700">{dayTotal}</p>
                            <div
                                className="flex w-full max-w-[52px] flex-col justify-end rounded-t-md bg-slate-100"
                                style={{ height: chartHeight }}
                            >
                                {columnHeight > 0 ? (
                                    <div
                                        className="flex w-full flex-col-reverse overflow-hidden rounded-t-md"
                                        style={{ height: columnHeight }}
                                    >
                                        {VISIT_STATUS_SERIES.map((status) => {
                                            const count = day[status.key] ?? 0;
                                            if (!count) return null;

                                            return (
                                                <div
                                                    key={status.key}
                                                    className={`w-full ${status.bar}`}
                                                    style={{
                                                        height: `${(count / dayTotal) * 100}%`,
                                                    }}
                                                    title={`${status.label}: ${count}`}
                                                />
                                            );
                                        })}
                                    </div>
                                ) : null}
                            </div>
                            <p className="truncate text-center text-[11px] font-medium text-slate-500">
                                {day.shortLabel ?? day.label}
                            </p>
                        </div>
                    );
                })}
            </div>
            <div className="mt-4 border-t border-slate-100 pt-4">
                <VisitStatusLegend />
            </div>
        </div>
    );
}

export default function Analytics({
    summary = {},
    careAlerts = [],
    dailyVisitTrend = [],
    visitStatusTotals = {},
    recentMissedShifts = [],
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
                                Live view of visits, status trends, missed shifts, and open care alerts.
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
                                <h2 className="mb-1 text-lg font-semibold text-slate-900">Visit status trend</h2>
                                <p className="mb-5 text-sm text-slate-500">
                                    Last 7 days — stacked bars show completed, in progress, upcoming, overdue, and missed visits.
                                </p>
                                <div className="mb-6">
                                    <VisitStatusWeekBar visitStatusTotals={visitStatusTotals} />
                                </div>
                                <VisitStatusTrendChart dailyVisitTrend={dailyVisitTrend} />
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

                        <section>
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
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
