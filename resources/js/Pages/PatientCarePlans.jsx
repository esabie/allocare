import { Head, Link, usePage } from '@inertiajs/react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

const statusClass = {
    Active: 'bg-emerald-100 text-emerald-700',
    Draft: 'bg-slate-200 text-slate-600',
    'Under Review': 'bg-sky-100 text-sky-700',
    'Not started': 'bg-slate-100 text-slate-500',
};

export default function PatientCarePlans({ patientSlug = 'sarah-jenkins', carePlans = [] }) {
    const successMessage = usePage().props?.flash?.success;
    const cards = Array.isArray(carePlans) ? carePlans : [];

    return (
        <>
            <Head title="Patient Care Plans" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="care_plans" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
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
                                        <span className={`rounded-full px-2 py-1 text-[10px] font-semibold uppercase ${statusClass[card.status] || statusClass['Not started']}`}>
                                            {card.status}
                                        </span>
                                    </div>

                                    <div className="mb-6 flex flex-wrap gap-2">
                                        {(card.risks || []).map((risk) => (
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
