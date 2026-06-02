import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const sideTabs = [
    { label: 'Overview', key: 'overview' },
    { label: 'Care Plans', key: 'care_plans' },
    { label: 'Risk Assessment', key: 'risk_assessment' },
    { label: 'eMAR', key: 'medication' },
    { label: 'Observations', key: 'observations' },
    { label: 'Documents', key: 'documents' },
    { label: 'Notes', key: 'notes' },
    { label: 'Logs', key: 'logs' },
    { label: 'Contacts', key: 'contacts' },
];

function StatCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-xl font-bold ${accent}`}>{value}</p>
        </div>
    );
}

export default function PatientMAR({ patientSlug = 'cr-88210', stats = {} }) {
    const marCards = [
        {
            slug: 'today-mar',
            title: 'Today eMAR',
            status: 'Open',
            description: 'Morning / Midday / Evening medication round',
            detail: `${stats.givenToday || 0} given, ${stats.dueToday || 0} remaining`,
        },
        {
            slug: 'prn-log',
            title: 'PRN Administration Log',
            status: 'Open',
            description: 'As-needed medications',
            detail: `${stats.prnCount || 0} PRN medications available`,
        },
        {
            slug: 'refused-medications',
            title: 'Refused / Omitted Doses',
            status: (stats.refusedToday || 0) + (stats.omittedToday || 0) > 0 ? 'Review' : 'Open',
            description: 'Exception tracking and documentation',
            detail: `${(stats.refusedToday || 0) + (stats.omittedToday || 0)} exceptions today`,
        },
        {
            slug: 'controlled-drug-register',
            title: 'Controlled Drug Register',
            status: 'Open',
            description: 'Controlled drug administration with witness',
            detail: `${stats.controlledCount || 0} controlled medications`,
        },
        {
            slug: 'insulin-mar',
            title: 'Insulin eMAR',
            status: 'Open',
            description: 'Blood glucose-linked dosing',
            detail: 'BGL-linked doses',
        },
        {
            slug: 'weekly-audit',
            title: 'Weekly eMAR Audit',
            status: 'Open',
            description: 'Compliance checks and review',
            detail: 'Medication compliance audit',
        },
    ];

    const statusClass = {
        Open: 'bg-emerald-100 text-emerald-700',
        Review: 'bg-amber-100 text-amber-700',
        Completed: 'bg-slate-200 text-slate-600',
    };

    return (
        <>
            <Head title="Patient eMAR" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:flex lg:flex-col">
                        <div className="mb-5">
                            <Link href={route('dashboard')}><ApplicationLogo className="mb-3 block w-full" /></Link>
                            <div className="rounded-xl border border-slate-200 bg-white p-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Patient Record</p>
                            </div>
                        </div>
                        <nav className="space-y-1.5">
                            {sideTabs.map((tab) =>
                                tab.key === 'overview' ? (
                                    <Link key={tab.key} href={route('patients.show', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'care_plans' ? (
                                    <Link key={tab.key} href={route('patients.careplans', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'risk_assessment' ? (
                                    <Link key={tab.key} href={route('patients.risks', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'medication' ? (
                                    <Link key={tab.key} href={route('patients.mar', patientSlug)} className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700">{tab.label}</Link>
                                ) : tab.key === 'observations' ? (
                                    <Link key={tab.key} href={route('patients.observations', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'documents' ? (
                                    <Link key={tab.key} href={route('patients.documents', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'logs' ? (
                                    <Link key={tab.key} href={route('patients.logs', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link key={tab.key} href={route('patients.contacts', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : (
                                    <button key={tab.key} type="button" className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</button>
                                ),
                            )}
                        </nav>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-xs font-medium text-slate-500">
                                <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                                <span>/</span>
                                <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                                <span>/</span>
                                <Link href={route('patients.show', patientSlug)} className="hover:text-slate-700">Profile</Link>
                                <span>/</span>
                                <span className="text-slate-900">eMAR</span>
                            </div>
                            <a
                                href={route('patients.mar.monthly-chart.pdf', { patient: patientSlug })}
                                className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Download monthly MAR chart (PDF)
                            </a>
                        </div>

                        {/* Stats overview */}
                        <section className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                            <StatCard label="Active Meds" value={stats.activeMeds || 0} />
                            <StatCard label="Given Today" value={stats.givenToday || 0} accent="text-emerald-700" />
                            <StatCard label="Due" value={stats.dueToday || 0} accent="text-blue-700" />
                            <StatCard label="Refused" value={stats.refusedToday || 0} accent="text-red-600" />
                            <StatCard label="Delayed" value={stats.delayedToday || 0} accent="text-orange-600" />
                            {stats.overdueReminders > 0 && (
                                <StatCard label="Overdue" value={stats.overdueReminders} accent="text-red-700" />
                            )}
                        </section>

                        {/* MAR Cards */}
                        <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {marCards.map((card) => (
                                <Link
                                    key={card.slug}
                                    href={route('patients.mar.show', { patient: patientSlug, mar: card.slug })}
                                    className="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md"
                                >
                                    <div className="mb-3 flex items-start justify-between">
                                        <h2 className="text-base font-semibold text-slate-900">{card.title}</h2>
                                        <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase ${statusClass[card.status] || statusClass.Open}`}>{card.status}</span>
                                    </div>
                                    <p className="mb-2 text-xs text-slate-500">{card.description}</p>
                                    <p className="text-sm font-medium text-slate-700">{card.detail}</p>
                                </Link>
                            ))}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
