import { Head, Link, usePage } from '@inertiajs/react';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const levelClass = {
    red: 'bg-rose-100 text-rose-700',
    amber: 'bg-amber-100 text-amber-700',
    green: 'bg-emerald-100 text-emerald-700',
};

const statusClass = {
    active: 'border-emerald-200 bg-emerald-50',
    draft: 'border-slate-200 bg-white',
    archived: 'border-slate-200 bg-slate-50 opacity-80',
};

export default function PatientRiskAssessments({
    patientSlug,
    patient = null,
    assessments = [],
    canExportFullPack = false,
}) {
    const successMessage = usePage().props?.flash?.success;
    const patientName = patient?.name || 'Patient';

    return (
        <>
            <Head title={`Risk assessments — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="risk_assessment" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <Link href={route('patients.show', patientSlug)} className="hover:text-slate-700">{patientName}</Link>
                            <span>/</span>
                            <span className="text-slate-900">Risk assessments</span>
                        </div>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        <section className="mb-4 rounded-2xl bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h1 className="text-2xl font-semibold text-slate-900">Risk assessments</h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Per-patient risk profiles with controls, review dates, linked care plans, and escalation levels.
                                    </p>
                                </div>
                                {canExportFullPack && (
                                    <a
                                        href={route('patients.risks.export.pdf', patientSlug)}
                                        className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                    >
                                        Export full pack (CQC)
                                    </a>
                                )}
                            </div>
                        </section>

                        <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {assessments.map((risk) => (
                                <article
                                    key={risk.slug}
                                    className={`rounded-2xl border p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md ${statusClass[risk.status] || statusClass.draft}`}
                                >
                                    <Link
                                        href={route('patients.risks.show', { patient: patientSlug, risk: risk.slug })}
                                        className="block"
                                    >
                                        <div className="mb-3 flex items-center justify-between gap-2">
                                            <h2 className="text-lg font-semibold text-slate-900">{risk.title}</h2>
                                            {risk.riskLevelLabel ? (
                                                <span className={`rounded-full px-2 py-1 text-[10px] font-semibold uppercase ${levelClass[risk.riskLevel] || levelClass.amber}`}>
                                                    {risk.riskLevelLabel}
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-semibold uppercase text-slate-600">
                                                    Not assessed
                                                </span>
                                            )}
                                        </div>

                                        {(risk.riskStatement || risk.activeControls) && (
                                            <p className="mb-3 line-clamp-2 text-sm text-slate-600">{risk.riskStatement || risk.activeControls}</p>
                                        )}

                                        {!risk.riskStatement && !risk.activeControls && risk.suggestedControls?.length > 0 && (
                                            <div className="mb-4 flex flex-wrap gap-2">
                                                {risk.suggestedControls.slice(0, 3).map((control) => (
                                                    <span key={control} className="rounded bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-500">
                                                        {control}
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        <div className="grid grid-cols-2 gap-3 text-xs text-slate-500">
                                            <div>
                                                <p className="uppercase tracking-wide">Last review</p>
                                                <p className="font-medium text-slate-700">{risk.lastReviewedAtLabel || '—'}</p>
                                            </div>
                                            <div>
                                                <p className="uppercase tracking-wide">Owner</p>
                                                <p className="font-medium text-slate-700">{risk.ownerName || risk.authorName || '—'}</p>
                                            </div>
                                        </div>

                                        {risk.reviewOverdue && (
                                            <p className="mt-3 text-xs font-bold uppercase text-rose-700">Review overdue</p>
                                        )}
                                    </Link>

                                    {risk.hasRecord && (
                                        <div className="mt-4 border-t border-slate-100 pt-3">
                                            <a
                                                href={route('patients.risks.pdf', { patient: patientSlug, risk: risk.slug })}
                                                className="inline-flex text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                                            >
                                                Export PDF
                                            </a>
                                        </div>
                                    )}
                                </article>
                            ))}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
