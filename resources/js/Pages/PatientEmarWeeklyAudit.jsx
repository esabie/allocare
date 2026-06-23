import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

function StatCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-xl font-bold ${accent}`}>{value}</p>
        </div>
    );
}

export default function PatientEmarWeeklyAudit({
    patientSlug,
    patientName,
    weekStart,
    weekEnd,
    summary = {},
}) {
    const [week, setWeek] = useState(weekStart || '');

    const applyWeek = () => {
        router.get(route('patients.mar.weekly-audit', patientSlug), { week }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title={`Weekly eMAR Audit — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:block">
                        <Link href={route('dashboard')}><ApplicationLogo className="mb-3 block w-full" /></Link>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-xs font-medium text-slate-500">
                                <Link href={route('patients.mar', patientSlug)} className="hover:text-slate-700">eMAR</Link>
                                <span>/</span>
                                <span className="text-slate-900">Weekly audit</span>
                            </div>
                            <Link href={route('reports.emar-weekly-audit', { week: weekStart })} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Organisation-wide audit
                            </Link>
                        </div>

                        <section className="mb-4 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-xl font-semibold text-slate-900">Weekly eMAR audit — {patientName}</h1>
                            <p className="mt-1 text-sm text-slate-500">{weekStart} to {weekEnd}</p>
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

                        <section className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            <StatCard label="Total" value={summary.totalAdministrations || 0} />
                            <StatCard label="Given" value={summary.given || 0} accent="text-emerald-700" />
                            <StatCard label="Refused" value={summary.refused || 0} accent="text-red-600" />
                            <StatCard label="Omitted" value={summary.omitted || 0} accent="text-amber-700" />
                            <StatCard label="Delayed" value={summary.delayed || 0} accent="text-orange-600" />
                            <StatCard label="Compliance" value={`${summary.complianceRate || 0}%`} accent="text-blue-700" />
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Exceptions this week</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[800px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Medication</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                            <th className="border border-slate-200 px-3 py-2">Scheduled</th>
                                            <th className="border border-slate-200 px-3 py-2">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(summary.exceptionRows || []).length === 0 ? (
                                            <tr><td colSpan={4} className="border border-slate-200 px-3 py-8 text-center text-slate-500">No exceptions this week.</td></tr>
                                        ) : (
                                            summary.exceptionRows.map((row) => (
                                                <tr key={`${row.id}-${row.updated_at}`}>
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
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
