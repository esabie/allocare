import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
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
    // { label: 'Alerts', key: 'alerts' },
];

const marCards = [
    { slug: 'today-mar', title: 'Today eMAR', status: 'Open', period: 'Morning / Midday / Evening', due: '14 meds due', author: 'Nurse Sarah-Jane' },
    { slug: 'prn-log', title: 'PRN Administration Log', status: 'Open', period: 'As required', due: '3 PRN events', author: 'Care Asst. Tom B' },
    { slug: 'refused-medications', title: 'Refused / Omitted Doses', status: 'Review', period: 'Exception tracking', due: '2 pending reviews', author: 'Pharmacist Kim' },
    { slug: 'controlled-drug-register', title: 'Controlled Drug Register', status: 'Open', period: 'Shift handover', due: 'Count due 20:00', author: 'Nurse Sarah-Jane' },
    { slug: 'insulin-mar', title: 'Insulin eMAR', status: 'Open', period: 'BGL-linked doses', due: 'Next check 12:30', author: 'Dr. Julian Vance' },
    { slug: 'weekly-audit', title: 'Weekly eMAR Audit', status: 'Completed', period: 'Compliance checks', due: 'Last completed today', author: 'Care Manager' },
];

const statusClass = {
    Open: 'bg-emerald-100 text-emerald-700',
    Review: 'bg-amber-100 text-amber-700',
    Completed: 'bg-slate-200 text-slate-600',
};

export default function PatientMAR({ patientSlug = 'cr-88210' }) {
    return (
        <>
            <Head title="Patient eMAR" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:flex lg:flex-col">
                        <div className="mb-5">
                            <Link href={route('dashboard')}>
                                <ApplicationLogo className="mb-3 block w-full" />
                            </Link>
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
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('dashboard')} className="hover:text-slate-900">Dashboard</Link>
                                <Link href={route('patients')} className="text-slate-900">Patients</Link>
                                <span>Schedules</span>
                                <span>Reports</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <span className="text-slate-900">eMAR</span>
                        </div>

                        <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {marCards.map((card) => (
                                <Link
                                    key={card.slug}
                                    href={route('patients.mar.show', { patient: patientSlug, mar: card.slug })}
                                    className="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md"
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <h2 className="text-xl font-semibold text-slate-900">{card.title}</h2>
                                        <span className={`rounded-full px-2 py-1 text-[10px] font-semibold uppercase ${statusClass[card.status] || statusClass.Open}`}>{card.status}</span>
                                    </div>
                                    <div className="mb-6 space-y-1 text-sm text-slate-600">
                                        <p>{card.period}</p>
                                        <p className="font-medium text-slate-700">{card.due}</p>
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        <p className="uppercase tracking-wide">Owner</p>
                                        <p className="font-medium text-slate-700">{card.author}</p>
                                    </div>
                                </Link>
                            ))}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
