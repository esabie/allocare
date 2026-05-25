import { Head, Link, usePage } from '@inertiajs/react';
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

const carePlanCards = [
    { slug: 'personal-care-and-dignity', title: 'Personal Care & Dignity', status: 'Active', risks: ['None'], date: '20 Oct 2024', author: 'Nurse Sarah-Jane' },
    { slug: 'mobility-and-moving', title: 'Mobility & Moving / Handling', status: 'Active', risks: ['Fall Risk'], date: '14 Oct 2024', author: 'Nurse Sarah-Jane' },
    { slug: 'nutrition-and-hydration', title: 'Nutrition, Hydration & Dysphagia', status: 'Active', risks: ['Choking Risk', 'Weight Loss'], date: '02 Nov 2024', author: 'Dr. Julian Vance' },
    { slug: 'medication-support', title: 'Medication & Treatment ', status: 'Active', risks: ['None'], date: '30 Oct 2024', author: 'Pharmacist Kim' },
    { slug: 'pressure-area-care', title: 'Pressure Area Care & Tissue Viability ', status: 'Active', risks: ['None'], date: '14 Oct 2024', author: 'Nurse Sarah-Jane' },
    { slug: 'seizure-management', title: 'Seizure Management', status: 'Draft', risks: ['None'], date: '14 Oct 2024', author: 'Care Asst. Eugene' },
    { slug: 'respiratory-care', title: 'Respiratory Care', status: 'Under Review', risks: ['Breathlessness'], date: '05 Nov 2024', author: 'Care Asst. Tom B' },
    { slug: 'enteral-feeding', title: 'Enteral Feeding', status: 'Draft', risks: ['None'], date: '14 Oct 2024', author: 'Dr. Albert Kwachie' },
    { slug: 'diabetes-management', title: 'Diabetes Management', status: 'Draft', risks: ['N/A'], date: '08 Nov 2024', author: 'Nurse Sarah-Jane' },
    { slug: 'behaviour-support', title: 'Behaviour Support & Distressed Behaviour', status: 'Draft', risks: ['N/A'], date: '08 Nov 2024', author: 'Nurse Sarah-Jane' },
    { slug: 'continence-care', title: 'Catheter & Continence Care', status: 'Active', risks: ['Skin Breakdown'], date: '12 Sep 2024', author: 'Dr. Julian Vance' },
    { slug: 'wound-care', title: 'Wound Care', status: 'Active', risks: ['None'], date: '15 Oct 2024', author: 'Nurse Abena-Jane' },
    { slug: 'sleeping-and-resting', title: 'Sleeping & Night Support', status: 'Active', risks: ['None'], date: '15 Oct 2024', author: 'Nurse Sarah-Jane' },
    { slug: 'community-access', title: 'Community Access & Transport', status: 'Active', risks: ['None'], date: '10 Nov 2024', author: 'Speech Team' },
    { slug: 'mental-health-and-emotional-wellbeing', title: 'Mental Health & Emotional Wellbeing', status: 'Draft', risks: ['None'], date: '11 Nov 2024', author: 'Nurse Sarah-Jane' },
    { slug: 'communication-and-sensory', title: 'Communication & Sensory', status: 'Active', risks: ['None'], date: '14 Oct 2024', author: 'Care Asst. Tom B' },
    { slug: 'infection-prevention-and-monitoring', title: 'Infection Prevention & Monitoring', status: 'Draft', risks: ['None'], date: '12 Nov 2024', author: 'Dr. Julian Vance' },
    { slug: 'bowel-and-stoma-care', title: 'Bowel & Stoma Care', status: 'Under Review', risks: ['None'], date: '09 Nov 2024', author: 'Dr. Julian Vance' },
    { slug: 'pain-management', title: 'Pain Management', status: 'Active', risks: ['None'], date: '14 Oct 2024', author: 'Care Asst. Tom B' },
    { slug: 'end-of-life-support', title: 'End of Life / Advance Care Planning', status: 'Draft', risks: ['None'], date: '12 Nov 2024', author: 'Dr. Julian Vance' },
];

const statusClass = {
    Active: 'bg-emerald-100 text-emerald-700',
    Draft: 'bg-slate-200 text-slate-600',
    'Under Review': 'bg-sky-100 text-sky-700',
};

function toCardStatus(snapshotStatus, fallbackStatus) {
    const normalized = String(snapshotStatus || '').trim().toLowerCase();
    if (normalized === 'draft') return 'Draft';
    if (normalized === 'reviewed') return 'Under Review';
    if (normalized === 'submitted') return 'Active';

    return fallbackStatus;
}

function formatLastAction(value) {
    if (!value) return 'Not yet updated';

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return 'Not yet updated';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(parsed);
}

export default function PatientCarePlans({ patientSlug = 'sarah-jenkins', carePlanSnapshots = {} }) {
    const successMessage = usePage().props?.flash?.success;
    const cards = carePlanCards.map((card) => {
        const snapshot = carePlanSnapshots?.[card.slug];
        return {
            ...card,
            status: toCardStatus(snapshot?.status, card.status),
            date: formatLastAction(snapshot?.lastUpdatedAt),
            author: snapshot?.author || 'Not yet updated',
        };
    });
    return (
        <>
            <Head title="Patient Care Plans" />

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
                                    <Link
                                        key={tab.key}
                                        href={route('patients.show', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'care_plans' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.careplans', patientSlug)}
                                        className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'risk_assessment' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.risks', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'medication' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.mar', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'observations' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.observations', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'documents' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.documents', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'logs' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.logs', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.contacts', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </button>
                                ),
                            )}
                        </nav>

                        <button type="button" className="mt-auto rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600">
                            Logout
                        </button>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">
                                Dashboard
                            </Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">
                                Patients
                            </Link>
                            <span>/</span>
                            <span className="text-slate-900">Care Plans</span>
                        </div>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {cards.map((card) => (
                                <Link
                                    key={card.slug}
                                    href={route('patients.careplans.show', { patient: patientSlug, plan: card.slug })}
                                    className="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md"
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <h2 className="text-xl font-semibold text-slate-900">{card.title}</h2>
                                        <span className={`rounded-full px-2 py-1 text-[10px] font-semibold uppercase ${statusClass[card.status] || statusClass.Draft}`}>
                                            {card.status}
                                        </span>
                                    </div>

                                    <div className="mb-6 flex flex-wrap gap-2">
                                        {card.risks.map((risk) => (
                                            <span key={risk} className="rounded bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                                                {risk}
                                            </span>
                                        ))}
                                    </div>

                                    <div className="grid grid-cols-2 gap-3 text-xs text-slate-500">
                                        <div>
                                            <p className="uppercase tracking-wide">Last Action</p>
                                            <p className="font-medium text-slate-700">{card.date}</p>
                                        </div>
                                        <div>
                                            <p className="uppercase tracking-wide">Author</p>
                                            <p className="font-medium text-slate-700">{card.author}</p>
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
