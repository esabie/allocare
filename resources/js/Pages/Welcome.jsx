import { Head, Link, usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';

const statCards = [
    {
        title: 'Visits',
        total: '124',
        label: 'WEEKLY',
        ringColor: 'border-emerald-500',
        metrics: [
            { name: 'Complete', value: 80, color: 'bg-emerald-500' },
            { name: 'In-Progress', value: 15, color: 'bg-sky-500' },
            { name: 'Upcoming', value: 10, color: 'bg-amber-400' },
            { name: 'Missed', value: 10, color: 'bg-red-400' },
        ],
    },
    {
        title: 'Tasks',
        total: '340',
        label: 'WEEKLY',
        ringColor: 'border-blue-500',
        metrics: [
            { name: 'Complete', value: 290, color: 'bg-emerald-500' },
            { name: 'Partial', value: 30, color: 'bg-amber-400' },
            { name: 'Missed', value: 20, color: 'bg-red-400' },
        ],
    },
];

const operations = [
    ['Assessments in progress', '12'],
    ['Supervisions', '04'],
    ['Bookings', '86'],
];

const careAlerts = [
    {
        label: 'MISSED MEDICATION',
        patient: 'John Powell',
        details: 'Apixaban 2.5mg',
        action: 'Resolve',
        accent: 'border-red-400',
        panel: 'bg-red-50',
    },
    {
        label: 'INCOMPLETE TASK',
        patient: 'Margaret Hughes',
        details: 'Morning Hygiene Care',
        action: 'Mark Complete',
        accent: 'border-emerald-400',
        panel: 'bg-emerald-50',
    },
    {
        label: 'OBSERVATION REQUIRED',
        patient: 'Wendy Thomas',
        details: 'Escalated mobility review',
        action: 'Assign',
        accent: 'border-amber-400',
        panel: 'bg-amber-50',
    },
];

const analysisRows = [
    { label: 'Medications', resolved: 78, missed: 14, flagged: 8 },
    { label: 'Personal Care', resolved: 83, missed: 7, flagged: 10 },
    { label: 'Observations', resolved: 67, missed: 5, flagged: 28 },
];

function toStrokeColor(metricColorClass) {
    const map = {
        'bg-emerald-500': '#22c55e',
        'bg-sky-500': '#0ea5e9',
        'bg-amber-400': '#fbbf24',
        'bg-red-400': '#f87171',
    };

    return map[metricColorClass] || '#94a3b8';
}

function Donut({ total, metrics }) {
    const normalizedMetrics = Array.isArray(metrics) ? metrics : [];
    const numericTotal = normalizedMetrics.reduce((sum, metric) => sum + Number(metric.value || 0), 0);
    const safeTotal = numericTotal > 0 ? numericTotal : 1;
    const radius = 44;
    const strokeWidth = 18;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    const segments = normalizedMetrics
        .filter((metric) => Number(metric.value || 0) > 0)
        .map((metric) => {
            const value = Number(metric.value || 0);
            const segmentLength = (value / safeTotal) * circumference;
            const segment = {
                ...metric,
                value,
                length: segmentLength,
                offset,
                color: toStrokeColor(metric.color),
            };
            offset += segmentLength;
            return segment;
        });

    const displayTotal = Number(total || 0);

    return (
        <div className="relative h-32 w-32">
            <svg viewBox="0 0 120 120" className="h-32 w-32 -rotate-90">
                <circle cx="60" cy="60" r={radius} fill="none" stroke="#e2e8f0" strokeWidth={strokeWidth} />
                {segments.map((segment) => (
                    <circle
                        key={segment.name}
                        cx="60"
                        cy="60"
                        r={radius}
                        fill="none"
                        stroke={segment.color}
                        strokeWidth={strokeWidth}
                        strokeDasharray={`${segment.length} ${circumference - segment.length}`}
                        strokeDashoffset={-segment.offset}
                        strokeLinecap="butt"
                    />
                ))}
            </svg>
            <div className="absolute inset-0 flex items-center justify-center">
                <div className="text-center">
                    <p className="text-2xl font-semibold text-slate-800">{Number.isFinite(displayTotal) ? displayTotal : 0}</p>
                    <p className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Total</p>
                </div>
            </div>
            {segments.map((segment) => {
                const mid = segment.offset + segment.length / 2;
                const angle = (mid / circumference) * 2 * Math.PI - Math.PI / 2;
                const labelRadius = radius + 10;
                const x = 60 + Math.cos(angle) * labelRadius;
                const y = 60 + Math.sin(angle) * labelRadius;
                return (
                    <span
                        key={`${segment.name}-label`}
                        className="absolute -translate-x-1/2 -translate-y-1/2 text-[10px] font-semibold text-slate-600"
                        style={{ left: `${(x / 120) * 100}%`, top: `${(y / 120) * 100}%` }}
                    >
                        {segment.value}
                    </span>
                );
            })}
        </div>
    );
}

export default function Welcome() {
    const authUser = usePage().props?.auth?.user;
    const dashboardStats = usePage().props?.dashboardStats || {};
    const normalizedRole = String(authUser?.primary_role || '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, '_');
    const isAdminUser = ['admin', 'super_admin'].includes(normalizedRole);

    const sideMenu = [
        { label: 'Overview', href: route('dashboard') },
        { label: 'Journal' },
        { label: 'Care Alerts' },
        { label: 'Analytics' },
        { label: 'Employees', href: route('employees') },
        ...(isAdminUser ? [{ label: 'Activity Logs', href: route('admin.activity-logs') }] : []),
    ];

    const cards = [
        {
            ...statCards[0],
            total: String(dashboardStats?.visits?.total ?? statCards[0].total),
            metrics: [
                { ...statCards[0].metrics[0], value: dashboardStats?.visits?.metrics?.complete ?? statCards[0].metrics[0].value },
                { ...statCards[0].metrics[1], value: dashboardStats?.visits?.metrics?.inProgress ?? statCards[0].metrics[1].value },
                { ...statCards[0].metrics[2], value: dashboardStats?.visits?.metrics?.partial ?? statCards[0].metrics[2].value },
                { ...statCards[0].metrics[3], value: dashboardStats?.visits?.metrics?.missed ?? statCards[0].metrics[3].value },
            ],
        },
        {
            ...statCards[1],
            total: String(dashboardStats?.tasks?.total ?? statCards[1].total),
            metrics: [
                { ...statCards[1].metrics[0], value: dashboardStats?.tasks?.metrics?.complete ?? statCards[1].metrics[0].value },
                { ...statCards[1].metrics[1], value: dashboardStats?.tasks?.metrics?.partial ?? statCards[1].metrics[1].value },
                { ...statCards[1].metrics[2], value: dashboardStats?.tasks?.metrics?.missed ?? statCards[1].metrics[2].value },
            ],
        },
    ];

    const operationRows = [
        ['Assessments in progress', String(dashboardStats?.operations?.assessmentsInProgress ?? operations[0][1])],
        ['Supervisions', String(dashboardStats?.operations?.supervisions ?? operations[1][1])],
        ['Bookings', String(dashboardStats?.operations?.bookings ?? operations[2][1])],
    ];
    const dashboardAlerts = Array.isArray(dashboardStats?.careAlerts) && dashboardStats.careAlerts.length > 0
        ? dashboardStats.careAlerts
        : careAlerts;

    return (
        <>
            <Head title="AlloCare Dashboard" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-8 lg:flex lg:flex-col">
                        <div className="mb-10">
                            <div className="mb-3">
                                <Link href={route('dashboard')}>
                                    <ApplicationLogo className="block w-full" />
                                </Link>
                            </div>
                            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Clinical Precision</p>
                        </div>

                        <nav className="space-y-2">
                            {sideMenu.map((item, idx) =>
                                item.href ? (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={`block w-full rounded-xl px-4 py-3 text-left text-sm font-medium ${
                                            idx === 0 ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                ) : (
                                    <button
                                        key={item.label}
                                        type="button"
                                        className={`w-full rounded-xl px-4 py-3 text-left text-sm font-medium ${
                                            idx === 0 ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'
                                        }`}
                                    >
                                        {item.label}
                                    </button>
                                ),
                            )}
                        </nav>

                        <div className="mt-auto space-y-2">
                            <button type="button" className="w-full rounded-xl bg-white px-4 py-3 text-left text-sm font-medium text-slate-600">
                                Insights
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm text-slate-500">
                                Help
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm text-slate-500">
                                Sign out
                            </button>
                        </div>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('patients')} className="hover:text-slate-900">
                                    Patients
                                </Link>
                                <Link href={route('schedules')} className="hover:text-slate-900">
                                    Schedules
                                </Link>
                                <span>Reports</span>
                                <span>Inventory</span>
                            </div>

                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                            {cards.map((card) => (
                                <article key={card.title} className="rounded-2xl bg-white p-5">
                                    <div className="mb-5 flex items-center justify-between">
                                        <h2 className="text-lg font-semibold text-slate-800">{card.title}</h2>
                                        <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500">{card.label}</span>
                                    </div>
                                    <div className="flex items-center gap-5">
                                        <Donut total={card.total} metrics={card.metrics} />
                                        <ul className="space-y-2 text-sm">
                                            {card.metrics.map((metric) => (
                                                <li key={metric.name} className="flex items-center gap-2">
                                                    <span className={`h-2.5 w-2.5 rounded-full ${metric.color}`} />
                                                    <span className="min-w-24 text-slate-500">{metric.name}</span>
                                                    <span className="font-semibold text-slate-700">{metric.value}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </article>
                            ))}

                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-5 text-lg font-semibold text-slate-800">Operations</h2>
                                <ul className="space-y-4">
                                    {operationRows.map(([label, count]) => (
                                        <li key={label} className="flex items-center justify-between text-sm">
                                            <span className="text-slate-600">{label}</span>
                                            <span className="text-lg font-semibold text-slate-800">{count}</span>
                                        </li>
                                    ))}
                                </ul>
                            </article>
                        </section>

                        <section className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-white p-5 xl:col-span-2">
                                <div className="mb-5 flex items-center justify-between">
                                    <h2 className="text-2xl font-semibold text-slate-800">Clinical Journal</h2>
                                    <button type="button" className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">
                                        + New Entry
                                    </button>
                                </div>
                                <div className="mb-8 flex items-center gap-6 text-sm">
                                    <span className="border-b-2 border-emerald-500 pb-2 font-semibold text-emerald-600">All</span>
                                    <span className="text-slate-500">Created by me</span>
                                    <span className="text-slate-500">Assigned to me</span>
                                </div>
                                <div className="mx-auto max-w-md rounded-2xl bg-slate-50 p-10 text-center">
                                    <div className="mx-auto mb-4 h-28 w-28 rounded-2xl bg-slate-200" />
                                    <h3 className="mb-2 text-2xl font-semibold text-slate-700">Clean slate, doctor.</h3>
                                    <p className="text-sm text-slate-500">No recent journal entries found. Start a new clinical observation to track patient progress.</p>
                                </div>
                            </article>

                            <article className="rounded-2xl bg-white p-5">
                                <div className="mb-5 flex items-center justify-between">
                                    <h2 className="text-2xl font-semibold text-slate-800">Care Alerts</h2>
                                    <span className="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500">Care</span>
                                </div>
                                <div className="space-y-3">
                                    {dashboardAlerts.map((alert) => (
                                        <div key={alert.patient} className={`rounded-xl border-l-4 p-4 ${alert.accent} ${alert.panel}`}>
                                            <p className="mb-2 text-[11px] font-semibold tracking-wide text-slate-500">{alert.label}</p>
                                            <p className="font-semibold text-slate-800">{alert.patient}</p>
                                            <p className="mb-3 text-sm text-slate-600">{alert.details}</p>
                                            <button type="button" className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">
                                                {alert.action}
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </article>
                        </section>

                        <section className="mt-4 rounded-2xl bg-white p-5">
                            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                                <h2 className="text-3xl font-semibold text-slate-800">Alerts Analysis</h2>
                                <button type="button" className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                                    Start recording
                                </button>
                            </div>

                            <div className="mb-4 flex flex-wrap gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-red-500" />Missed</span>
                                <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />Resolved</span>
                                <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-amber-400" />Flagged</span>
                            </div>

                            <div className="space-y-4">
                                {analysisRows.map((row) => (
                                    <div key={row.label}>
                                        <p className="mb-2 text-sm font-medium text-slate-600">{row.label}</p>
                                        <div className="flex h-7 overflow-hidden rounded-full bg-slate-100">
                                            <div className="h-full bg-red-500" style={{ width: `${row.missed}%` }} />
                                            <div className="h-full bg-emerald-500" style={{ width: `${row.resolved}%` }} />
                                            <div className="h-full bg-amber-400" style={{ width: `${row.flagged}%` }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
