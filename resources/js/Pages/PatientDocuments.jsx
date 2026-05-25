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

const documents = [
    { slug: 'about-me-person-centred-care-plan', title: 'About Me', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'communication-passport', title: 'Communication Passport', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'hospital-passport', title: 'Hospital Passport', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'advance-statement', title: 'Advance Statement', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'initial-assessment', title: 'Initial Assessment', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'baseline-summary', title: 'Baseline Summary', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'activity-log-daily-record', title: 'Activity Log', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'tissue-viability-checklist', title: 'Tissue Viability Checklist', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
];

const quickLinks = [
    { slug: 'initial-assessment', title: 'Initial Assessment', files: '1 File' },
    { slug: 'baseline-summary', title: 'Baseline Summary', files: '1 File' },
    { slug: 'activity-log-daily-record', title: 'Activity Log', files: '1 File' },
    { slug: 'tissue-viability-checklist', title: 'Tissue Viability Checklist', files: '1 File' },
    { slug: 'about-me-person-centred-care-plan', title: 'About Me', files: '1 File' },
    { slug: 'communication-passport', title: 'Communication Passport', files: '1 File' },
    { slug: 'hospital-passport', title: 'Hospital Passport', files: '1 File' },
    { slug: 'advance-statement', title: 'Advance Statement', files: '1 File' },
];

export default function PatientDocuments({ patientSlug = 'cr-88210' }) {
    const aboutMe = documents.find((doc) => doc.slug === 'about-me-person-centred-care-plan');
    const communicationPassport = documents.find((doc) => doc.slug === 'communication-passport');
    const hospitalPassport = documents.find((doc) => doc.slug === 'hospital-passport');
    const advanceStatement = documents.find((doc) => doc.slug === 'advance-statement');

    return (
        <>
            <Head title="Patient Documents" />
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
                                    <Link key={tab.key} href={route('patients.mar', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'observations' ? (
                                    <Link key={tab.key} href={route('patients.observations', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'documents' ? (
                                    <Link key={tab.key} href={route('patients.documents', patientSlug)} className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700">{tab.label}</Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link key={tab.key} href={route('patients.contacts', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'logs' ? (
                                    <Link key={tab.key} href={route('patients.logs', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : (
                                    <button key={tab.key} type="button" className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</button>
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
                            <span className="text-slate-900">Documents</span>
                        </div>

                        <section className="space-y-4">
                            <article className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-3xl font-bold text-slate-900">Sarah Jenkins</h2>
                                        <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                                            <span>DOB <strong>12 May 1948</strong></span>
                                            <span>NHS ID <strong>482-992-102</strong></span>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Low Risk (RAG)</span>
                                        <span className="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Severe Allergy: Penicillin</span>
                                        {/* <button type="button" className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Upload Document</button> */}
                                    </div>
                                </div>
                            </article>

                            <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="mb-4">
                                    <h2 className="text-3xl font-bold text-slate-900">Document Library</h2>
                                    <p className="text-sm text-slate-500">Central repository for clinical documentation and legal records.</p>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
                                    <div className="space-y-4">
                                        {aboutMe && (
                                            <Link href={route('patients.documents.show', { patient: patientSlug, document: aboutMe.slug })} className="block rounded-3xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-300">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <h3 className="text-xl font-semibold text-slate-900">About Me (Source of Truth)</h3>
                                                        <p className="text-xs text-slate-500">Core personal and historical background.</p>
                                                    </div>
                                                    <span className="rounded-full bg-white px-2 py-1 text-[10px] font-semibold uppercase text-slate-500">3 Files</span>
                                                </div>
                                                <div className="mt-4 space-y-2 text-xs text-slate-600">
                                                    <p>Personal History & Narrative</p>
                                                    <p>Key Contacts & Guardianship</p>
                                                </div>
                                            </Link>
                                        )}

                                        <div className="grid gap-4 md:grid-cols-2">
                                            {communicationPassport && (
                                                <Link href={route('patients.documents.show', { patient: patientSlug, document: communicationPassport.slug })} className="block rounded-3xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-300">
                                                    <h3 className="text-lg font-semibold text-slate-900">Communication Passport</h3>
                                                    <p className="mt-1 text-xs text-slate-500">Interaction and speech guidelines.</p>
                                                </Link>
                                            )}
                                            {hospitalPassport && (
                                                <Link href={route('patients.documents.show', { patient: patientSlug, document: hospitalPassport.slug })} className="block rounded-3xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-300">
                                                    <h3 className="text-lg font-semibold text-slate-900">Hospital Passport</h3>
                                                    <p className="mt-1 text-xs text-slate-500">Emergency acute care protocols.</p>
                                                </Link>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        {advanceStatement && (
                                            <Link href={route('patients.documents.show', { patient: patientSlug, document: advanceStatement.slug })} className="block rounded-3xl bg-slate-900 p-5 text-white transition hover:opacity-95">
                                                <p className="text-xl font-semibold">Advance Statement</p>
                                                <p className="mt-1 text-xs text-slate-300">Legal standing for future care decisions and life-sustaining preferences.</p>
                                                <p className="mt-4 inline-flex rounded-full bg-slate-800 px-3 py-1 text-xs">Latest Statement</p>
                                            </Link>
                                        )}

                                        <div className="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">All Forms</p>
                                            <ul className="space-y-2 text-sm text-slate-700">
                                                {quickLinks.map((item) => (
                                                    <li key={item.slug}>
                                                        <Link href={route('patients.documents.show', { patient: patientSlug, document: item.slug })} className="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-white">
                                                            <span>{item.title}</span>
                                                            <span className="text-xs text-slate-400">{item.files}</span>
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
