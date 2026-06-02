import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import ProfileMenu from '@/Components/ProfileMenu';

function Field({ label, value, wide = false }) {
    return (
        <div className={wide ? 'col-span-full' : ''}>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className="mt-1 whitespace-pre-wrap text-sm text-slate-700">{value || '-'}</p>
        </div>
    );
}

function SectionCard({ title, children, dark = false }) {
    return (
        <section className={`mb-4 rounded-2xl p-5 ${dark ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'}`}>
            <h2 className={`mb-4 text-lg font-semibold ${dark ? 'text-white' : 'text-slate-900'}`}>{title}</h2>
            {children}
        </section>
    );
}

export default function ReportsIncidentView({
    incident = {},
    investigationStatusOptions = [],
    riddorCategoryOptions = [],
    canManageInvestigation = false,
}) {
    const successMessage = usePage().props?.flash?.success;
    const inv = incident.investigation || {};

    const form = useForm({
        investigation_status: inv.status || 'pending',
        investigation_summary: inv.investigationSummary || '',
        root_cause: inv.rootCause || '',
        corrective_actions: inv.correctiveActions || '',
        due_at: inv.dueAt || '',
        riddor_reportable: inv.riddorReportable || false,
        riddor_category: inv.riddorCategory || '',
        riddor_reported_at: inv.riddorReportedAt ? inv.riddorReportedAt.slice(0, 10) : '',
        riddor_reference: inv.riddorReference || '',
        safeguarding_concern: inv.safeguardingConcern || false,
        safeguarding_referral_made: inv.safeguardingReferralMade || false,
        safeguarding_referral_at: inv.safeguardingReferralAt ? inv.safeguardingReferralAt.slice(0, 10) : '',
        safeguarding_authority: inv.safeguardingAuthority || '',
        safeguarding_reference: inv.safeguardingReference || '',
    });

    const submitInvestigation = (event) => {
        event.preventDefault();
        form.patch(route('reports.incidents.investigation.update', incident.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Incident ${incident.reference}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('reports.incidents')} className="hover:text-slate-700">Incidents</Link>
                            <span>/</span>
                            <span className="text-slate-900">{incident.reference}</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="text-xs font-mono font-semibold text-slate-500">{incident.reference}</p>
                                    <h1 className="text-2xl font-semibold text-slate-900">
                                        {incident.title && incident.title !== '-' ? incident.title : 'Incident Report'}
                                    </h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Patient: <span className="font-medium text-slate-700">{incident.patient_name}</span>
                                    </p>
                                </div>
                                <Link href={route('reports.incidents')} className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200">
                                    ← Back
                                </Link>
                            </div>
                        </section>

                        {canManageInvestigation && (
                            <SectionCard title="Investigation & regulatory reporting">
                                {(inv.riddorOverdue || inv.investigationOverdue || inv.safeguardingPending) && (
                                    <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                        {inv.riddorOverdue && <p>RIDDOR reporting window may be overdue (72 hours).</p>}
                                        {inv.investigationOverdue && <p>Investigation past due date.</p>}
                                        {inv.safeguardingPending && <p>Safeguarding referral not yet recorded.</p>}
                                    </div>
                                )}
                                <form onSubmit={submitInvestigation} className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <InputLabel value="Investigation status" />
                                            <select
                                                value={form.data.investigation_status}
                                                onChange={(e) => form.setData('investigation_status', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                            >
                                                {investigationStatusOptions.map((status) => (
                                                    <option key={status} value={status}>{status.replace(/_/g, ' ')}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <InputLabel value="Due date" />
                                            <input type="date" value={form.data.due_at} onChange={(e) => form.setData('due_at', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                        </div>
                                        <div className="md:col-span-2">
                                            <InputLabel value="Investigation summary" />
                                            <textarea rows={3} value={form.data.investigation_summary} onChange={(e) => form.setData('investigation_summary', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                        </div>
                                        <div>
                                            <InputLabel value="Root cause" />
                                            <textarea rows={3} value={form.data.root_cause} onChange={(e) => form.setData('root_cause', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                        </div>
                                        <div>
                                            <InputLabel value="Corrective actions" />
                                            <textarea rows={3} value={form.data.corrective_actions} onChange={(e) => form.setData('corrective_actions', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                        </div>
                                    </div>

                                    <div className="rounded-xl border border-rose-200 bg-rose-50/50 p-4">
                                        <h3 className="text-sm font-semibold text-rose-900">RIDDOR</h3>
                                        <label className="mt-2 flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={form.data.riddor_reportable} onChange={(e) => form.setData('riddor_reportable', e.target.checked)} className="rounded border-slate-300" />
                                            Reportable to HSE under RIDDOR
                                        </label>
                                        {form.data.riddor_reportable && (
                                            <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                                <select value={form.data.riddor_category} onChange={(e) => form.setData('riddor_category', e.target.value)} className="rounded-md border-slate-300 text-sm">
                                                    <option value="">Category</option>
                                                    {riddorCategoryOptions.map((opt) => (
                                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                    ))}
                                                </select>
                                                <input type="date" value={form.data.riddor_reported_at} onChange={(e) => form.setData('riddor_reported_at', e.target.value)} className="rounded-md border-slate-300 text-sm" placeholder="Reported date" />
                                                <input value={form.data.riddor_reference} onChange={(e) => form.setData('riddor_reference', e.target.value)} placeholder="HSE reference" className="rounded-md border-slate-300 text-sm" />
                                            </div>
                                        )}
                                    </div>

                                    <div className="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
                                        <h3 className="text-sm font-semibold text-indigo-900">Safeguarding</h3>
                                        <label className="mt-2 flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={form.data.safeguarding_concern} onChange={(e) => form.setData('safeguarding_concern', e.target.checked)} className="rounded border-slate-300" />
                                            Safeguarding concern identified
                                        </label>
                                        {form.data.safeguarding_concern && (
                                            <div className="mt-3 space-y-3">
                                                <label className="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" checked={form.data.safeguarding_referral_made} onChange={(e) => form.setData('safeguarding_referral_made', e.target.checked)} className="rounded border-slate-300" />
                                                    Referral made to local authority / safeguarding board
                                                </label>
                                                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                                    <input type="date" value={form.data.safeguarding_referral_at} onChange={(e) => form.setData('safeguarding_referral_at', e.target.value)} className="rounded-md border-slate-300 text-sm" />
                                                    <input value={form.data.safeguarding_authority} onChange={(e) => form.setData('safeguarding_authority', e.target.value)} placeholder="Authority" className="rounded-md border-slate-300 text-sm" />
                                                    <input value={form.data.safeguarding_reference} onChange={(e) => form.setData('safeguarding_reference', e.target.value)} placeholder="Referral reference" className="rounded-md border-slate-300 text-sm" />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <PrimaryButton disabled={form.processing}>Save investigation</PrimaryButton>
                                </form>
                            </SectionCard>
                        )}

                        <SectionCard title="Incident Details">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Field label="Patient Name" value={incident.patient_name} />
                                <Field label="Date of Birth" value={incident.patient_dob} />
                                <Field label="Incident Date" value={incident.incident_date} />
                                <Field label="Incident Time" value={incident.incident_time} />
                                <Field label="Location" value={incident.location} />
                                <Field label="Reporter" value={incident.reporter} />
                                <Field label="Duration" value={incident.duration_minutes ? `${incident.duration_minutes} minutes` : '-'} />
                                <Field label="Submitted" value={incident.submitted_at} />
                            </div>
                        </SectionCard>

                        <SectionCard title="Antecedent">
                            <p className="whitespace-pre-wrap text-sm text-slate-700">{incident.antecedent || 'Not recorded'}</p>
                        </SectionCard>

                        <SectionCard title="Behaviour">
                            <p className="whitespace-pre-wrap text-sm text-slate-700">{incident.behaviour || 'Not recorded'}</p>
                        </SectionCard>

                        <SectionCard title="Consequence" dark>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <p className="whitespace-pre-wrap text-sm text-slate-200">{incident.consequence || 'Not recorded'}</p>
                                <p className="whitespace-pre-wrap text-sm text-slate-200">{incident.immediate_outcome || 'Not recorded'}</p>
                            </div>
                        </SectionCard>

                        <SectionCard title="Post-Incident Review" dark>
                            <div className="grid grid-cols-1 gap-4">
                                <p className="whitespace-pre-wrap text-sm text-slate-200">{incident.lessons_learnt || 'Not recorded'}</p>
                                <p className="whitespace-pre-wrap text-sm text-slate-200">{incident.actions_planned || 'Not recorded'}</p>
                            </div>
                        </SectionCard>

                        <SectionCard title="Manager Sign-off">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <Field label="Manager Name" value={incident.manager_name} />
                                <Field label="Signed Off" value={incident.manager_sign_off ? 'Yes' : 'No'} />
                                <Field label="Involved Staff" value={incident.staff_members?.join(', ') || '-'} />
                            </div>
                        </SectionCard>
                    </main>
                </div>
            </div>
        </>
    );
}
