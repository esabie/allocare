import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';
import ReportPagination, { paginatorData } from '@/Components/ReportPagination';

function StatCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${accent}`}>{value}</p>
        </div>
    );
}

const checklistLabels = {
    exceptions_reviewed: 'All refused, omitted, and delayed doses reviewed',
    controlled_register_reconciled: 'Controlled drug register reconciled',
    prn_usage_reviewed: 'PRN usage within limits reviewed',
    time_critical_escalations_reviewed: 'Time-critical escalations reviewed',
    action_plan_documented: 'Action plan documented where required',
};

export default function ReportsEmarWeeklyAudit({
    weekStart,
    weekEnd,
    summary = {},
    exceptionRows = [],
    audit = null,
    defaultChecklist = {},
}) {
    const flash = usePage().props?.flash;
    const [week, setWeek] = useState(weekStart || '');
    const exceptionItems = paginatorData(exceptionRows);

    const form = useForm({
        week_start: weekStart,
        notes: audit?.notes || '',
        checklist: { ...defaultChecklist, ...(audit?.checklist || {}) },
    });

    const applyWeek = () => {
        router.get(route('reports.emar-weekly-audit'), { week }, { preserveState: true, preserveScroll: true });
    };

    const submitSignOff = (e) => {
        e.preventDefault();
        form.post(route('reports.emar-weekly-audit.sign-off'));
    };

    return (
        <>
            <Head title="Weekly eMAR Audit" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-xs font-medium text-slate-500">
                                <Link href={route('reports')} className="hover:text-slate-700">Reports</Link>
                                <span>/</span>
                                <span className="text-slate-900">Weekly eMAR audit</span>
                            </div>
                            <a
                                href={route('reports.emar-weekly-audit.pdf', { week: weekStart })}
                                className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Download audit (PDF)
                            </a>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">Weekly eMAR clinical audit</h1>
                            <p className="mt-1 text-sm text-slate-500">Organisation-wide medication compliance review for clinical administrators — {weekStart} to {weekEnd}</p>
                            {flash?.success && <p className="mt-2 text-sm font-medium text-emerald-700">{flash.success}</p>}
                            {audit?.signedAtLabel && (
                                <p className="mt-2 text-sm text-emerald-700">
                                    Signed off by {audit.reviewerName || 'Clinical administrator'} on {audit.signedAtLabel}
                                </p>
                            )}
                        </section>

                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                            <div className="flex flex-wrap items-end gap-3">
                                <div>
                                    <label className="text-xs text-slate-500">Week containing</label>
                                    <input type="date" value={week} onChange={(e) => setWeek(e.target.value)} className="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                </div>
                                <button type="button" onClick={applyWeek} className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Apply</button>
                            </div>
                        </section>

                        <section className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                            <StatCard label="Total" value={summary.totalAdministrations || 0} />
                            <StatCard label="Given" value={summary.given || 0} accent="text-emerald-700" />
                            <StatCard label="Refused" value={summary.refused || 0} accent="text-red-600" />
                            <StatCard label="Omitted" value={summary.omitted || 0} accent="text-amber-700" />
                            <StatCard label="Delayed" value={summary.delayed || 0} accent="text-orange-600" />
                            <StatCard label="Compliance" value={`${summary.complianceRate || 0}%`} accent="text-blue-700" />
                        </section>

                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Exceptions this week</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[900px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Patient</th>
                                            <th className="border border-slate-200 px-3 py-2">Medication</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                            <th className="border border-slate-200 px-3 py-2">Scheduled</th>
                                            <th className="border border-slate-200 px-3 py-2">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(exceptionItems.length === 0) ? (
                                            <tr><td colSpan={5} className="border border-slate-200 px-3 py-8 text-center text-slate-500">No exceptions this week.</td></tr>
                                        ) : (
                                            exceptionItems.map((row) => (
                                                <tr key={`${row.id}-${row.updated_at}`}>
                                                    <td className="border border-slate-200 px-3 py-2">{row.patient}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.medication}</td>
                                                    <td className="border border-slate-200 px-3 py-2 uppercase">{row.status}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.scheduled_time}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.reason || '—'}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <ReportPagination pagination={exceptionRows} />
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Clinical administrator sign-off</h2>
                            <form onSubmit={submitSignOff} className="space-y-4">
                                <input type="hidden" name="week_start" value={form.data.week_start} />
                                <div className="space-y-2">
                                    {Object.entries(checklistLabels).map(([key, label]) => (
                                        <label key={key} className="flex items-start gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(form.data.checklist[key])}
                                                onChange={(e) => form.setData('checklist', { ...form.data.checklist, [key]: e.target.checked })}
                                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                                <div>
                                    <label className="text-xs font-semibold uppercase text-slate-500">Review notes</label>
                                    <textarea
                                        rows={3}
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                        className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                        placeholder="Actions taken, follow-up required, etc."
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                                >
                                    {form.processing ? 'Signing off…' : 'Sign off weekly audit'}
                                </button>
                            </form>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
