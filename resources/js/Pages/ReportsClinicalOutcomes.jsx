import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

function StatCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${accent}`}>{value}</p>
        </div>
    );
}

export default function ReportsClinicalOutcomes({ stats = {}, weeklyVitals = [], filters = {} }) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    const applyFilters = () => {
        router.get(route('reports.clinical-outcomes'), { from, to }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Clinical Outcomes Report" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('reports')} className="hover:text-slate-700">Reports</Link>
                            <span>/</span>
                            <span className="text-slate-900">Clinical outcomes</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">Clinical outcomes</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Aggregated observations, fluid balance, bowel chart, and wound activity for the selected period.
                            </p>
                        </section>

                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                            <div className="flex flex-wrap items-end gap-3">
                                <div>
                                    <label className="text-xs text-slate-500">From</label>
                                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="text-xs text-slate-500">To</label>
                                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                </div>
                                <button type="button" onClick={applyFilters} className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Apply
                                </button>
                                <a
                                    href={route('reports.clinical-outcomes.export.pdf', { from, to })}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export PDF
                                </a>
                            </div>
                        </section>

                        <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <StatCard label="Vital entries" value={stats.vitalEntries ?? 0} />
                            <StatCard label="Fluid entries" value={stats.fluidEntries ?? 0} />
                            <StatCard label="Bowel entries" value={stats.bowelEntries ?? 0} />
                            <StatCard label="Wound assessments" value={stats.woundAssessments ?? 0} />
                            <StatCard label="High pain flags" value={stats.highPainFlags ?? 0} accent="text-amber-700" />
                            <StatCard label="Low SpO₂ flags" value={stats.lowSpo2Flags ?? 0} accent="text-rose-700" />
                            <StatCard label="Wound escalations" value={stats.woundEscalations ?? 0} accent="text-rose-700" />
                            <StatCard label="Fluid intake (ml)" value={stats.fluidIntakeMl ?? 0} />
                        </div>

                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-800">Weekly vital trends</h2>
                            {weeklyVitals.length === 0 ? (
                                <p className="text-sm text-slate-500">No vital observations in this period.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                            <tr>
                                                <th className="px-3 py-2">Week</th>
                                                <th className="px-3 py-2">Entries</th>
                                                <th className="px-3 py-2">Avg HR</th>
                                                <th className="px-3 py-2">Avg SpO₂</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {weeklyVitals.map((row) => (
                                                <tr key={row.week}>
                                                    <td className="px-3 py-2">{row.week}</td>
                                                    <td className="px-3 py-2">{row.count}</td>
                                                    <td className="px-3 py-2">{row.avgHeartRate || '—'}</td>
                                                    <td className="px-3 py-2">{row.avgSpo2 || '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
