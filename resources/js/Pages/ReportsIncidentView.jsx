import { Head, Link } from '@inertiajs/react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
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

export default function ReportsIncidentView({ incident = {} }) {
    return (
        <>
            <Head title={`Incident - ${incident.patient_name}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('reports')} className="hover:text-slate-700">Reports</Link>
                            <span>/</span>
                            <Link href={route('reports.incidents')} className="hover:text-slate-700">Incidents</Link>
                            <span>/</span>
                            <span className="text-slate-900">{incident.patient_name}</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h1 className="text-2xl font-semibold text-slate-900">
                                        {incident.title && incident.title !== '-' ? incident.title : 'Incident Report'}
                                    </h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Patient: <span className="font-medium text-slate-700">{incident.patient_name}</span>
                                        <span className="ml-3">Status: </span>
                                        <span className={`font-semibold ${incident.status === 'Submitted' ? 'text-emerald-700' : 'text-amber-600'}`}>
                                            {incident.status}
                                        </span>
                                    </p>
                                </div>
                                <Link
                                    href={route('reports.incidents')}
                                    className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                                >
                                    ← Back to Incidents
                                </Link>
                            </div>
                        </section>

                        {/* Patient & Incident Info */}
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
                            {(incident.tags?.length > 0 || incident.impacts?.length > 0) && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {incident.tags?.map((tag) => (
                                        <span key={tag} className="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">
                                            {tag}
                                        </span>
                                    ))}
                                    {incident.impacts?.map((impact) => (
                                        <span key={impact} className="rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[10px] font-semibold text-red-700">
                                            {impact}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </SectionCard>

                        {/* Antecedent */}
                        <SectionCard title="Antecedent – What happened before the behaviour">
                            <p className="whitespace-pre-wrap text-sm text-slate-700">{incident.antecedent || 'Not recorded'}</p>
                        </SectionCard>

                        {/* Behaviour */}
                        <SectionCard title="Behaviour – What the person did">
                            <p className="whitespace-pre-wrap text-sm text-slate-700">{incident.behaviour || 'Not recorded'}</p>
                        </SectionCard>

                        {/* Consequence */}
                        <SectionCard title="Consequence – What happened after">
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Staff Response / Outcome</p>
                                    <p className="whitespace-pre-wrap text-sm text-slate-700">{incident.consequence || 'Not recorded'}</p>
                                </div>
                                <div>
                                    <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Immediate Outcome</p>
                                    <p className="whitespace-pre-wrap text-sm text-slate-700">{incident.immediate_outcome || 'Not recorded'}</p>
                                </div>
                            </div>
                        </SectionCard>

                        {/* Post-Incident Review */}
                        <SectionCard title="Post-Incident Review" dark>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Lessons Learnt / Preventive Strategies</p>
                                    <p className="whitespace-pre-wrap text-sm text-slate-200">{incident.lessons_learnt || 'Not recorded'}</p>
                                </div>
                                <div>
                                    <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">New Triggers Identified</p>
                                    <p className="whitespace-pre-wrap text-sm text-slate-200">{incident.new_triggers || 'Not recorded'}</p>
                                </div>
                                <div className="col-span-full">
                                    <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Actions / Changes Planned</p>
                                    <p className="whitespace-pre-wrap text-sm text-slate-200">{incident.actions_planned || 'Not recorded'}</p>
                                </div>
                            </div>
                        </SectionCard>

                        {/* Sign-off */}
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
