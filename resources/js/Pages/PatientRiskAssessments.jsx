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
    // { label: 'Alerts', key: 'alerts' },
];

const riskCards = [
    { slug: 'falls-risk', title: 'Falls Risk', level: 'High', controls: ['2:1 support', 'Transfer belt'], date: '20 Oct 2024', owner: 'Nurse Sarah-Jane' },
    { slug: 'skin-integrity', title: 'Skin Integrity Risk', level: 'Moderate', controls: ['Repositioning', 'Pressure mattress'], date: '14 Oct 2024', owner: 'Nurse Sarah-Jane' },
    { slug: 'aspiration-risk', title: 'Aspiration / Choking Risk', level: 'High', controls: ['IDDSI Level 4', 'Supervised feeding'], date: '02 Nov 2024', owner: 'Dr. Julian Vance' },
    { slug: 'medication-risk', title: 'Medication Administration Risk', level: 'Moderate', controls: ['Double-check eMAR', 'Time-critical flag'], date: '08 Nov 2024', owner: 'Pharmacist Kim' },
    { slug: 'elopement-risk', title: 'Absconding / Missing Person Risk', level: 'Low', controls: ['Door sensor', 'Escort plan'], date: '05 Nov 2024', owner: 'Care Asst. Tom B' },
    { slug: 'infection-risk', title: 'Infection Prevention Risk', level: 'Moderate', controls: ['PPE protocol', 'Daily observations'], date: '12 Nov 2024', owner: 'Dr. Julian Vance' },
];

const levelClass = {
    High: 'bg-rose-100 text-rose-700',
    Moderate: 'bg-amber-100 text-amber-700',
    Low: 'bg-emerald-100 text-emerald-700',
};

export default function PatientRiskAssessments({ patientSlug = 'cr-88210' }) {
    return (
        <>
            <Head title="Patient Risk Assessment" />

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
                                    <Link key={tab.key} href={route('patients.show', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'care_plans' ? (
                                    <Link key={tab.key} href={route('patients.careplans', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'risk_assessment' ? (
                                    <Link key={tab.key} href={route('patients.risks', patientSlug)} className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'medication' ? (
                                    <Link key={tab.key} href={route('patients.mar', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'observations' ? (
                                    <Link key={tab.key} href={route('patients.observations', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'documents' ? (
                                    <Link key={tab.key} href={route('patients.documents', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'logs' ? (
                                    <Link key={tab.key} href={route('patients.logs', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link key={tab.key} href={route('patients.contacts', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : (
                                    <button key={tab.key} type="button" className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </button>
                                ),
                            )}
                        </nav>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <span className="text-slate-900">Risk Assessment</span>
                        </div>

                        <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {riskCards.map((risk) => (
                                <Link
                                    key={risk.slug}
                                    href={route('patients.risks.show', { patient: patientSlug, risk: risk.slug })}
                                    className="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md"
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <h2 className="text-xl font-semibold text-slate-900">{risk.title}</h2>
                                        <span className={`rounded-full px-2 py-1 text-[10px] font-semibold uppercase ${levelClass[risk.level] || levelClass.Moderate}`}>
                                            {risk.level}
                                        </span>
                                    </div>
                                    <div className="mb-6 flex flex-wrap gap-2">
                                        {risk.controls.map((control) => (
                                            <span key={control} className="rounded bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                {control}
                                            </span>
                                        ))}
                                    </div>
                                    <div className="grid grid-cols-2 gap-3 text-xs text-slate-500">
                                        <div>
                                            <p className="uppercase tracking-wide">Last Review</p>
                                            <p className="font-medium text-slate-700">{risk.date}</p>
                                        </div>
                                        <div>
                                            <p className="uppercase tracking-wide">Owner</p>
                                            <p className="font-medium text-slate-700">{risk.owner}</p>
                                        </div>
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
